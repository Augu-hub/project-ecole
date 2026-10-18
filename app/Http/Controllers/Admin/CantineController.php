<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CantineOffre;
use App\Models\Eleve;
use App\Models\Paiement;
use Illuminate\Http\Request;

class CantineController extends Controller
{
    /**
     * Saisie manuelle (back-office) d'un paiement cantine (ticket ou petit-déjeuner) pour un élève.
     */
    public function payer(Request $request, Eleve $eleve, CantineOffre $offre)
    {
        $request->validate([
            'montant' => 'required|numeric|min:1',
            'mode_paiement' => 'required|in:especes,mobile_money,virement,carte,cheque',
        ]);

        if (! $eleve->parent_id) {
            return back()->with('error', "Cet élève n'a pas de parent associé, impossible d'enregistrer le paiement.");
        }

        Paiement::create([
            'eleve_id' => $eleve->id,
            'tranche_id' => null,
            'payable_type' => CantineOffre::class,
            'payable_id' => $offre->id,
            'montant' => $request->montant,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => $request->mode_paiement,
            'parent_id' => $eleve->parent_id,
            'commentaire' => 'Cantine — '.$offre->mois_label.' ('.($offre->type === 'ticket' ? 'Ticket' : 'Petit-déjeuner').')',
        ]);

        return back()->with('success', 'Paiement cantine enregistré.');
    }
}
