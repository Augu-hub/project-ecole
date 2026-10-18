<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use Illuminate\Http\Request;

class CantineOffreController extends Controller
{
    public function index(Request $request)
    {
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeScolaireId = (int) $request->input('annee_scolaire_id', AnneeScolaire::active()?->id);
        $anneeScolaire = $anneesScolaires->firstWhere('id', $anneeScolaireId);
        $cycle = in_array($request->input('cycle'), ['primaire', 'secondaire'], true)
            ? $request->input('cycle')
            : 'secondaire';

        $offres = $anneeScolaire
            ? CantineOffre::where('annee_scolaire_id', $anneeScolaire->id)
                ->where('cycle', $cycle)
                ->get()
                ->groupBy('mois')
            : collect();

        // Ordre "année scolaire" (septembre -> juin), pas l'ordre calendaire.
        $mois = collect(CantineOffre::MOIS_ANNEE_SCOLAIRE)
            ->mapWithKeys(fn ($m) => [$m => CantineOffre::MOIS_LABELS[$m]]);

        return view('back.pages.cantine.tarifs', compact('anneesScolaires', 'anneeScolaire', 'offres', 'mois', 'cycle'));
    }

    public function enregistrer(Request $request)
    {
        $request->validate([
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
            'cycle' => 'required|in:primaire,secondaire',
            'ticket' => 'nullable|array',
            'ticket.*' => 'nullable|numeric|min:0',
            'petit_dejeuner' => 'nullable|array',
            'petit_dejeuner.*' => 'nullable|numeric|min:0',
        ]);

        foreach ($request->input('ticket', []) as $mois => $montant) {
            if ($montant === null || $montant === '') {
                continue;
            }
            CantineOffre::updateOrCreate(
                ['annee_scolaire_id' => $request->annee_scolaire_id, 'mois' => $mois, 'type' => 'ticket', 'cycle' => $request->cycle],
                ['montant' => $montant]
            );
        }

        foreach ($request->input('petit_dejeuner', []) as $mois => $montant) {
            if ($montant === null || $montant === '') {
                continue;
            }
            CantineOffre::updateOrCreate(
                ['annee_scolaire_id' => $request->annee_scolaire_id, 'mois' => $mois, 'type' => 'petit_dejeuner', 'cycle' => $request->cycle],
                ['montant' => $montant]
            );
        }

        return redirect()->route('admin.cantine.tarifs', ['annee_scolaire_id' => $request->annee_scolaire_id, 'cycle' => $request->cycle])
            ->with('success', 'Tarifs cantine enregistrés.');
    }
}
