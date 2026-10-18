<?php

use App\Http\Middleware\CheckRole;
use App\Models\User;
use App\Support\AccesMenu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// Les fonctions communes aux tests du menu (membreMenu, xpathMenuDe, menuDe, liensDuMenuDe,
// neutraliserControleurs) sont dans tests/Pest.php.

/**
 * Toutes les rubriques réglables sauf celles données, dans l'ordre du menu.
 */
function rubriquesSauf(string ...$exclues): array
{
    return array_values(array_diff(array_keys(AccesMenu::RUBRIQUES), $exclues));
}

/**
 * Envoie le formulaire de réglage d'un compte : $activees sont les rubriques laissées cochées.
 */
function reglerMenus(User $par, User $compte, array $activees): TestResponse
{
    return test()->actingAs($par)->put(route('admin.acces-menus.update', $compte), ['menus' => $activees]);
}

function xpathDeLaPage(TestResponse $reponse): DOMXPath
{
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="UTF-8">'.$reponse->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($dom);
}

/**
 * Lignes du tableau de la liste des comptes, telles qu'elles sont affichées : le texte de chaque cellule.
 */
function lignesDeLaListe(TestResponse $reponse): array
{
    $xpath = xpathDeLaPage($reponse);

    $lignes = [];
    foreach ($xpath->query('//div[contains(@class, "dashboard-main-body")]//table/tbody/tr') as $ligne) {
        $cellules = [];
        foreach ($xpath->query('td', $ligne) as $cellule) {
            $cellules[] = trim(preg_replace('/\s+/u', ' ', $cellule->textContent));
        }

        $lignes[] = $cellules;
    }

    return $lignes;
}

/**
 * Interrupteurs de la page de réglage : clé de la rubrique => [libellé affiché, coché ou non].
 */
function interrupteurs(TestResponse $reponse): array
{
    $xpath = xpathDeLaPage($reponse);

    $interrupteurs = [];
    foreach ($xpath->query('//input[@type="checkbox"][@name="menus[]"]') as $case) {
        $libelle = $xpath->query('//label[@for="'.$case->getAttribute('id').'"]')->item(0);

        $interrupteurs[$case->getAttribute('value')] = [trim($libelle->textContent), $case->hasAttribute('checked')];
    }

    return $interrupteurs;
}

// ==================== RUBRIQUES RÉGLABLES (App\Support\AccesMenu) ====================

test('un role n a a regler que les rubriques que ses routes lui ouvrent', function (string $role, array $rubriques) {
    expect(array_keys(AccesMenu::rubriquesDuRole($role)))->toBe($rubriques);
})->with([
    'directeur : tout sauf l equipe du prefet' => ['directeur', rubriquesSauf('equipe')],
    'censeur : ni journal des connexions ni acces aux menus' => ['censeur', rubriquesSauf('equipe', 'connexions', 'acces-menus')],
    'econome : comme le censeur, sans le personnel' => ['econome', rubriquesSauf('equipe', 'personnel', 'connexions', 'acces-menus')],
    'prefet' => ['prefet', ['equipe', 'notes', 'messagerie', 'emploi-du-temps', 'communiques']],
    'surveillant : comme le prefet, sans son equipe' => ['surveillant', ['notes', 'messagerie', 'emploi-du-temps', 'communiques']],
    'saisisseur' => ['saisisseur', ['notes']],
    'enseignant' => ['enseignant', ['notes', 'emploi-du-temps']],
    'parent : aucune' => ['parent', []],
]);

test('les rubriques d un role portent le libelle du menu', function () {
    expect(AccesMenu::rubriquesDuRole('enseignant'))->toBe(['notes' => 'Notes', 'emploi-du-temps' => 'Emploi du temps']);
});

