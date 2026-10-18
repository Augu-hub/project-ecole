<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Équipe du préfet : le préfet crée et gère lui-même les surveillants placés sous lui.
 * Un préfet ne voit et ne modifie que les membres de sa propre équipe.
 */
class EquipeController extends Controller
{
    const ROLE_MEMBRE = 'surveillant';

    // -----------------------------------------------------------------------
    // Index
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        $membres = $request->user()->equipe()
            ->where('role', self::ROLE_MEMBRE)
            ->orderBy('name')
            ->paginate(15);

        return view('back.pages.equipe.index', compact('membres'));
    }

    // -----------------------------------------------------------------------
    // Create / Store
    // -----------------------------------------------------------------------

    public function create()
    {
        return view('back.pages.equipe.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:150',
            'email' => 'required|email|unique:users,email',
        ]);

        $request->user()->equipe()->create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make('password123'),
            'role'     => self::ROLE_MEMBRE,
            'actif'    => true,
        ]);

        return redirect()->route('admin.equipe.index')
                         ->with('success', 'Surveillant ajouté à votre équipe.');
    }

    // -----------------------------------------------------------------------
    // Edit / Update
    // -----------------------------------------------------------------------

    public function edit(Request $request, User $membre)
    {
        $this->authorizeMembre($request, $membre);

        return view('back.pages.equipe.edit', compact('membre'));
    }

    public function update(Request $request, User $membre)
    {
        $this->authorizeMembre($request, $membre);

        $validated = $request->validate([
            'name'  => 'required|string|max:150',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($membre->id)],
            'actif' => 'boolean',
        ]);

        $membre->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'actif' => $request->boolean('actif'),
        ]);

        return redirect()->route('admin.equipe.index')
                         ->with('success', 'Membre de l\'équipe mis à jour.');
    }

    // -----------------------------------------------------------------------
    // Destroy
    // -----------------------------------------------------------------------

    public function destroy(Request $request, User $membre)
    {
        $this->authorizeMembre($request, $membre);

        $membre->delete();

        return redirect()->route('admin.equipe.index')
                         ->with('success', 'Membre retiré de l\'équipe et compte supprimé.');
    }

    // -----------------------------------------------------------------------
    // Reset password
    // -----------------------------------------------------------------------

    public function resetPassword(Request $request, User $membre)
    {
        $this->authorizeMembre($request, $membre);

        $membre->update([
            'password' => Hash::make('password123'),
        ]);

        return back()->with('success', 'Mot de passe réinitialisé à « password123 ».');
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function authorizeMembre(Request $request, User $membre): void
    {
        abort_if(
            $membre->superieur_id !== $request->user()->id || $membre->role !== self::ROLE_MEMBRE,
            403,
            'Ce compte ne fait pas partie de votre équipe.'
        );
    }
}
