<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TypeNote;
use Illuminate\Http\Request;

class TypeNoteController extends Controller
{
    public function index()
    {
        $types = TypeNote::ordonnes()->paginate(15);
        return view('back.pages.type-notes.index', compact('types'));
    }

    public function create()
    {
        return view('back.pages.type-notes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:50|unique:type_notes,nom',
            'code' => 'required|string|max:20|unique:type_notes,code',
        ]);

        TypeNote::create($request->all());

        return redirect()->route('admin.type-notes.index')
                         ->with('success', 'Type de note créé avec succès.');
    }

    public function edit(TypeNote $typeNote)
    {
        return view('back.pages.type-notes.edit', compact('typeNote'));
    }

    public function update(Request $request, TypeNote $typeNote)
    {
        $request->validate([
            'nom' => 'required|string|max:50|unique:type_notes,nom,' . $typeNote->id,
            'code' => 'required|string|max:20|unique:type_notes,code,' . $typeNote->id,
        ]);

        $typeNote->update($request->all());

        return redirect()->route('admin.type-notes.index')
                         ->with('success', 'Type de note mis à jour.');
    }

    public function destroy(TypeNote $typeNote)
    {
        // Vérifier si des notes existent ?
        $typeNote->delete();
        return redirect()->route('admin.type-notes.index')
                         ->with('success', 'Type de note supprimé.');
    }
}