<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Parents;
use Exception;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur dédié à la cantine, entièrement indépendant de Api\PaiementController (scolarité)
 * pour ne jamais risquer de régression sur le flux de paiement FedaPay existant en production.
 */
class CantineController extends Controller
{
    /**
     * Le primaire (autre école) encaisse sa cantine sur un compte FedaPay distinct du
     * secondaire : la clé API doit être re-sélectionnée pour CHAQUE élève concerné, jamais
     * fixée une fois pour toutes dans le constructeur (une requête peut manipuler des élèves
     * de cycles différents selon l'ordre d'appel).
     */
    private function configurerFedaPayPourEleve(?Eleve $eleve): void
    {
        $cycle = $eleve?->cycle_cantine ?? 'secondaire';
        $config = $cycle === 'primaire'
            ? config('services.fedapay.primaire')
            : config('services.fedapay');

        FedaPay::setApiKey($config['secret_key']);
        FedaPay::setEnvironment($config['environment']);
    }

    /**
     * Tarifs cantine de l'année scolaire (active par défaut) avec statut payé/non payé pour
     * cet élève. Filtré sur le cycle (primaire/secondaire) de l'élève : le primaire n'a pas
     * forcément le même tarif que le secondaire.
     */
    public function getCantineEnfant(Eleve $eleve, Request $request)
    {
        $anneeScolaireId = $request->filled('annee_scolaire_id')
            ? (int) $request->input('annee_scolaire_id')
            : AnneeScolaire::active()?->id;

        if (! $anneeScolaireId) {
            return response()->json([], 200);
        }

        $offres = CantineOffre::where('annee_scolaire_id', $anneeScolaireId)
            ->where('cycle', $eleve->cycle_cantine)
            ->orderBy('mois')
            ->get();

        $totalPayeParOffre = Paiement::where('payable_type', CantineOffre::class)
            ->where('eleve_id', $eleve->id)
            ->whereIn('payable_id', $offres->pluck('id'))
            ->selectRaw('payable_id, SUM(montant) as total')
            ->groupBy('payable_id')
            ->pluck('total', 'payable_id');

        $resultat = $offres->map(fn ($offre) => [
            'id' => $offre->id,
            'mois' => $offre->mois,
            'mois_label' => $offre->mois_label,
            'type' => $offre->type,
            'montant' => (float) $offre->montant,
            'paye' => ($totalPayeParOffre[$offre->id] ?? 0) >= $offre->montant,
        ]);

        return response()->json($resultat->values());
    }

    /**
     * Créer une transaction FedaPay pour un ensemble d'offres cantine sélectionnées par le parent.
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'eleve_id' => 'required|exists:eleves,id',
            'offre_ids' => 'required|array|min:1',
            'offre_ids.*' => 'integer|exists:cantine_offres,id',
        ]);

        $eleve = Eleve::findOrFail($request->eleve_id);
        $user = $request->user();
        $parent = Parents::where('user_id', $user->id)->first();

        // Filtre par cycle en plus des ids : empêche un client mobile trafiqué de payer des
        // offres d'un autre cycle que celui de l'élève réellement concerné.
        $offres = CantineOffre::whereIn('id', $request->offre_ids)
            ->where('cycle', $eleve->cycle_cantine)
            ->get();

        // Exclure toute offre déjà intégralement payée par cet élève (protection contre un
        // double paiement si le client mobile envoie un état obsolète).
        $offresAPayer = $offres->filter(function ($offre) use ($eleve) {
            $paye = Paiement::where('payable_type', CantineOffre::class)
                ->where('payable_id', $offre->id)
                ->where('eleve_id', $eleve->id)
                ->sum('montant');

            return $paye < $offre->montant;
        });

        if ($offresAPayer->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Les éléments sélectionnés sont déjà payés.',
            ], 422);
        }

        $montantTotal = $offresAPayer->sum('montant');

        $this->configurerFedaPayPourEleve($eleve);

        try {
            $transaction = Transaction::create([
                'description' => "Cantine de {$eleve->prenom} {$eleve->nom}",
                'amount' => (int) $montantTotal,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => url('/api/fedapay/success'),
                'cancel_url' => url('/api/fedapay/cancel'),
                'customer' => [
                    'email' => $user->email ?? 'parent@schoollink.ci',
                    'firstname' => $parent->prenom ?? 'Parent',
                    'lastname' => $parent->nom ?? '',
                ],
            ]);

            $token = $transaction->generateToken();

            cache()->put("fedapay_cantine_tx_{$transaction->id}", [
                'eleve_id' => $eleve->id,
                'parent_id' => $parent->id,
                'offre_ids' => $offresAPayer->pluck('id')->all(),
            ], 600);

            return response()->json([
                'success' => true,
                'links' => [['rel' => 'approve', 'href' => $token->url]],
                'order_id' => $transaction->id,
                'montant_total' => (float) $montantTotal,
            ]);
        } catch (Exception $e) {
            Log::error('FedaPay cantine createOrder: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erreur FedaPay'], 500);
        }
    }

    /**
     * Capturer et enregistrer les paiements cantine après approbation FedaPay.
     */
    public function captureOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer',
        ]);

        // Le cache (posé par createOrder) doit être lu AVANT d'interroger FedaPay : il faut
        // connaître le cycle de l'élève pour sélectionner le bon compte, sinon la transaction
        // du primaire est cherchée à tort sur le compte secondaire (ou l'inverse) et échoue.
        $cached = cache()->get("fedapay_cantine_tx_{$request->order_id}");
        if (! $cached) {
            return response()->json(['success' => false, 'message' => 'Session expirée'], 400);
        }

        $this->configurerFedaPayPourEleve(Eleve::find($cached['eleve_id']));

        try {
            $fedaTx = Transaction::retrieve($request->order_id);

            if ($fedaTx->status !== 'approved') {
                return response()->json(['success' => false, 'message' => 'Transaction non approuvée'], 400);
            }

            DB::beginTransaction();

            $offres = CantineOffre::whereIn('id', $cached['offre_ids'])->get();
            $paiementsIds = [];

            foreach ($offres as $offre) {
                $paiement = Paiement::create([
                    'eleve_id' => $cached['eleve_id'],
                    'tranche_id' => null,
                    'payable_type' => CantineOffre::class,
                    'payable_id' => $offre->id,
                    'montant' => $offre->montant,
                    'date_paiement' => now()->toDateString(),
                    'mode_paiement' => 'mobile_money',
                    'reference' => 'FEDA-CANTINE-'.$request->order_id,
                    'parent_id' => $cached['parent_id'],
                    'commentaire' => 'Cantine — '.$offre->mois_label.' ('.($offre->type === 'ticket' ? 'Ticket' : 'Petit-déjeuner').') via FedaPay',
                ]);
                $paiementsIds[] = $paiement->id;
            }

            DB::commit();

            cache()->forget("fedapay_cantine_tx_{$request->order_id}");

            return response()->json([
                'success' => true,
                'message' => 'Paiement cantine enregistré avec succès',
                'paiements_ids' => $paiementsIds,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('FedaPay cantine captureOrder: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement'], 500);
        }
    }
}