test('toute page reservee a des roles fait partie d une rubrique, sauf les tableaux de bord', function () {
    $horsRubrique = [];
    $sansNom = [];

    foreach (Route::getRoutes() as $route) {
        // Middlewares résolus comme le fait le routeur : « role:... » devient la classe CheckRole
        $reserveeADesRoles = collect(Route::resolveMiddleware($route->middleware(), $route->excludedMiddleware()))
            ->contains(fn ($middleware) => is_string($middleware) && Str::before($middleware, ':') === CheckRole::class);

        if (! in_array('web', $route->middleware(), true) || ! $reserveeADesRoles) {
            continue;
        }

        if ($route->getName() === null) {
            $sansNom[] = $route->uri();
        } elseif (AccesMenu::rubriqueDe($route->getName()) === null) {
            $horsRubrique[] = $route->getName();
        }
    }

    sort($horsRubrique);

    // Une nouvelle route doit entrer dans une rubrique (AccesMenu::RUBRIQUES) : sinon elle resterait
    // ouverte quel que soit le réglage du compte. Sans nom, elle ne peut entrer dans aucune.
    expect($horsRubrique)->toBe(['admin.dashboard', 'enseignant.dashboard'])
        ->and($sansNom)->toBe([]);
});

// ==================== FERMER ET ROUVRIR UNE RUBRIQUE ====================

test('fermer la finance a un censeur la retire de son menu et lui en refuse les pages', function () {
    $admin = membreMenu('admin');
    $censeur = membreMenu('censeur', ['name' => 'Rose AKPO']);
    $temoin = membreMenu('censeur');

    // L'administrateur laisse tout coché sauf « Finance »
    reglerMenus($admin, $censeur, rubriquesSauf('equipe', 'connexions', 'acces-menus', 'finance'))
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('success', 'Menus de Rose AKPO : 1 désactivé.');

    $censeur->refresh();

    expect($censeur->menus_desactives)->toBe(['finance'])
        ->and(menuDe($temoin))->toHaveKey('Finance')
        ->and(menuDe($censeur))->toBe(Arr::except(menuDe($temoin), ['Finance']));

    // Le tableau de bord s'ouvre toujours, sans ses blocs de finances
    $this->actingAs($temoin)->get(route('admin.dashboard'))->assertOk()->assertSee('Encaissements');
    $this->actingAs($censeur)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Encaissements');

    // Les pages de la rubrique sont refusées, même par leur adresse directe
    $this->actingAs($censeur)->get('/admin/scolarites')
        ->assertForbidden()
        ->assertSee('Ce menu n\'est pas activé pour votre compte.');

    $this->actingAs($censeur)->getJson('/admin/scolarites')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Ce menu n\'est pas activé pour votre compte.']);

    $this->actingAs($censeur)->get('/admin/frais-dossiers')->assertForbidden();

    // Seuls les middlewares comptent pour la suite : les contrôleurs sont neutralisés
    neutraliserControleurs();

    $this->actingAs($censeur)->get('/admin/scolarites/1/tranches')->assertForbidden();

    // Les autres rubriques et le tableau de bord restent ouverts ; l'autre censeur garde la finance
    $this->actingAs($censeur)->get('/admin/eleves')->assertOk();
    $this->actingAs($censeur)->get('/admin/dashboard')->assertOk();
    $this->actingAs($temoin)->get('/admin/scolarites')->assertOk();
});

test('tout reactiver vide le reglage et la rubrique revient', function () {
    $admin = membreMenu('admin');
    $censeur = membreMenu('censeur', ['name' => 'Rose AKPO', 'menus_desactives' => ['finance', 'cantine']]);

    expect(menuDe($censeur))->not->toHaveKey('Finance')
        ->and(menuDe($censeur))->not->toHaveKey('Cantine');

    reglerMenus($admin, $censeur, array_keys(AccesMenu::rubriquesDuRole('censeur')))
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('success', 'Menus de Rose AKPO : tous activés.');

    // Plus rien de fermé : la colonne est vide (null), pas une liste vide
    expect(DB::table('users')->where('id', $censeur->id)->value('menus_desactives'))->toBeNull();

    $censeur->refresh();

    expect($censeur->menus_desactives)->toBeNull()
        ->and(menuDe($censeur))->toBe(menuDe(membreMenu('censeur')));

    neutraliserControleurs();

    $this->actingAs($censeur)->get('/admin/scolarites')->assertOk();
    $this->actingAs($censeur)->get('/admin/cantine/tarifs')->assertOk();
});

