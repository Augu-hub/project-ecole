<?php

namespace App\Support;

use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Routing\Route as RouteDeclaree;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Affichage du menu latéral selon le rôle et les réglages du compte.
 *
 * Source de vérité unique : le middleware « role: » des routes (CheckRole). Une entrée du menu
 * n'est affichée que si l'utilisateur a le droit d'ouvrir la route vers laquelle elle pointe :
 * le menu suit donc tout seul les droits déclarés dans routes/web.php.
 *
 * Un administrateur peut en plus fermer des rubriques du menu à un compte (Paramètres > Accès aux
 * menus) : la rubrique disparaît du menu de ce compte et CheckRole lui en refuse les pages.
 */
class AccesMenu
{
    /**
     * Rubriques du menu qu'on peut fermer à un compte : clé => [libellé du menu, routes de la rubrique].
     *
     * Les routes sont des motifs de noms (Str::is). Une route qui n'entre dans aucune rubrique
     * (tableau de bord, profil) ne peut pas être fermée.
     */
    public const RUBRIQUES = [
        'equipe' => ['Mon équipe', ['admin.equipe.*']],
        'personnel' => ['Personnel', ['admin.personnel.*']],
        'parents' => ['Parents', ['admin.parents.*']],
        'eleves' => ['Élèves', ['admin.eleves.*', 'admin.fichiers.*', 'admin.reinscriptions.*', 'admin.certificats.*']],
        'pedagogie' => ['Pédagogie', ['admin.niveaux.*', 'admin.classes.*', 'admin.classe-annees.*', 'admin.matieres.*', 'admin.periodes.*', 'admin.type-notes.*', 'admin.classe-matieres.*']],
        'notes' => ['Notes', ['admin.notes.*', 'admin.cahier-notes.*']],
        'finance' => ['Finance', ['admin.scolarites.*', 'admin.tranches.*', 'admin.frais-dossiers.*']],
        'cantine' => ['Cantine', ['admin.cantine.*']],
        'connexions' => ['Connexions (audit)', ['admin.connexion-logs.*']],
        'messagerie' => ['Messagerie', ['admin.messagerie.*']],
        'uniformes' => ['Uniformes', ['admin.uniformes.*']],
        'emploi-du-temps' => ['Emploi du temps', ['admin.creneaux-horaires.*', 'admin.emploi-du-temps.*', 'enseignant.emploi-du-temps']],
        'annees-scolaires' => ['Années scolaires', ['admin.annees-scolaires.*']],
        'communiques' => ['Communiqués', ['admin.communiques.*']],
        'acces-menus' => ['Accès aux menus', ['admin.acces-menus.*']],
    ];

    /**
     * L'utilisateur (par défaut celui connecté) peut-il ouvrir la route portant ce nom ?
     *
     * Même règle que CheckRole : compte actif et rôle présent dans la liste, sans passe-droit
     * (un admin absent de la liste est refusé), et rubrique non fermée à ce compte. Avec plusieurs
     * middlewares de rôle (groupe + route), tous doivent accepter. Une route sans middleware de rôle
     * est ouverte à tout utilisateur connecté ; une route inconnue n'est jamais affichée.
     */
    public static function autorise(string $nomRoute, ?User $user = null): bool
    {
        $user ??= Auth::user();
        $route = Route::getRoutes()->getByName($nomRoute);

        if (! $user || ! $route) {
            return false;
        }

        foreach (static::rolesExiges($route) as $roles) {
            if (! $user->actif || ! in_array($user->role, $roles)) {
                return false;
            }
        }

        return ! static::fermee($nomRoute, $user);
    }

    /**
     * Clé de la rubrique à laquelle appartient cette route, ou null si elle n'entre dans aucune.
     */
    public static function rubriqueDe(?string $nomRoute): ?string
    {
        if ($nomRoute === null) {
            return null;
        }

        foreach (self::RUBRIQUES as $cle => [, $motifs]) {
            if (Str::is($motifs, $nomRoute)) {
                return $cle;
            }
        }

        return null;
    }

    /**
     * Cette route fait-elle partie d'une rubrique fermée à ce compte ?
     *
     * Un administrateur garde toujours tout son menu : personne ne peut lui fermer l'application.
     */
    public static function fermee(?string $nomRoute, User $user): bool
    {
        $fermees = $user->menus_desactives;

        if ($user->role === 'admin' || empty($fermees)) {
            return false;
        }

        return in_array(static::rubriqueDe($nomRoute), $fermees, true);
    }

    /**
     * Rubriques que ce rôle ouvre : celles dont une route au moins accepte le rôle. Ce sont les
     * seules qu'on peut activer ou désactiver pour un compte de ce rôle.
     *
     * @return array<string, string> clé => libellé du menu, dans l'ordre du menu
     */
    public static function rubriquesDuRole(string $role): array
    {
        $ouvertes = [];

        foreach (Route::getRoutes() as $route) {
            $cle = static::rubriqueDe($route->getName());

            if ($cle === null || isset($ouvertes[$cle])) {
                continue;
            }

            $accepte = collect(static::rolesExiges($route))->every(fn (array $roles) => in_array($role, $roles));

            if ($accepte) {
                $ouvertes[$cle] = true;
            }
        }

        return array_map(
            fn (array $rubrique) => $rubrique[0],
            array_intersect_key(self::RUBRIQUES, $ouvertes),
        );
    }

    /**
     * Rôles acceptés par chacun des middlewares de rôle déclarés sur la route ou sur ses groupes.
     *
     * Seuls les fichiers de routes sont lus (pas un middleware qu'un contrôleur déclarerait
     * lui-même) : afficher le menu ne charge ainsi aucun contrôleur.
     *
     * @return list<list<string>>
     */
    protected static function rolesExiges(RouteDeclaree $route): array
    {
        $exigences = [];

        // Alias (« role ») et groupes sont résolus comme le fait le routeur avant d'exécuter la route
        foreach (Route::resolveMiddleware($route->middleware(), $route->excludedMiddleware()) as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$classe, $parametres] = array_pad(explode(':', $middleware, 2), 2, '');

            if ($classe === CheckRole::class) {
                $exigences[] = array_values(array_filter(array_map('trim', explode(',', $parametres))));
            }
        }

        return $exigences;
    }
}
