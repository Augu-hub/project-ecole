<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scolarite;
use App\Models\ClasseAnnee;
use App\Models\Tranche;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScolariteController extends Controller
{
    /**
     * Liste des scolarités.
     */
    public function index()
    {
        $scolarites = Scolarite::with('classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire')->paginate(15);
        return view('back.pages.scolarites.index', compact('scolarites'));
    }

    /**
     * Formulaire de création.
     */
    public function create()
    {
        $classesAnnees = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->deLAnneeActive()->get();
        return view('back.pages.scolarites.create', compact('classesAnnees'));
    }

    /**
     * Enregistrement d'une nouvelle scolarité.
     */
    public function store(Request $request)
    {
        $request->validate([
            'classe_annee_id' => 'required|exists:classe_annees,id|unique:scolarites,classe_annee_id',
            'montant_annuel'   => 'required|numeric|min:0',
            'description'      => 'nullable|string',
        ]);

        Scolarite::create($request->all());

        return redirect()->route('admin.scolarites.index')
                         ->with('success', 'Scolarité créée avec succès.');
    }

    /**
     * Formulaire d'édition (affiche aussi les tranches existantes).
     */
    public function edit(Scolarite $scolarite)
    {
        $scolarite->load('tranches', 'classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire');
        $classesAnnees = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->deLAnneeActive($scolarite->classe_annee_id)->get();
        return view('back.pages.scolarites.edit', compact('scolarite', 'classesAnnees'));
    }

    /**
     * Mise à jour de la scolarité.
     */
    public function update(Request $request, Scolarite $scolarite)
    {
        $request->validate([
            'classe_annee_id' => [
                'required',
                'exists:classe_annees,id',
                Rule::unique('scolarites')->ignore($scolarite->id),
            ],
            'montant_annuel'   => 'required|numeric|min:0',
            'description'      => 'nullable|string',
        ]);

        $scolarite->update($request->all());

        return redirect()->route('admin.scolarites.index')
                         ->with('success', 'Scolarité mise à jour.');
    }

    /**
     * Suppression d'une scolarité (les tranches seront supprimées en cascade).
     */
    public function destroy(Scolarite $scolarite)
    {
        $scolarite->delete();
        return redirect()->route('admin.scolarites.index')
                         ->with('success', 'Scolarité supprimée.');
    }

    // ==================== GESTION DES TRANCHES ====================

    /**
     * Ajout d'une tranche.
     */
    public function storeTranche(Request $request, Scolarite $scolarite)
    {
        // Récupérer l'année scolaire liée via la classe_annee
        $anneeScolaire = $scolarite->classeAnnee->anneeScolaire;

        $request->validate([
            'libelle'        => 'required|string|max:100',
            'date_echeance'  => [
                'required',
                'date',
                'after_or_equal:' . $anneeScolaire->date_debut->format('Y-m-d'),
                'before_or_equal:' . $anneeScolaire->date_fin->format('Y-m-d'),
            ],
            'montant'        => 'required|numeric|min:0',
            'ordre'          => 'required|integer|min:1',
        ]);

        // Vérifier que l'ordre est unique pour cette scolarité
        $exists = Tranche::where('scolarite_id', $scolarite->id)
                         ->where('ordre', $request->ordre)
                         ->exists();
        if ($exists) {
            return back()->withErrors(['ordre' => 'Cet ordre est déjà utilisé pour une autre tranche.'])->withInput();
        }

        $scolarite->tranches()->create($request->all());

        return back()->with('success', 'Tranche ajoutée avec succès.');
    }

    /**
     * Mise à jour d'une tranche.
     */
    public function updateTranche(Request $request, Scolarite $scolarite, Tranche $tranche)
    {
        // Vérifier que la tranche appartient bien à cette scolarité
        if ($tranche->scolarite_id != $scolarite->id) {
            abort(404);
        }

        $anneeScolaire = $scolarite->classeAnnee->anneeScolaire;

        $request->validate([
            'libelle'        => 'required|string|max:100',
            'date_echeance'  => [
                'required',
                'date',
                'after_or_equal:' . $anneeScolaire->date_debut->format('Y-m-d'),
                'before_or_equal:' . $anneeScolaire->date_fin->format('Y-m-d'),
            ],
            'montant'        => 'required|numeric|min:0',
            'ordre'          => 'required|integer|min:1',
        ]);

        // Vérifier l'unicité de l'ordre (sauf pour la même tranche)
        $exists = Tranche::where('scolarite_id', $scolarite->id)
                         ->where('ordre', $request->ordre)
                         ->where('id', '!=', $tranche->id)
                         ->exists();
        if ($exists) {
            return back()->withErrors(['ordre' => 'Cet ordre est déjà utilisé pour une autre tranche.'])->withInput();
        }

        $tranche->update($request->all());

        return back()->with('success', 'Tranche mise à jour.');
    }

    /**
     * Suppression d'une tranche.
     */
    public function destroyTranche(Scolarite $scolarite, Tranche $tranche)
    {
        if ($tranche->scolarite_id != $scolarite->id) {
            abort(404);
        }
        $tranche->delete();
        return back()->with('success', 'Tranche supprimée.');
    }
}