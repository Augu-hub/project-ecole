<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Uniforme;
use App\Models\UniformeVente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UniformeVenteController extends Controller
{
    /**
     * Enregistrer une vente d'uniforme pour un élève (secondaire uniquement), avec paiement
     * immédiat optionnel : si "montant" est fourni, un Paiement est créé dans la foulée, sinon
     * la vente reste en attente de paiement (créditée), réglable plus tard via payer().
     */
    public function vendre(Request $request, Eleve $eleve)
    {
        $eleve->load('classeAnnee.classe.niveau');

        if (! $eleve->classeAnnee || $eleve->classeAnnee->classe->niveau->cycle !== 'secondaire') {
            return back()->with('error', 'Les uniformes ne sont disponibles que pour le cycle secondaire.');
        }

        $request->validate([
            'uniforme_id' => 'required|exists:uniformes,id',
            'quantite' => 'required|integer|min:1',
            'montant' => 'nullable|numeric|min:1',
            'mode_paiement' => 'required_with:montant|in:especes,mobile_money,virement,carte,cheque',
        ]);

        $uniforme = Uniforme::findOrFail($request->uniforme_id);

        if ($request->filled('montant') && ! $eleve->parent_id) {
            return back()->with('error', "Cet élève n'a pas de parent associé, impossible d'enregistrer le paiement.");
        }

        DB::transaction(function () use ($request, $eleve, $uniforme) {
            $vente = UniformeVente::create([
                'uniforme_id' => $uniforme->id,
                'eleve_id' => $eleve->id,
                'quantite' => $request->quantite,
                'prix_unitaire' => $uniforme->prix,
                'montant_total' => $uniforme->prix * $request->quantite,
                'date_vente' => now()->toDateString(),
            ]);

            // Stock informatif : jamais bloquant, peut devenir négatif.
            $uniforme->decrement('quantite_stock', $request->quantite);

            if ($request->filled('montant')) {
                Paiement::create([
                    'eleve_id' => $eleve->id,
                    'tranche_id' => null,
                    'payable_type' => UniformeVente::class,
                    'payable_id' => $vente->id,
                    'montant' => $request->montant,
                    'date_paiement' => now()->toDateString(),
                    'mode_paiement' => $request->mode_paiement,
                    'parent_id' => $eleve->parent_id,
                    'commentaire' => 'Uniforme — '.$uniforme->type.' ('.$uniforme->taille.')',
                ]);
            }
        });

        return back()->with('success', 'Vente enregistrée.');
    }

    /**
     * Ajouter un paiement (partiel ou total) à une vente déjà créée.
     */
    public function payer(Request $request, UniformeVente $vente)
    {
        $request->validate([
            'montant' => 'required|numeric|min:1',
            'mode_paiement' => 'required|in:especes,mobile_money,virement,carte,cheque',
        ]);

        $eleve = $vente->eleve;

        if (! $eleve->parent_id) {
            return back()->with('error', "Cet élève n'a pas de parent associé, impossible d'enregistrer le paiement.");
        }

        Paiement::create([
            'eleve_id' => $eleve->id,
            'tranche_id' => null,
            'payable_type' => UniformeVente::class,
            'payable_id' => $vente->id,
            'montant' => $request->montant,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => $request->mode_paiement,
            'parent_id' => $eleve->parent_id,
            'commentaire' => 'Uniforme — '.$vente->uniforme->type.' ('.$vente->uniforme->taille.')',
        ]);

        return back()->with('success', 'Paiement enregistré.');
    }
}
