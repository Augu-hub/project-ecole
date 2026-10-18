<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreneauHoraire;
use Illuminate\Http\Request;

class CreneauHoraireController extends Controller
{
    public function index()
    {
        $creneaux = CreneauHoraire::orderBy('ordre')->get();

        return view('back.pages.creneaux-horaires.index', compact('creneaux'));
    }

    public function create()
    {
        return view('back.pages.creneaux-horaires.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            'ordre' => 'required|integer|min:0',
        ]);

        CreneauHoraire::create($validated);

        return redirect()->route('admin.creneaux-horaires.index')->with('success', 'Créneau créé avec succès.');
    }

    public function edit(CreneauHoraire $creneauxHoraire)
    {
        return view('back.pages.creneaux-horaires.edit', ['creneau' => $creneauxHoraire]);
    }

    public function update(Request $request, CreneauHoraire $creneauxHoraire)
    {
        $validated = $request->validate([
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            'ordre' => 'required|integer|min:0',
        ]);

        $creneauxHoraire->update($validated);

        return redirect()->route('admin.creneaux-horaires.index')->with('success', 'Créneau mis à jour.');
    }

    public function destroy(CreneauHoraire $creneauxHoraire)
    {
        $creneauxHoraire->delete();

        return redirect()->route('admin.creneaux-horaires.index')->with('success', 'Créneau supprimé.');
    }
}
