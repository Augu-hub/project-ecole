<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClasseAnnee;
use App\Models\Classe;
use App\Models\AnneeScolaire;
use Illuminate\Http\Request;

class ClasseAnneeController extends Controller
{
    public function index()
    {
        $classeAnnees = ClasseAnnee::with(['classe.niveau', 'anneeScolaire'])->orderBy('id', 'desc')->get();
        return view('back.pages.classe_annees.index', compact('classeAnnees'));
    }

    public function create()
    {
        $classes = Classe::with('niveau')->get()->mapWithKeys(function ($classe) {
            return [$classe->id => $classe->full_name];
        });
        $anneesScolaires = AnneeScolaire::orderBy('libelle', 'desc')->pluck('libelle', 'id');
        $anneeActiveId = AnneeScolaire::active()?->id;
        return view('back.pages.classe_annees.create', compact('classes', 'anneesScolaires', 'anneeActiveId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'classe_id'          => 'required|exists:classes,id',
            'annee_scolaire_id'  => 'required|exists:annee_scolaires,id',
        ]);

        // Vérifier unicité (classe_id + annee_scolaire_id)
        $exists = ClasseAnnee::where('classe_id', $validated['classe_id'])
                             ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
                             ->exists();
        if ($exists) {
            return back()->withErrors(['annee_scolaire_id' => 'Cette classe existe déjà pour cette année scolaire.'])->withInput();
        }

        ClasseAnnee::create($validated);

        return redirect()->route('admin.classe-annees.index')->with('success', 'Association classe-année créée avec succès.');
    }

    public function edit(ClasseAnnee $classeAnnee)
    {
        $classes = Classe::with('niveau')->get()->mapWithKeys(function ($classe) {
            return [$classe->id => $classe->full_name];
        });
        $anneesScolaires = AnneeScolaire::orderBy('libelle', 'desc')->pluck('libelle', 'id');
        return view('back.pages.classe_annees.edit', compact('classeAnnee', 'classes', 'anneesScolaires'));
    }

    public function update(Request $request, ClasseAnnee $classeAnnee)
    {
        $validated = $request->validate([
            'classe_id'          => 'required|exists:classes,id',
            'annee_scolaire_id'  => 'required|exists:annee_scolaires,id',
        ]);

        // Vérifier unicité en ignorant l'enregistrement courant
        $exists = ClasseAnnee::where('classe_id', $validated['classe_id'])
                             ->where('annee_scolaire_id', $validated['annee_scolaire_id'])
                             ->where('id', '!=', $classeAnnee->id)
                             ->exists();
        if ($exists) {
            return back()->withErrors(['annee_scolaire_id' => 'Cette classe existe déjà pour cette année scolaire.'])->withInput();
        }

        $classeAnnee->update($validated);

        return redirect()->route('admin.classe-annees.index')->with('success', 'Association mise à jour avec succès.');
    }

    public function destroy(ClasseAnnee $classeAnnee)
    {
        $classeAnnee->delete();
        return redirect()->route('admin.classe-annees.index')->with('success', 'Association supprimée avec succès.');
    }
}