test('une rubrique que le role n ouvre pas est ignoree et ne donne aucun acces', function () {
    $admin = membreMenu('admin');
    $saisisseur = membreMenu('saisisseur', ['name' => 'Luc DOSSA']);

    // « finance » n'est pas une rubrique du saisisseur : rien n'est enregistré pour elle.
    // « notes », sa seule rubrique, n'est pas cochée : c'est elle qui est fermée.
    reglerMenus($admin, $saisisseur, ['finance'])
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('success', 'Menus de Luc DOSSA : 1 désactivé.');

    $saisisseur->refresh();

    expect($saisisseur->menus_desactives)->toBe(['notes'])
        ->and(menuDe($saisisseur))->not->toHaveKey('Finance');
    $this->actingAs($saisisseur)->get('/admin/scolarites')->assertForbidden();

    // Même cochée avec sa vraie rubrique, une clé étrangère ou inconnue ne laisse aucune trace
    reglerMenus($admin, $saisisseur, ['notes', 'finance', 'rubrique-inconnue'])
        ->assertSessionHas('success', 'Menus de Luc DOSSA : tous activés.');

    $saisisseur->refresh();

    expect($saisisseur->menus_desactives)->toBeNull()
        ->and(menuDe($saisisseur))->toBe(menuDe(membreMenu('saisisseur')));

    neutraliserControleurs();

    $this->actingAs($saisisseur)->get('/admin/scolarites')
        ->assertForbidden()
        ->assertSee('Vous n\'avez pas accès à cette section.');
    $this->actingAs($saisisseur)->get('/admin/notes')->assertOk();
});

test('un envoi mal forme est refuse sans rien enregistrer', function (mixed $menus, array $erreur) {
    $admin = membreMenu('admin');
    $censeur = membreMenu('censeur', ['menus_desactives' => ['finance']]);

    $this->actingAs($admin)
        ->from(route('admin.acces-menus.edit', $censeur))
        ->put(route('admin.acces-menus.update', $censeur), ['menus' => $menus])
        ->assertRedirect(route('admin.acces-menus.edit', $censeur))
        ->assertSessionHasErrors($erreur);

    expect($censeur->fresh()->menus_desactives)->toBe(['finance']);
})->with([
    'un texte au lieu d une liste' => ['finance', ['menus' => 'Le champ menus doit être une liste.']],
    'une liste dans la liste' => [[['finance']], ['menus.0' => 'Le champ menu doit être un texte.']],
]);

// ==================== COMPTES QUI NE SE RÈGLENT PAS ====================

test('les menus d un administrateur, d un parent ou de son propre compte ne se reglent pas', function (string $role, string $cible, string $raison) {
    $connecte = membreMenu($role, ['menus_desactives' => ['cantine']]);
    $compte = $cible === 'soi' ? $connecte : membreMenu($cible, ['menus_desactives' => ['cantine']]);

    // La page de réglage ne s'ouvre pas : retour à la liste, avec la raison
    $this->actingAs($connecte)->get(route('admin.acces-menus.edit', $compte))
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('error', $raison);

    $this->actingAs($connecte)->followingRedirects()->get(route('admin.acces-menus.edit', $compte))
        ->assertOk()
        ->assertSee($raison);

    // L'envoi direct du formulaire ne change rien non plus
    reglerMenus($connecte, $compte, [])
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('error', $raison);

    expect($compte->fresh()->menus_desactives)->toBe(['cantine']);
})->with([
    'un administrateur' => ['directeur', 'admin', 'Un administrateur garde toujours tous ses menus.'],
    'un parent' => ['directeur', 'parent', 'Ce compte n\'utilise pas le menu de l\'administration.'],
    'son propre compte' => ['directeur', 'soi', 'Vous ne pouvez pas régler vos propres menus.'],
    'son propre compte d administrateur' => ['admin', 'soi', 'Un administrateur garde toujours tous ses menus.'],
]);

