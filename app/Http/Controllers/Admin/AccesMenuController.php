<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccesMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Accès aux menus : l'administrateur ou le directeur active ou désactive, compte par compte, les
 * rubriques du menu latéral. Une rubrique désactivée disparaît du menu du compte et ses pages lui
 * sont refusées (voir App\Support\AccesMenu et le middleware CheckRole).
 *
 * On ne fait que retirer : un compte ne reçoit jamais une rubrique que son rôle n'ouvre pas.
 */
class AccesMenuController extends Controller
{
    /**
     * Rôles dont les comptes ne se règlent pas ici, avec la raison donnée à qui essaie.
     */
    const ROLES_NON_REGLABLES = [
        'admin' => 'Un administrateur garde toujours tous ses menus.',
        'parent' => 'Ce compte n\'utilise pas le menu de l\'administration.',
    ];

    /**
     * La colonne users.menus_desactives est ajoutée au site par une mise à jour de la base lancée
     * à la main : tant qu'elle n'y est pas, la liste reste consultable mais rien ne se règle.
     */
    const REGLAGE_INDISPONIBLE = 'Ce réglage sera disponible après la prochaine mise à jour de la base de données du site.';

    // -----------------------------------------------------------------------
    // Index
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        // Comptes du personnel, sauf le sien (les parents, très nombreux, n'utilisent pas ce menu)
        $comptes = User::whereNotIn('role', array_keys(self::ROLES_NON_REGLABLES))
            ->whereKeyNot($request->user()->id)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25);

        // Rubriques ouvertes par chaque rôle présent sur la page : calculées une fois par rôle, pas par compte
        $rubriquesParRole = $comptes->getCollection()->pluck('role')->unique()
            ->mapWithKeys(fn (string $role) => [$role => AccesMenu::rubriquesDuRole($role)]);

        $menus = $comptes->getCollection()->mapWithKeys(fn (User $compte) => [$compte->id => [
            'fermes' => count($this->fermees($compte, $rubriquesParRole[$compte->role])),
            'total' => count($rubriquesParRole[$compte->role]),
        ]]);

        return view('back.pages.acces-menus.index', [
            'comptes' => $comptes,
            'menus' => $menus,
            'avis' => $this->reglageDisponible() ? null : self::REGLAGE_INDISPONIBLE,
        ]);
    }

    // -----------------------------------------------------------------------
    // Edit / Update
    // -----------------------------------------------------------------------

    public function edit(Request $request, User $compte)
    {
        if ($refus = $this->refus($request, $compte)) {
            return $refus;
        }

        $rubriques = AccesMenu::rubriquesDuRole($compte->role);

        return view('back.pages.acces-menus.edit', [
            'compte' => $compte,
            'rubriques' => $rubriques,
            'fermees' => $this->fermees($compte, $rubriques),
        ]);
    }

    public function update(Request $request, User $compte)
    {
        if ($refus = $this->refus($request, $compte)) {
            return $refus;
        }

        // « menus » : les rubriques laissées activées
        $validated = $request->validate([
            'menus' => 'nullable|array',
            'menus.*' => 'string',
        ], [], [
            // Nom lisible dans le message d'erreur, à la place de « menus.0 »
            'menus.*' => 'menu',
        ]);

        // Sont fermées les rubriques du rôle qui n'ont pas été laissées activées. Une clé envoyée
        // qui n'est pas une rubrique du rôle est ignorée : elle ne donne aucun accès.
        $fermees = array_values(array_diff(
            array_keys(AccesMenu::rubriquesDuRole($compte->role)),
            $validated['menus'] ?? [],
        ));

        // Colonne hors de $fillable : elle ne s'écrit qu'ici
        $compte->forceFill(['menus_desactives' => $fermees ?: null])->save();

        $resultat = match (count($fermees)) {
            0 => 'tous activés',
            1 => '1 désactivé',
            default => count($fermees).' désactivés',
        };

        return redirect()->route('admin.acces-menus.index')
            ->with('success', "Menus de {$compte->name} : {$resultat}.");
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function reglageDisponible(): bool
    {
        return Schema::hasColumn('users', 'menus_desactives');
    }

    /**
     * Retour à la liste quand les menus de ce compte ne peuvent pas être réglés ; null quand ils
     * peuvent l'être.
     */
    private function refus(Request $request, User $compte): ?RedirectResponse
    {
        if (! $this->reglageDisponible()) {
            return redirect()->route('admin.acces-menus.index')
                ->with('info', self::REGLAGE_INDISPONIBLE);
        }

        $raison = self::ROLES_NON_REGLABLES[$compte->role]
            ?? ($compte->is($request->user()) ? 'Vous ne pouvez pas régler vos propres menus.' : null);

        return $raison === null
            ? null
            : redirect()->route('admin.acces-menus.index')->with('error', $raison);
    }

    /**
     * Rubriques du rôle fermées à ce compte. Une clé enregistrée qui n'est pas (ou plus) une
     * rubrique de son rôle ne compte pas.
     *
     * @param  array<string, string>  $rubriques  rubriques ouvertes par le rôle du compte
     * @return list<string>
     */
    private function fermees(User $compte, array $rubriques): array
    {
        return array_values(array_intersect(array_keys($rubriques), (array) $compte->menus_desactives));
    }
}
