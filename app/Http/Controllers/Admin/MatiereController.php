<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use Illuminate\Http\Request;

class MatiereController extends Controller
{
    /**
     * Affiche la liste des matières.
     */
    public function index()
    {
        $matieres = Matiere::orderBy('nom_matiere')->paginate(15);
        return view('back.pages.matieres.index', compact('matieres'));
    }

    /**
     * Affiche le formulaire de création.
     */
    public function create()
    {
        return view('back.pages.matieres.create');
    }

    /**
     * Enregistre une nouvelle matière.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom_matiere' => 'required|string|max:100|unique:matieres,nom_matiere',
            'description' => 'nullable|string',
        ]);

        Matiere::create($request->all());

        return redirect()->route('admin.matieres.index')
                         ->with('success', 'Matière créée avec succès.');
    }

    /**
     * Affiche le formulaire d'édition.
     */
    public function edit(Matiere $matiere)
    {
        return view('back.pages.matieres.edit', compact('matiere'));
    }

    /**
     * Met à jour la matière.
     */
    public function update(Request $request, Matiere $matiere)
    {
        $request->validate([
            'nom_matiere' => 'required|string|max:100|unique:matieres,nom_matiere,' . $matiere->id,
            'description' => 'nullable|string',
        ]);

        $matiere->update($request->all());

        return redirect()->route('admin.matieres.index')
                         ->with('success', 'Matière mise à jour.');
    }

    /**
     * Supprime une matière.
     */
    public function destroy(Matiere $matiere)
    {
        $matiere->delete();

        return redirect()->route('admin.matieres.index')
                         ->with('success', 'Matière supprimée.');
    }
}