test('un administrateur garde tout son menu meme si un reglage lui est impose en base', function () {
    $admin = membreMenu('admin');

    DB::table('users')->where('id', $admin->id)->update(['menus_desactives' => json_encode(['finance'])]);
    $admin->refresh();

    expect($admin->menus_desactives)->toBe(['finance'])
        ->and(menuDe($admin))->toHaveKey('Finance')
        ->and(menuDe($admin))->toBe(menuDe(membreMenu('admin')));

    $liens = liensDuMenuDe($admin);

    neutraliserControleurs();

    $refuses = array_filter($liens, fn (string $lien) => $this->actingAs($admin)->get($lien)->status() !== 200);

    expect($liens)->toContain(route('admin.scolarites.index'))
        ->and(array_values($refuses))->toBe([]);
});

// ==================== QUI RÈGLE LES MENUS ====================

test('les pages du reglage sont reservees a l administrateur et au directeur', function () {
    $compte = membreMenu('surveillant');

    foreach (['admin', 'directeur'] as $role) {
        $membre = membreMenu($role);

        $this->actingAs($membre)->get(route('admin.acces-menus.index'))->assertOk();
        $this->actingAs($membre)->get(route('admin.acces-menus.edit', $compte))->assertOk();
    }

    foreach (['censeur', 'econome', 'prefet', 'surveillant', 'saisisseur', 'enseignant', 'parent'] as $role) {
        $membre = membreMenu($role);

        $this->actingAs($membre)->get(route('admin.acces-menus.index'))->assertForbidden();
        $this->actingAs($membre)->get(route('admin.acces-menus.edit', $compte))->assertForbidden();
        reglerMenus($membre, $compte, [])->assertForbidden();
    }

    expect($compte->fresh()->menus_desactives)->toBeNull();
});

test('un directeur a qui l acces aux menus est ferme ne regle plus rien et n en voit plus l entree', function () {
    $directeur = membreMenu('directeur');
    $collegue = membreMenu('directeur', ['name' => 'Jean HOUNDJO']);
    $compte = membreMenu('surveillant');

    expect(menuDe($collegue)['Paramètres'])->toBe(['Communiqués', 'Accès aux menus', 'Mon profil']);

    // Un directeur règle les menus d'un autre directeur
    reglerMenus($directeur, $collegue, rubriquesSauf('equipe', 'acces-menus'))
        ->assertSessionHas('success', 'Menus de Jean HOUNDJO : 1 désactivé.');

    $collegue->refresh();

    expect($collegue->menus_desactives)->toBe(['acces-menus'])
        ->and(menuDe($collegue)['Paramètres'])->toBe(['Communiqués', 'Mon profil']);

    $this->actingAs($collegue)->get(route('admin.acces-menus.index'))
        ->assertForbidden()
        ->assertSee('Ce menu n\'est pas activé pour votre compte.');
    $this->actingAs($collegue)->get(route('admin.acces-menus.edit', $compte))->assertForbidden();
    reglerMenus($collegue, $compte, [])->assertForbidden();

    expect($compte->fresh()->menus_desactives)->toBeNull();
});

// ==================== LISTE DES COMPTES ====================

