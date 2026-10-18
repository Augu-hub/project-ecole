<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PersonnelController extends Controller
{
    /**
     * Rôles gérés par ce controller.
     * Admin et Enseignant ont leurs propres CRUDs.
     */
    const ROLES_PERSONNEL = [
        'directeur'  => 'Directeur',
        'censeur'    => 'Censeur',
        'econome'    => 'Économe',
        'prefet'      => 'Préfet',
        'surveillant' => 'Surveillant',
        'saisisseur'  => 'Saisisseur de note',
    ];

    /**
     * Couleurs badge par rôle (utilisées dans les vues).
     */
    const ROLE_COLORS = [
        'directeur'  => ['bg' => 'bg-primary-100',  'text' => 'text-primary-600'],
        'censeur'    => ['bg' => 'bg-purple-100',    'text' => 'text-purple-600'],
        'econome'    => ['bg' => 'bg-warning-100',   'text' => 'text-warning-600'],
        'prefet'      => ['bg' => 'bg-success-100',   'text' => 'text-success-600'],
        'surveillant' => ['bg' => 'bg-neutral-200',   'text' => 'text-secondary-light'],
        'saisisseur'  => ['bg' => 'bg-info-100',      'text' => 'text-info-600'],
    ];

    // -----------------------------------------------------------------------
    // Index
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        $query = User::whereIn('role', array_keys(self::ROLES_PERSONNEL))
                     ->with('superieur')
                     ->orderBy('name');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name',  'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $personnel = $query->paginate(15)->withQueryString();

        return view('back.pages.personnel.index', [
            'personnel'      => $personnel,
            'rolesPersonnel' => self::ROLES_PERSONNEL,
            'roleColors'     => self::ROLE_COLORS,
        ]);
    }

    // -----------------------------------------------------------------------
    // Create / Store
    // -----------------------------------------------------------------------

    public function create()
    {
        return view('back.pages.personnel.create', [
            'rolesPersonnel' => self::ROLES_PERSONNEL,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:150',
            'email' => 'required|email|unique:users,email',
            'role'  => ['required', Rule::in(array_keys(self::ROLES_PERSONNEL))],
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make('password123'),
            'role'     => $validated['role'],
            'actif'    => true,
        ]);

        return redirect()->route('admin.personnel.index')
                         ->with('success', 'Membre du personnel créé avec succès.');
    }

    // -----------------------------------------------------------------------
    // Edit / Update
    // -----------------------------------------------------------------------

    public function edit(User $personnel)
    {
        $this->authorizeRole($personnel);

        return view('back.pages.personnel.edit', [
            'membre'         => $personnel,
            'rolesPersonnel' => self::ROLES_PERSONNEL,
        ]);
    }

    public function update(Request $request, User $personnel)
    {
        $this->authorizeRole($personnel);

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($personnel->id)],
            'role'  => ['required', Rule::in(array_keys(self::ROLES_PERSONNEL))],
            'actif' => 'boolean',
        ]);

        $personnel->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'role'  => $validated['role'],
            'actif' => $request->boolean('actif'),
            // Un membre qui n'est plus surveillant quitte l'équipe de son préfet
            'superieur_id' => $validated['role'] === 'surveillant' ? $personnel->superieur_id : null,
        ]);

        return redirect()->route('admin.personnel.index')
                         ->with('success', 'Membre du personnel mis à jour.');
    }

    // -----------------------------------------------------------------------
    // Destroy
    // -----------------------------------------------------------------------

    public function destroy(User $personnel)
    {
        $this->authorizeRole($personnel);

        $personnel->delete();

        return redirect()->route('admin.personnel.index')
                         ->with('success', 'Membre du personnel supprimé.');
    }

    // -----------------------------------------------------------------------
    // Reset password
    // -----------------------------------------------------------------------

    public function resetPassword(User $personnel)
    {
        $this->authorizeRole($personnel);

        $personnel->update([
            'password' => Hash::make('password123'),
        ]);

        return back()->with('success', 'Mot de passe réinitialisé à « password123 ».');
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function authorizeRole(User $user): void
    {
        abort_if(
            ! in_array($user->role, array_keys(self::ROLES_PERSONNEL)),
            403,
            'Ce compte n\'est pas géré ici.'
        );
    }
}
