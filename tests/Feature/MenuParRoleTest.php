<?php

use App\Http\Middleware\CheckRole;
use App\Support\AccesMenu;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

// Les fonctions communes aux tests du menu (membreMenu, xpathMenuDe, menuDe, liensDuMenuDe,
// neutraliserControleurs) sont dans tests/Pest.php.

/**
 * Rôles du personnel qui utilisent le menu latéral.
 */
function rolesDuMenu(): array
{
    return ['admin', 'directeur', 'censeur', 'econome', 'prefet', 'surveillant', 'saisisseur', 'enseignant'];
}

/**
 * Menu complet de l'administration : rubrique => entrées du sous-menu.
 * « Accès aux menus » n'est ouvert qu'à l'administrateur et au directeur.
 */
function menuComplet(bool $accesAuxMenus = true): array
{
    return [
        'Dashboard' => [],
        'Personnel' => ['Liste du personnel'],
        'Parents' => ['Liste des parents', 'Importer parents'],
        'Élèves' => ['Liste des élèves', 'Nouvelle inscription', 'Importer élèves', 'Réinscriptions', 'Certificats de scolarité'],
        // « Coefficients » est une entrée désactivée, suivie de son badge « Par classe »
        'Pédagogie' => ['Niveaux', 'Classes', 'Classes par année', 'Matières', 'Périodes', 'Types de notes', 'Coefficients Par classe'],
        'Notes' => ['Saisie des notes', 'Historique', 'Cahier de notes'],
        'Finance' => ['Scolarités & tranches', 'Frais de dossier'],
        'Cantine' => [],
        'Connexions (audit)' => [],
        'Messagerie' => [],
        'Uniformes' => [],
        'Emploi du temps' => ['Créneaux horaires', 'Par classe (secondaire)'],
        'Années scolaires' => [],
        'Paramètres' => $accesAuxMenus ? ['Communiqués', 'Accès aux menus', 'Mon profil'] : ['Communiqués', 'Mon profil'],
        'Se déconnecter' => [],
    ];
}

// ==================== INVARIANTS (valables quels que soient les droits déclarés dans les routes) ====================

test('chaque lien du menu mene a une page que le role a le droit d ouvrir', function (string $role) {
    $membre = membreMenu($role);
    $liens = liensDuMenuDe($membre);

    // 200 : la requête a franchi les middlewares ; 403 : rôle refusé ; 404 : lien mort
    neutraliserControleurs();
    $refuses = array_filter($liens, fn (string $lien) => $this->actingAs($membre)->get($lien)->status() !== 200);

    expect($liens)->not->toBeEmpty()
        ->and(array_values($refuses))->toBe([]);
})->with(rolesDuMenu());

test('aucune page ouverte au role n est cachee dans le menu', function (string $role) {
    // Tous les liens que le menu sait afficher, tous rôles confondus
    $tous = collect(rolesDuMenu())->flatMap(fn (string $autre) => liensDuMenuDe(membreMenu($autre)))->unique();

    $membre = membreMenu($role);
    $caches = $tous->diff(liensDuMenuDe($membre));

    neutraliserControleurs();
    $ouverts = $caches->filter(fn (string $lien) => $this->actingAs($membre)->get($lien)->status() !== 403);

    expect($ouverts->values()->all())->toBe([]);
})->with(rolesDuMenu());

test('un menu deroulant sans entree visible n apparait pas', function (string $role) {
    $vides = [];
    foreach (xpathMenuDe(membreMenu($role))->query('//ul[@id="sidebar-menu"]/li[contains(@class, "dropdown")][not(ul/li)]') as $deroulant) {
        $vides[] = trim($deroulant->textContent);
    }

    expect($vides)->toBe([]);
})->with(rolesDuMenu());

// ==================== MENU ATTENDU PAR RÔLE (droits actuels de routes/web.php) ====================