test('la liste montre les comptes du personnel, sans les parents, les administrateurs ni son propre compte', function () {
    $admin = membreMenu('admin', ['name' => 'Zoe ADMIN']);
    membreMenu('admin', ['name' => 'Autre ADMINISTRATEUR']);
    membreMenu('parent', ['name' => 'Parent DELEVE']);

    membreMenu('enseignant', ['name' => 'Dossou ENSEIGNANT', 'email' => 'dossou@ecole.bj']);
    membreMenu('saisisseur', ['name' => 'Codjo SAISISSEUR', 'email' => 'codjo@ecole.bj', 'actif' => false, 'menus_desactives' => ['notes']]);
    $directeur = membreMenu('directeur', ['name' => 'Bio DIRECTEUR', 'email' => 'bio@ecole.bj']);
    // « equipe » n'est pas une rubrique du censeur : elle ne compte pas parmi ses menus désactivés
    $censeur = membreMenu('censeur', ['name' => 'Awa CENSEUR', 'email' => 'awa@ecole.bj', 'menus_desactives' => ['finance', 'equipe', 'cantine']]);

    $reponse = $this->actingAs($admin)->get(route('admin.acces-menus.index'))->assertOk();

    // Par ordre alphabétique : nom, adresse e-mail, rôle, état des menus, bouton
    expect(lignesDeLaListe($reponse))->toBe([
        ['Awa CENSEUR awa@ecole.bj', 'Censeur', '2 désactivés sur '.count(AccesMenu::rubriquesDuRole('censeur')), 'Régler'],
        ['Bio DIRECTEUR bio@ecole.bj', 'Directeur', 'Tous activés', 'Régler'],
        ['Codjo SAISISSEUR Inactif codjo@ecole.bj', 'Saisisseur de note', '1 désactivé sur 1', 'Régler'],
        ['Dossou ENSEIGNANT dossou@ecole.bj', 'Enseignant', 'Tous activés', 'Régler'],
    ]);

    $reponse->assertSee('href="'.route('admin.acces-menus.edit', $censeur).'"', false);

    // Le directeur voit la même liste, sans son propre compte
    $vuParLeDirecteur = lignesDeLaListe($this->actingAs($directeur)->get(route('admin.acces-menus.index'))->assertOk());

    expect(array_column($vuParLeDirecteur, 0))->toBe([
        'Awa CENSEUR awa@ecole.bj',
        'Codjo SAISISSEUR Inactif codjo@ecole.bj',
        'Dossou ENSEIGNANT dossou@ecole.bj',
    ]);

    // Dans le menu latéral, « Paramètres » est déplié sur l'entrée « Accès aux menus »
    $xpath = xpathDeLaPage($reponse);
    $deplies = $xpath->query('//ul[@id="sidebar-menu"]/li[contains(concat(" ", normalize-space(@class), " "), " open ")]');

    expect($deplies->length)->toBe(1)
        ->and(trim($xpath->query('a[@aria-expanded="true"]/span', $deplies->item(0))->item(0)?->textContent))->toBe('Paramètres')
        ->and(trim($xpath->query('ul/li/a[@aria-current="page"]', $deplies->item(0))->item(0)?->textContent))->toBe('Accès aux menus');
});

test('sans autre compte du personnel la liste le dit', function () {
    membreMenu('parent');

    $this->actingAs(membreMenu('admin'))->get(route('admin.acces-menus.index'))
        ->assertOk()
        ->assertSee('Aucun compte du personnel.');
});

test('la liste est paginee par 25 comptes', function () {
    $admin = membreMenu('admin');
    User::factory()->count(27)->create(['role' => 'surveillant', 'actif' => true]);

    $premiere = $this->actingAs($admin)->get(route('admin.acces-menus.index'))->assertOk();

    expect(lignesDeLaListe($premiere))->toHaveCount(25)
        ->and($premiere->viewData('comptes')->total())->toBe(27);

    // Liens de pagination en français, sans la phrase anglaise de la pagination par défaut
    $premiere->assertSee('href="'.route('admin.acces-menus.index', ['page' => 2]).'"', false)
        ->assertSee('Suivant')
        ->assertDontSee('Showing');

    $deuxieme = $this->actingAs($admin)->get(route('admin.acces-menus.index', ['page' => 2]))->assertOk();

    expect(lignesDeLaListe($deuxieme))->toHaveCount(2);
    $deuxieme->assertSee('href="'.route('admin.acces-menus.index', ['page' => 1]).'"', false)
        ->assertSee('Précédent');
});

// ==================== PAGE DE RÉGLAGE D'UN COMPTE ====================

