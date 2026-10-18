<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\Uniforme;
use App\Models\UniformeVente;
use Exception;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur dédié aux uniformes, entièrement indépendant de Api\PaiementController (scolarité)
 * et de Api\CantineController pour ne jamais risquer de régression sur les flux existants.
 */
class UniformeController extends Controller
{
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.environment'));
    }

    /**
     * Catalogue complet (le stock est informatif : il n'empêche jamais une sélection/achat).
     */
    public function getCatalogue()
    {
        $uniformes = Uniforme::orderBy('type')->orderBy('taille')->get();

        return response()->json($uniformes->map(fn ($u) => [
            'id' => $u->id,
            'type' => $u->type,
            'taille' => $u->taille,
            'prix' => (float) $u->prix,
            'quantite_stock' => $u->quantite_stock,
        ])->values());
    }

    /**
     * Historique des achats d'uniformes pour un élève, avec statut payé/partiel/impayé.
     */
    public function getHistoriqueEnfant(Eleve $eleve)
    {
        $ventes = UniformeVente::with('uniforme')
            ->where('eleve_id', $eleve->id)
            ->orderByDesc('date_vente')
            ->get();

        return response()->json($ventes->map(fn ($vente) => [
            'id' => $vente->id,
            'type' => $vente->uniforme->type,
            'taille' => $vente->uniforme->taille,
            'quantite' => $vente->quantite,
            'montant_total' => (float) $vente->montant_total,
            'montant_paye' => (float) $vente->montant_paye,
            'statut' => $vente->statut,
            'date_vente' => $vente->date_vente,
        ])->values());
    }

    /**
     * Créer une transaction FedaPay pour un panier d'articles sélectionnés par le parent.
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'eleve_id' => 'required|exists:eleves,id',
            'items' => 'required|array|min:1',
            'items.*.uniforme_id' => 'required|integer|exists:uniformes,id',
            'items.*.quantite' => 'required|integer|min:1',
        ]);

        $eleve = Eleve::findOrFail($request->eleve_id);
        $user = $request->user();
        $parent = Parents::where('user_id', $user->id)->first();

        $uniformes = Uniforme::whereIn('id', collect($request->items)->pluck('uniforme_id'))->get()->keyBy('id');

        $montantTotal = 0;
        foreach ($request->items as $item) {
            $montantTotal += $uniformes[$item['uniforme_id']]->prix * $item['quantite'];
        }

        try {
            $transaction = Transaction::create([
                'description' => "Achat uniformes de {$eleve->prenom} {$eleve->nom}",
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

            cache()->put("fedapay_uniformes_tx_{$transaction->id}", [
                'eleve_id' => $eleve->id,
                'parent_id' => $parent->id,
                'items' => $request->items,
            ], 600);

            return response()->json([
                'success' => true,
                'links' => [['rel' => 'approve', 'href' => $token->url]],
                'order_id' => $transaction->id,
                'montant_total' => (float) $montantTotal,
            ]);
        } catch (Exception $e) {
            Log::error('FedaPay uniformes createOrder: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erreur FedaPay'], 500);
        }
    }

    /**
     * Capturer et enregistrer les ventes + paiements après approbation FedaPay.
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

            $cached = cache()->get("fedapay_uniformes_tx_{$request->order_id}");
            if (! $cached) {
                return response()->json(['success' => false, 'message' => 'Session expirée'], 400);
            }

            DB::beginTransaction();

            $ventesIds = [];

            foreach ($cached['items'] as $item) {
                $uniforme = Uniforme::findOrFail($item['uniforme_id']);

                $vente = UniformeVente::create([
                    'uniforme_id' => $uniforme->id,
                    'eleve_id' => $cached['eleve_id'],
                    'quantite' => $item['quantite'],
                    'prix_unitaire' => $uniforme->prix,
                    'montant_total' => $uniforme->prix * $item['quantite'],
                    'date_vente' => now()->toDateString(),
                ]);

                Paiement::create([
                    'eleve_id' => $cached['eleve_id'],
                    'tranche_id' => null,
                    'payable_type' => UniformeVente::class,
                    'payable_id' => $vente->id,
                    'montant' => $vente->montant_total,
                    'date_paiement' => now()->toDateString(),
                    'mode_paiement' => 'mobile_money',
                    'reference' => 'FEDA-UNIFORMES-'.$request->order_id,
                    'parent_id' => $cached['parent_id'],
                    'commentaire' => 'Uniforme — '.$uniforme->type.' ('.$uniforme->taille.') via FedaPay',
                ]);

                // Stock informatif : jamais bloquant, peut devenir négatif.
                $uniforme->decrement('quantite_stock', $item['quantite']);

                $ventesIds[] = $vente->id;
            }

            DB::commit();

            cache()->forget("fedapay_uniformes_tx_{$request->order_id}");

            return response()->json([
                'success' => true,
                'message' => 'Achat uniformes enregistré avec succès',
                'ventes_ids' => $ventesIds,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('FedaPay uniformes captureOrder: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement'], 500);
        }
    }
}
