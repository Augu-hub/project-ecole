<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Niveau;
use Illuminate\Http\Request;

class NiveauController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $niveaux = Niveau::orderBy('ordre')->get();

        return view('back.pages.niveaux.index', compact('niveaux'));
    }

    public function create()
    {
        return view('back.pages.niveaux.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:20|unique:niveaux',
            'ordre' => 'required|integer|min:0',
            'cycle' => 'nullable|in:maternelle,primaire,secondaire',
        ]);

        Niveau::create($validated);

        return redirect()->route('admin.niveaux.index')->with('success', 'Niveau créé avec succès.');
    }

    public function edit(Niveau $niveau)
    {
        return view('back.pages.niveaux.edit', compact('niveau'));
    }

    public function update(Request $request, Niveau $niveau)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:20|unique:niveaux,nom,'.$niveau->id,
            'ordre' => 'required|integer|min:0',
            'cycle' => 'nullable|in:maternelle,primaire,secondaire',
        ]);

        $niveau->update($validated);

        return redirect()->route('admin.niveaux.index')->with('success', 'Niveau modifié avec succès.');
    }

    public function destroy(Niveau $niveau)
    {
        $niveau->delete();

        return redirect()->route('admin.niveaux.index')->with('success', 'Niveau supprimé avec succès.');
    }
}
