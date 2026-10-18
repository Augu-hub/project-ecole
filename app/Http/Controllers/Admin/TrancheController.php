<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scolarite;
use App\Models\Tranche;
use Illuminate\Http\Request;

class TrancheController extends Controller
{
    /**
     * Affiche la liste des tranches pour une scolarité donnée.
     */
    public function index(Scolarite $scolarite)
    {
        $tranches = $scolarite->tranches()->orderBy('ordre')->get();
        return view('back.pages.tranches.index', compact('scolarite', 'tranches'));
    }

    /**
     * Formulaire de création d'une tranche.
     */
    public function create(Scolarite $scolarite)
    {
        return view('back.pages.tranches.create', compact('scolarite'));
    }

    /**
     * Enregistrement d'une nouvelle tranche.
     */
    public function store(Request $request, Scolarite $scolarite)
    {
        // Récupérer l'année scolaire via la scolarite->classeAnnee->anneeScolaire
        $anneeScolaire = $scolarite->classeAnnee->anneeScolaire;

        $request->validate([
            'libelle'       => 'required|string|max:100',
            'date_echeance' => [
                'required',
                'date',
                'after_or_equal:' . $anneeScolaire->date_debut->format('Y-m-d'),
                'before_or_equal:' . $anneeScolaire->date_fin->format('Y-m-d'),
            ],
            'montant'       => 'required|numeric|min:0',
            'ordre'         => 'required|integer|min:1|unique:tranches,ordre,NULL,id,scolarite_id,' . $scolarite->id,
        ], [
            'date_echeance.after_or_equal' => 'La date d\'échéance doit être après ou égale au début de l\'année scolaire (' . $anneeScolaire->date_debut->format('d/m/Y') . ').',
            'date_echeance.before_or_equal' => 'La date d\'échéance doit être avant ou égale à la fin de l\'année scolaire (' . $anneeScolaire->date_fin->format('d/m/Y') . ').',
            'ordre.unique' => 'Cet ordre est déjà utilisé pour une autre tranche de cette scolarité.',
        ]);

        $scolarite->tranches()->create($request->all());

        return redirect()->route('admin.tranches.index', $scolarite)
                         ->with('success', 'Tranche ajoutée avec succès.');
    }

    /**
     * Formulaire d'édition d'une tranche.
     */
    public function edit(Scolarite $scolarite, Tranche $tranche)
    {
        // Vérifier que la tranche appartient bien à la scolarite
        if ($tranche->scolarite_id != $scolarite->id) {
            abort(404);
        }
        return view('back.pages.tranches.edit', compact('scolarite', 'tranche'));
    }

    /**
     * Mise à jour d'une tranche.
     */
    public function update(Request $request, Scolarite $scolarite, Tranche $tranche)
    {
        if ($tranche->scolarite_id != $scolarite->id) {
            abort(404);
        }

        $anneeScolaire = $scolarite->classeAnnee->anneeScolaire;

        $request->validate([
            'libelle'       => 'required|string|max:100',
            'date_echeance' => [
                'required',
                'date',
                'after_or_equal:' . $anneeScolaire->date_debut->format('Y-m-d'),
                'before_or_equal:' . $anneeScolaire->date_fin->format('Y-m-d'),
            ],
            'montant'       => 'required|numeric|min:0',
            'ordre'         => [
                'required',
                'integer',
                'min:1',
                'unique:tranches,ordre,' . $tranche->id . ',id,scolarite_id,' . $scolarite->id,
            ],
        ], [
            'date_echeance.after_or_equal' => 'La date d\'échéance doit être après ou égale au début de l\'année scolaire (' . $anneeScolaire->date_debut->format('d/m/Y') . ').',
            'date_echeance.before_or_equal' => 'La date d\'échéance doit être avant ou égale à la fin de l\'année scolaire (' . $anneeScolaire->date_fin->format('d/m/Y') . ').',
            'ordre.unique' => 'Cet ordre est déjà utilisé pour une autre tranche de cette scolarité.',
        ]);

        $tranche->update($request->all());

        return redirect()->route('admin.tranches.index', $scolarite)
                         ->with('success', 'Tranche mise à jour.');
    }

    /**
     * Suppression d'une tranche.
     */
    public function destroy(Scolarite $scolarite, Tranche $tranche)
    {
        if ($tranche->scolarite_id != $scolarite->id) {
            abort(404);
        }
        $tranche->delete();
        return redirect()->route('admin.tranches.index', $scolarite)
                         ->with('success', 'Tranche supprimée.');
    }
}