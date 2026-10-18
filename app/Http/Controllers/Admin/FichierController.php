<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Fichier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FichierController extends Controller
{
    public function store(Request $request, Eleve $eleve)
    {
        $request->validate([
            'fichier' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
            'libelle' => 'nullable|string|max:150',
        ]);

        $file = $request->file('fichier');
        $path = $file->store('eleves/'.$eleve->id, 'documents_eleves');

        Fichier::create([
            'nom_fichier' => $file->getClientOriginalName(),
            'chemin_fichier' => $path,
            'type_fichier' => $file->getClientOriginalExtension(),
            'taille' => $file->getSize(),
            'user_id' => $request->user()->id,
            'eleve_id' => $eleve->id,
            'libelle' => $request->input('libelle'),
        ]);

        return back()->with('success', 'Document ajouté.');
    }

    public function destroy(Fichier $fichier)
    {
        Storage::disk('documents_eleves')->delete($fichier->chemin_fichier);
        $fichier->delete();

        return back()->with('success', 'Document supprimé.');
    }

    public function download(Fichier $fichier)
    {
        return Storage::disk('documents_eleves')->download($fichier->chemin_fichier, $fichier->nom_fichier);
    }
}
