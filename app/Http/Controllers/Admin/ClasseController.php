<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Niveau;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    public function index()
    {
        $classes = Classe::with('niveau')->orderBy('niveau_id')->orderBy('suffixe')->get();
        return view('back.pages.classes.index', compact('classes'));
    }

    public function create()
    {
        $niveaux = Niveau::orderBy('ordre')->get();
        return view('back.pages.classes.create', compact('niveaux'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'niveau_id' => 'required|exists:niveaux,id',
            'suffixe'   => 'nullable|string|max:10',
        ]);

        // Vérifier l'unicité (niveau_id + suffixe)
        $exists = Classe::where('niveau_id', $validated['niveau_id'])
                        ->where('suffixe', $validated['suffixe'] ?? '')
                        ->exists();
        if ($exists) {
            return back()->withErrors(['suffixe' => 'Cette combinaison niveau + suffixe existe déjà.'])->withInput();
        }

        Classe::create([
            'niveau_id' => $validated['niveau_id'],
            'suffixe'   => $validated['suffixe'] ?? '',
        ]);

        return redirect()->route('admin.classes.index')->with('success', 'Classe créée avec succès.');
    }

    public function edit(Classe $classe)
    {
        $niveaux = Niveau::orderBy('ordre')->get();
        return view('back.classes.edit', compact('classe', 'niveaux'));
    }

    public function update(Request $request, Classe $classe)
    {
        $validated = $request->validate([
            'niveau_id' => 'required|exists:niveaux,id',
            'suffixe'   => 'nullable|string|max:10',
        ]);

        // Vérifier l'unicité en ignorant l'enregistrement courant
        $exists = Classe::where('niveau_id', $validated['niveau_id'])
                        ->where('suffixe', $validated['suffixe'] ?? '')
                        ->where('id', '!=', $classe->id)
                        ->exists();
        if ($exists) {
            return back()->withErrors(['suffixe' => 'Cette combinaison niveau + suffixe existe déjà.'])->withInput();
        }

        $classe->update([
            'niveau_id' => $validated['niveau_id'],
            'suffixe'   => $validated['suffixe'] ?? '',
        ]);

        return redirect()->route('admin.classes.index')->with('success', 'Classe modifiée avec succès.');
    }

    public function destroy(Classe $classe)
    {
        $classe->delete();
        return redirect()->route('admin.classes.index')->with('success', 'Classe supprimée avec succès.');
    }
}