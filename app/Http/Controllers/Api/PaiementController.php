<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\Scolarite;
use App\Models\Tranche;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.environment')); // 'sandbox' ou 'live'
    }

    /**
     * Résout la classe_annee de l'élève pour une année scolaire donnée (optionnelle) : via ses
     * notes ou ses paiements de cette année, avec repli sur sa classe_annee courante si l'année
     * demandée est bien l'année courante. Permet au parent de consulter la scolarité d'une année
     * antérieure sans casser le comportement par défaut (année active) des appels existants.
     */
    private function resolveClasseAnneeId(Eleve $eleve, ?int $anneeScolaireId): ?int
    {
        if (! $anneeScolaireId) {
            return $eleve->classe_annee_id;
        }

        $viaNote = Note::where('eleve_id', $eleve->id)
            ->whereHas('classeAnnee', fn ($q) => $q->where('annee_scolaire_id', $anneeScolaireId))
            ->value('classe_annee_id');
        if ($viaNote) {
            return $viaNote;
        }

        $viaPaiement = Paiement::where('eleve_id', $eleve->id)
            ->whereHas('tranche.scolarite.classeAnnee', fn ($q) => $q->where('annee_scolaire_id', $anneeScolaireId))
            ->with('tranche.scolarite')
            ->first();
        if ($viaPaiement) {
            return $viaPaiement->tranche->scolarite->classe_annee_id;
        }

        $courante = $eleve->classeAnnee;
        if ($courante && $courante->annee_scolaire_id == $anneeScolaireId) {
            return $courante->id;
        }

        return null;
    }

    /**
     * Récupérer la scolarité complète d'un élève avec tranches et paiements
     */
    public function getScolariteEnfant(Eleve $eleve, Request $request)
    {
        $anneeScolaireId = $request->filled('annee_scolaire_id') ? (int) $request->input('annee_scolaire_id') : null;
        $classeAnneeId = $this->resolveClasseAnneeId($eleve, $anneeScolaireId);

        $scolarite = $classeAnneeId ? Scolarite::where('classe_annee_id', $classeAnneeId)->first() : null;

        if (! $scolarite) {
            return response()->json(['message' => 'Aucune scolarité configurée'], 404);
        }

        $tranches = Tranche::where('scolarite_id', $scolarite->id)
            ->orderBy('ordre')
            ->get()
            ->map(function ($tranche) use ($eleve) {
                // Total payé pour cette tranche par cet élève
                $totalPaye = Paiement::where('eleve_id', $eleve->id)
                    ->where('tranche_id', $tranche->id)
                    ->sum('montant');

                $resteAPayer = max(0, $tranche->montant - $totalPaye);

                return [
                    'id' => $tranche->id,
                    'libelle' => $tranche->libelle,
                    'montant' => (float) $tranche->montant,
                    'date_echeance' => $tranche->date_echeance,
                    'ordre' => $tranche->ordre,
                    'total_paye' => (float) $totalPaye,
                    'reste' => (float) $resteAPayer,
                    'est_soldee' => $resteAPayer <= 0,
                    'est_en_retard' => $resteAPayer > 0 && now()->isAfter($tranche->date_echeance),
                ];
            });

        // Paiements récents de cet élève, scopés sur les tranches de la scolarité résolue ci-dessus.
        $paiementsRecents = Paiement::where('eleve_id', $eleve->id)
            ->whereIn('tranche_id', $tranches->pluck('id'))
            ->with('tranche')
            ->orderBy('date_paiement', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'montant' => (float) $p->montant,
                'date_paiement' => $p->date_paiement,
                'mode_paiement' => $p->mode_paiement,
                'reference' => $p->reference,
                'tranche_libelle' => $p->tranche?->libelle ?? 'Non spécifiée',
                'commentaire' => $p->commentaire,
            ]);

        $totalDu = $tranches->sum('reste');
        $totalPaye = $tranches->sum('total_paye');
        $totalAnnuel = (float) $scolarite->montant_annuel;

        return response()->json([
            'scolarite' => [
                'id' => $scolarite->id,
                'montant_annuel' => $totalAnnuel,
                'description' => $scolarite->description,
            ],
            'tranches' => $tranches,
            'paiements_recents' => $paiementsRecents,
            'resume' => [
                'total_annuel' => $totalAnnuel,
                'total_paye' => $totalPaye,
                'total_du' => $totalDu,
                'est_a_jour' => $totalDu <= 0,
            ],
        ]);
    }

    /**
     * Paiements récents de tous les enfants d'un parent
     */
    public function getPaiementsRecents(Parents $parent)
    {
        $elevesIds = Eleve::where('parent_id', $parent->id)->pluck('id');

        // Uniquement les paiements de scolarité (payable_type = Tranche) : ce endpoint alimente
        // l'écran "Frais Scolaires", il ne doit pas mélanger les paiements cantine/uniformes
        // (autre payable_type), sans quoi un enfant du primaire (cantine uniquement) apparaît
        // à tort dans les paiements récents de frais scolaires.
        $paiements = Paiement::whereIn('eleve_id', $elevesIds)
            ->where('payable_type', Tranche::class)
            ->with(['eleve', 'tranche'])
            ->orderBy('date_paiement', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'eleve_nom' => $p->eleve->nom.' '.$p->eleve->prenom,
                'montant' => (float) $p->montant,
                'date_paiement' => $p->date_paiement,
                'mode_paiement' => $p->mode_paiement,
                'reference' => $p->reference,
                'tranche_libelle' => $p->tranche?->libelle ?? '-',
            ]);

        return response()->json($paiements);
    }

    /**
     * Simuler la répartition d'un montant sur les tranches
     * Appelé en temps réel depuis Flutter quand le parent saisit un montant
     */
    public function simulerRepartition(Request $request)
    {
        $request->validate([
            'eleve_id' => 'required|exists:eleves,id',
            'montant' => 'required|numeric|min:1',
        ]);

        $eleve = Eleve::find($request->eleve_id);
        $repartition = $this->calculerRepartition($eleve, $request->montant);

        return response()->json($repartition);
    }

    /**
     * Créer une transaction FedaPay
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'eleve_id' => 'required|exists:eleves,id',
            'montant' => 'required|numeric|min:100',
        ]);

        $eleve = Eleve::find($request->eleve_id);
        $user = $request->user();

        // Récupérer le parent lié à l'user connecté
        $parent = Parents::where('user_id', $user->id)->first();

        // Vérifier que le montant ne dépasse pas ce qui est dû
        $scolarite = Scolarite::where('classe_annee_id', $eleve->classe_annee_id)->first();
        $tranches = Tranche::where('scolarite_id', $scolarite->id)->orderBy('ordre')->get();

        $totalDu = 0;
        foreach ($tranches as $tranche) {
            $paye = Paiement::where('eleve_id', $eleve->id)->where('tranche_id', $tranche->id)->sum('montant');
            $totalDu += max(0, $tranche->montant - $paye);
        }

        if ($request->montant > $totalDu) {
            return response()->json([
                'success' => false,
                'message' => "Le montant dépasse le total dû ({$totalDu} XOF)",
            ], 422);
        }

        try {
            $transaction = Transaction::create([
                'description' => "Scolarité de {$eleve->prenom} {$eleve->nom}",
                'amount' => (int) $request->montant,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => url('/api/fedapay/success'),
                'cancel_url' => url('/api/fedapay/cancel'),
                'customer' => [
                    'email' => $request->user()->email ?? 'parent@schoollink.ci',
                    'firstname' => $parent->prenom ?? 'Parent',
                    'lastname' => $parent->nom ?? '',
                ],
            ]);

            $token = $transaction->generateToken();

            // Stocker temporairement les infos en cache (10 min)
            cache()->put("fedapay_tx_{$transaction->id}", [
                'eleve_id' => $eleve->id,
                'parent_id' => $parent->id,
                'montant' => $request->montant,
            ], 600);

            return response()->json([
                'success' => true,
                'links' => [['rel' => 'approve', 'href' => $token->url]],
                'order_id' => $transaction->id,
            ]);
        } catch (Exception $e) {
            Log::error('FedaPay createOrder: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erreur FedaPay'], 500);
        }
    }

    /**
     * Capturer et enregistrer le paiement après approbation FedaPay
     */
    public function captureOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer',
        ]);

        try {
            $fedaTx = Transaction::retrieve($request->order_id);

            if ($fedaTx->status !== 'approved') {
                return response()->json(['success' => false, 'message' => 'Transaction non approuvée'], 400);
            }

            // Récupérer les infos mises en cache
            $cached = cache()->get("fedapay_tx_{$request->order_id}");
            if (! $cached) {
                return response()->json(['success' => false, 'message' => 'Session expirée'], 400);
            }

            $eleve = Eleve::find($cached['eleve_id']);
            $montant = $cached['montant'];

            DB::beginTransaction();

            // Calculer la répartition et créer les paiements
            $repartition = $this->calculerRepartition($eleve, $montant);
            $paiementsIds = [];

            foreach ($repartition['repartition'] as $item) {
                if ($item['montant_applique'] > 0) {
                    $paiement = Paiement::create([
                        'eleve_id' => $eleve->id,
                        'tranche_id' => $item['tranche_id'],
                        'payable_type' => Tranche::class,
                        'payable_id' => $item['tranche_id'],
                        'montant' => $item['montant_applique'],
                        'date_paiement' => now()->toDateString(),
                        'mode_paiement' => 'mobile_money',
                        'reference' => 'FEDA-'.$request->order_id,
                        'parent_id' => $cached['parent_id'],
                        'commentaire' => 'Paiement via FedaPay',
                    ]);
                    $paiementsIds[] = $paiement->id;
                }
            }

            DB::commit();

            cache()->forget("fedapay_tx_{$request->order_id}");

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'paiements_ids' => $paiementsIds,
                'repartition' => $repartition['repartition'],
                'montant_total' => $montant,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('FedaPay captureOrder: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement'], 500);
        }
    }

    /**
     * Télécharger le reçu PDF d'un paiement
     */
    public function downloadRecu(Paiement $paiement)
    {
        $paiement->load(['eleve.classeAnnee.classe.niveau', 'eleve.parentPrincipal', 'tranche']);

        $pdf = Pdf::loadView('pdf.recu_paiement', [
            'paiement' => $paiement,
            'ecole' => config('ecole'),
        ]);

        $pdf->setPaper('A5', 'portrait');

        return $pdf->download("recu_paiement_{$paiement->id}.pdf");
    }

    public function successRedirect(Request $request)
    {
        return response()->json([
            'status' => $request->query('transaction-status'),
            'order_id' => $request->query('id'),
        ]);
    }

    public function cancelRedirect(Request $request)
    {
        return response()->json(['message' => 'Paiement annulé']);
    }

    /**
     * Logique de répartition du montant sur les tranches (dans l'ordre)
     */
    private function calculerRepartition(Eleve $eleve, float $montant): array
    {
        $scolarite = Scolarite::where('classe_annee_id', $eleve->classe_annee_id)->first();
        $tranches = Tranche::where('scolarite_id', $scolarite->id)->orderBy('ordre')->get();

        $repartition = [];
        $resteMontant = $montant;

        foreach ($tranches as $tranche) {
            $dejaPaye = Paiement::where('eleve_id', $eleve->id)
                ->where('tranche_id', $tranche->id)
                ->sum('montant');

            $resteTraanche = max(0, $tranche->montant - $dejaPaye);

            if ($resteTraanche <= 0 || $resteMontant <= 0) {
                continue;
            }

            $montantApplique = min($resteMontant, $resteTraanche);
            $resteMontant -= $montantApplique;

            $repartition[] = [
                'tranche_id' => $tranche->id,
                'tranche_libelle' => $tranche->libelle,
                'montant_tranche' => (float) $tranche->montant,
                'deja_paye' => (float) $dejaPaye,
                'reste_avant' => (float) $resteTraanche,
                'montant_applique' => (float) $montantApplique,
                'reste_apres' => (float) ($resteTraanche - $montantApplique),
                'soldee' => ($resteTraanche - $montantApplique) <= 0,
            ];
        }

        return [
            'repartition' => $repartition,
            'montant_total' => $montant,
            'reste_non_applique' => $resteMontant,
        ];
    }
}