test('chaque role ne voit que les entrees auxquelles ses routes donnent acces', function (string $role, array $menu) {
    expect(menuDe(membreMenu($role)))->toBe($menu);
})->with([
    'admin : tout le menu' => ['admin', menuComplet()],
    'directeur : tout le menu' => ['directeur', menuComplet()],
    'censeur : tout sauf le journal des connexions et l acces aux menus' => ['censeur', Arr::except(menuComplet(accesAuxMenus: false), ['Connexions (audit)'])],
    'econome : ni personnel, ni journal des connexions, ni acces aux menus' => ['econome', Arr::except(menuComplet(accesAuxMenus: false), ['Personnel', 'Connexions (audit)'])],
    'prefet : son equipe, le cahier de notes, la messagerie, les creneaux et les communiques' => ['prefet', [
        'Dashboard' => [],
        'Mon équipe' => [],
        'Notes' => ['Cahier de notes'],
        'Messagerie' => [],
        'Emploi du temps' => ['Créneaux horaires'],
        'Paramètres' => ['Communiqués', 'Mon profil'],
        'Se déconnecter' => [],
    ]],
    'surveillant : comme le prefet, sans son equipe' => ['surveillant', [
        'Dashboard' => [],
        'Notes' => ['Cahier de notes'],
        'Messagerie' => [],
        'Emploi du temps' => ['Créneaux horaires'],
        'Paramètres' => ['Communiqués', 'Mon profil'],
        'Se déconnecter' => [],
    ]],
    'saisisseur : uniquement la saisie et l historique des notes' => ['saisisseur', [
        'Dashboard' => [],
        'Notes' => ['Saisie des notes', 'Historique'],
        'Paramètres' => ['Mon profil'],
        'Se déconnecter' => [],
    ]],
    'enseignant : menu reduit' => ['enseignant', [
        'Dashboard' => [],
        'Saisie des notes' => [],
        'Mon emploi du temps' => [],
        'Mon profil' => [],
        'Se déconnecter' => [],
    ]],
]);

test('le menu de l enseignant pointe vers son tableau de bord, la saisie, son emploi du temps et son profil', function () {
    expect(liensDuMenuDe(membreMenu('enseignant')))->toBe([
        route('enseignant.dashboard'),
        route('admin.notes.create'),
        route('enseignant.emploi-du-temps'),
        route('profile.edit'),
    ]);
});

test('sans aucun acces il reste le tableau de bord, le profil et la deconnexion', function (?string $role) {
    expect(menuDe($role ? membreMenu($role) : null))->toBe([
        'Dashboard' => [],
        'Paramètres' => ['Mon profil'],
        'Se déconnecter' => [],
    ]);
})->with([
    'role sans acces au back-office (parent)' => ['parent'],
    // Les pages d'erreur utilisent la même mise en page : le menu ne doit jamais y planter
    'personne de connecte' => [null],
]);

// ==================== RÈGLE D'ACCÈS (App\Support\AccesMenu) ====================

test('le menu applique la regle du middleware reellement branche sur role', function () {
    // AccesMenu reproduit la règle de CheckRole : si l'alias « role » change de classe, il faut le revoir
    expect(Route::getMiddleware()['role'])->toBe(CheckRole::class);
});

test('une route inconnue n est jamais affichee et ne provoque pas d erreur', function () {
    expect(AccesMenu::autorise('admin.page-qui-n-existe-pas', membreMenu('admin')))->toBeFalse();
});

test('une route sans middleware de role est ouverte a tout utilisateur connecte', function () {
    // Personne n'est encore connecté
    expect(AccesMenu::autorise('profile.edit'))->toBeFalse();

    $this->actingAs(membreMenu('saisisseur'));

    expect(AccesMenu::autorise('profile.edit'))->toBeTrue();
});

test('le role doit etre accepte par tous les middlewares de role, sans passe droit ni compte desactive', function () {
    Route::middleware(['web', 'auth', 'role:admin,censeur,prefet'])->group(function () {
        Route::get('essai-menu', fn () => 'ok')->middleware('role:prefet,surveillant')->name('essai.menu');
    });
    Route::getRoutes()->refreshNameLookups();

    foreach (['prefet' => true, 'admin' => false, 'censeur' => false, 'surveillant' => false] as $role => $attendu) {
        $membre = membreMenu($role);

        // Le menu doit dire exactement ce que fait le vrai middleware
        expect(AccesMenu::autorise('essai.menu', $membre))->toBe($attendu);
        $this->actingAs($membre)->get('essai-menu')->assertStatus($attendu ? 200 : 403);
    }

    $desactive = membreMenu('prefet', ['actif' => false]);

    expect(AccesMenu::autorise('essai.menu', $desactive))->toBeFalse();
    $this->actingAs($desactive)->get('essai-menu')->assertRedirect(route('login'));
});