test('la page de reglage propose un interrupteur par rubrique du role, coche sauf si la rubrique est fermee', function () {
    $directeur = membreMenu('directeur');
    // « finance » : reste d'un ancien rôle du compte, ce n'est pas une rubrique du surveillant
    $surveillant = membreMenu('surveillant', ['name' => 'Koffi ZINSOU', 'menus_desactives' => ['messagerie', 'finance']]);

    $reponse = $this->actingAs($directeur)->get(route('admin.acces-menus.edit', $surveillant))
        ->assertOk()
        ->assertSee('Koffi ZINSOU — Surveillant')
        ->assertSee('Décochez les menus que ce compte ne doit pas utiliser.')
        ->assertSee('action="'.route('admin.acces-menus.update', $surveillant).'"', false)
        ->assertSee('name="_method" value="PUT"', false)
        ->assertSee('Enregistrer')
        ->assertSee('Annuler');

    expect(interrupteurs($reponse))->toBe([
        'notes' => ['Notes', true],
        'messagerie' => ['Messagerie', false],
        'emploi-du-temps' => ['Emploi du temps', true],
        'communiques' => ['Communiqués', true],
    ]);

    // Le directeur décoche aussi « Communiqués » : la liste enregistrée est refaite à partir des rubriques du rôle
    reglerMenus($directeur, $surveillant, ['notes', 'emploi-du-temps'])
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('success', 'Menus de Koffi ZINSOU : 2 désactivés.');

    expect($surveillant->fresh()->menus_desactives)->toBe(['messagerie', 'communiques']);
});

// ==================== MENU LATÉRAL ====================

test('fermer l emploi du temps retire tout son menu deroulant et laisse la pedagogie', function () {
    $censeur = membreMenu('censeur', ['menus_desactives' => ['emploi-du-temps']]);
    $menuComplet = menuDe(membreMenu('censeur'));
    $menu = menuDe($censeur);

    expect($menuComplet['Emploi du temps'])->toBe(['Créneaux horaires', 'Par classe (secondaire)'])
        ->and($menu)->toBe(Arr::except($menuComplet, ['Emploi du temps']))
        ->and($menu['Pédagogie'])->toContain('Classes par année');

    neutraliserControleurs();

    foreach (['/admin/creneaux-horaires', '/admin/classe-annees/1/emploi-du-temps', '/admin/enseignants/1/emploi-du-temps'] as $page) {
        $this->actingAs($censeur)->get($page)->assertForbidden();
    }

    // La liste des classes de l'année, page de la pédagogie, reste ouverte
    $this->actingAs($censeur)->get('/admin/classe-annees')->assertOk();
});

test('fermer la pedagogie retire aussi l entree par classe de l emploi du temps', function () {
    $menu = menuDe(membreMenu('censeur', ['menus_desactives' => ['pedagogie']]));

    // « Par classe » passe par la liste des classes de l'année, qui est une page de la pédagogie
    expect($menu)->not->toHaveKey('Pédagogie')
        ->and($menu['Emploi du temps'])->toBe(['Créneaux horaires']);
});

test('le reglage vaut aussi pour le menu reduit de l enseignant', function () {
    $enseignant = membreMenu('enseignant', ['menus_desactives' => ['emploi-du-temps']]);

    expect(menuDe($enseignant))->toBe([
        'Dashboard' => [],
        'Saisie des notes' => [],
        'Mon profil' => [],
        'Se déconnecter' => [],
    ]);

    neutraliserControleurs();

    $this->actingAs($enseignant)->get('/enseignant/emploi-du-temps')
        ->assertForbidden()
        ->assertSee('Ce menu n\'est pas activé pour votre compte.');
    $this->actingAs($enseignant)->get('/enseignant/dashboard')->assertOk();
    $this->actingAs($enseignant)->get('/admin/notes/create')->assertOk();
});

