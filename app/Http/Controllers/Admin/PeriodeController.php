<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Periode;
use Illuminate\Http\Request;

class PeriodeController extends Controller
{
    public function index(Request $request)
    {
        $anneeActive = AnneeScolaire::active();
        if (! $anneeActive) {
            return redirect()->back()->with('error', 'Aucune année active trouvée.');
        }

        $periodes = Periode::where('annee_scolaire_id', $anneeActive->id)
            ->orderBy('date_debut')
            ->get();

        return view('back.pages.periodes.index', compact('periodes', 'anneeActive'));
    }

    public function create()
    {
        $annees = AnneeScolaire::all();

        return view('back.pages.periodes.create', compact('annees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
            'nom' => 'required|string|max:50',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        // Vérifier unicité du nom pour cette année
        $exists = Periode::where('annee_scolaire_id', $request->annee_scolaire_id)
            ->where('nom', $request->nom)
            ->exists();
        if ($exists) {
            return back()->withErrors(['nom' => 'Une période avec ce nom existe déjà pour cette année.'])->withInput();
        }

        Periode::create($request->all());

        return redirect()->route('admin.periodes.index')
            ->with('success', 'Période créée avec succès.');
    }

    public function edit(Periode $periode)
    {
        $annees = AnneeScolaire::all();

        return view('back.pages.periodes.edit', compact('periode', 'annees'));
    }

    public function update(Request $request, Periode $periode)
    {
        $request->validate([
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
            'nom' => 'required|string|max:50',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        // Vérifier unicité en excluant cette période
        $exists = Periode::where('annee_scolaire_id', $request->annee_scolaire_id)
            ->where('nom', $request->nom)
            ->where('id', '!=', $periode->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['nom' => 'Une période avec ce nom existe déjà pour cette année.'])->withInput();
        }

        $periode->update($request->all());

        return redirect()->route('admin.periodes.index')
            ->with('success', 'Période mise à jour.');
    }

    public function destroy(Periode $periode)
    {
        // Vérifier si des notes sont associées ? À faire plus tard
        $periode->delete();

        return redirect()->route('admin.periodes.index')
            ->with('success', 'Période supprimée.');
    }
}