test('quelle que soit la rubrique fermee, le menu ne garde ni son entree ni un lien vers une page refusee', function (string $role, string $rubrique) {
    $libelle = AccesMenu::RUBRIQUES[$rubrique][0];
    // Libellés du menu, sous-menus compris (« Communiqués » et « Accès aux menus » sont sous « Paramètres »)
    $libellesDe = fn (User $membre) => collect(menuDe($membre))
        ->flatMap(fn (array $entrees, string $titre) => [$titre, ...$entrees])
        ->all();

    $membre = membreMenu($role, ['menus_desactives' => [$rubrique]]);

    expect($libellesDe(membreMenu($role)))->toContain($libelle)
        ->and($libellesDe($membre))->not->toContain($libelle);

    $deroulantsVides = [];
    foreach (xpathMenuDe($membre)->query('//ul[@id="sidebar-menu"]/li[contains(@class, "dropdown")][not(ul/li)]') as $deroulant) {
        $deroulantsVides[] = trim($deroulant->textContent);
    }

    $liens = liensDuMenuDe($membre);

    // 200 : la requête a franchi les middlewares ; 403 : page refusée à ce compte
    neutraliserControleurs();
    $refuses = array_filter($liens, fn (string $lien) => $this->actingAs($membre)->get($lien)->status() !== 200);

    expect($deroulantsVides)->toBe([])
        ->and($liens)->not->toBeEmpty()
        ->and(array_values($refuses))->toBe([]);
})->with([
    ...array_map(fn (string $rubrique) => ['directeur', $rubrique], rubriquesSauf('equipe')),
    ['prefet', 'equipe'],
]);

// ==================== BASE DU SITE PAS ENCORE MISE À JOUR ====================

test('tant que la base n a pas la colonne du reglage, la liste s affiche avec un avis et rien ne se regle', function () {
    // SQLite annule la suppression de la colonne avec la transaction du test. Ailleurs (MySQL), elle
    // serait définitive et casserait les tests suivants.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Ce test supprime une colonne : il ne tourne que sur la base SQLite des tests.');
    }

    $avis = 'Ce réglage sera disponible après la prochaine mise à jour de la base de données du site.';
    $admin = membreMenu('admin');
    $censeur = membreMenu('censeur', ['name' => 'Rose AKPO', 'email' => 'rose@ecole.bj']);

    // Base du site avant sa mise à jour : la colonne n'existe pas encore
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('menus_desactives'));
    expect(Schema::hasColumn('users', 'menus_desactives'))->toBeFalse();

    // Aucune erreur ne doit être rattrapée par une page d'erreur : la moindre exception fait échouer le test
    $this->withoutExceptionHandling();

    // Les comptes restent listés, avec l'avis, sans colonne « Menus » ni bouton
    $liste = $this->actingAs($admin)->get(route('admin.acces-menus.index'))->assertOk()->assertSee($avis);

    expect(lignesDeLaListe($liste))->toBe([['Rose AKPO rose@ecole.bj', 'Censeur']]);
    $liste->assertDontSee(route('admin.acces-menus.edit', $censeur), false);

    // Ni la page de réglage ni l'envoi du formulaire ne touchent à la base
    $this->actingAs($admin)->get(route('admin.acces-menus.edit', $censeur))
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('info', $avis);

    reglerMenus($admin, $censeur, [])
        ->assertRedirect(route('admin.acces-menus.index'))
        ->assertSessionHas('info', $avis);

    // De retour sur la liste, l'avis n'est affiché qu'une fois
    $retour = $this->actingAs($admin)->followingRedirects()->get(route('admin.acces-menus.edit', $censeur))->assertOk();

    expect(substr_count($retour->getContent(), e($avis)))->toBe(1);

    // Le reste de l'application ne dépend pas de la colonne : menu complet, pages ouvertes
    $censeur = User::findOrFail($censeur->id);

    expect(menuDe($censeur))->toBe(menuDe(membreMenu('censeur')));
    $this->actingAs($censeur)->get(route('admin.dashboard'))->assertOk();

    neutraliserControleurs();

    $this->actingAs($censeur)->get('/admin/scolarites')->assertOk();
});

test('la colonne du reglage est de nouveau la pour les tests suivants', function () {
    // Garde-fou du test précédent : s'il laissait la base sans la colonne, tous les tests qui
    // enregistrent un réglage échoueraient ensuite sans raison apparente.
    expect(Schema::hasColumn('users', 'menus_desactives'))->toBeTrue();
});
