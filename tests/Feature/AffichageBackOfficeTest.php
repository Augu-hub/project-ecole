<?php

use App\Models\TypeNote;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

function adminAffichage(): User
{
    return User::factory()->create(['role' => 'admin', 'actif' => true]);
}

/**
 * Types de notes tels qu'ils ont été créés en production : les interrogations 3 et 4 après le devoir
 * et la composition, deux noms saisis en majuscules.
 */
function typesDeNotesDansLeDesordre(): void
{
    // Une base de test fraîche contient déjà « Composition » (créé par la migration pre_moyenner)
    TypeNote::query()->delete();

    foreach ([
        ['Interrogation 1', 'INT2'], ['Interrogation 2', 'INT'], ['DEVOIR', 'DEV'],
        ['COMPOSITION', 'COMP'], ['Interrogation 3', 'INT 3'], ['Interrogation 4', 'INT 4'],
    ] as [$nom, $code]) {
        TypeNote::factory()->create(['nom' => $nom, 'code' => $code, 'max_par_periode' => 1]);
    }
}

// ==================== MESSAGES À L'UTILISATEUR ====================

test('un message d erreur s affiche meme sur une page qui ne le prevoit pas', function () {
    // La liste des types de notes n'affiche elle-même que les messages de succès
    $this->actingAs(adminAffichage())
        ->withSession(['error' => "Cette matière n'est pas enseignée dans cette classe."])
        ->get(route('admin.type-notes.index'))
        ->assertOk()
        ->assertSee("Cette matière n'est pas enseignée dans cette classe.");
});

test('un message deja affiche par la page n est pas repete', function () {
    $html = $this->actingAs(adminAffichage())
        ->withSession(['success' => 'Type de note enregistré.'])
        ->get(route('admin.type-notes.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'Type de note enregistré.'))->toBe(1);
});

test('les erreurs de formulaire sont en francais et nomment le champ', function () {
    $this->actingAs(adminAffichage())
        ->from(route('admin.type-notes.create'))
        ->post(route('admin.type-notes.store'), [])
        ->assertSessionHasErrors([
            'nom' => 'Le champ nom est obligatoire.',
            'code' => 'Le champ code est obligatoire.',
        ]);

    $erreurs = Validator::make(
        ['classe_annee_id' => '', 'notes' => [7 => ['valeur' => 25]]],
        ['classe_annee_id' => 'required', 'notes.*.valeur' => 'numeric|max:20'],
    )->errors();

    expect($erreurs->first('classe_annee_id'))->toBe('Le champ classe est obligatoire.')
        ->and($erreurs->first('notes.7.valeur'))->toBe('Le champ note ne doit pas dépasser 20.');
});

// ==================== MISE EN PAGE ====================

test('les styles et scripts propres a une page sont charges, sans le script de demonstration du gabarit', function () {
    // La liste des notes envoie ses propres styles et son propre script à la mise en page
    $this->actingAs(adminAffichage())
        ->get(route('admin.notes.index'))
        ->assertOk()
        ->assertSee('.badge.bg-purple-soft', false)
        ->assertSee('Auto-submit on filter change', false)
        ->assertDontSee('Revenue Statistics Chart')
        ->assertDontSee('createChartThree');
});

test('la mise en page est en francais et au nom de l etablissement', function () {
    $this->actingAs(adminAffichage())
        ->get(route('admin.type-notes.index'))
        ->assertOk()
        ->assertSee('<html lang="fr"', false)
        ->assertSee('<title>Administration — '.e(config('ecole.nom')).'</title>', false)
        ->assertDontSee('Edudash')
        ->assertDontSee('Wowtheme7');
});

test('le role affiche sous le nom est le vrai role, et le menu du profil mene au profil', function (string $role, string $libelle) {
    $this->actingAs(User::factory()->create(['role' => $role, 'actif' => true]));

    $barre = view('back.layouts.partials.nav-bar')->render();

    expect($barre)->toContain($libelle)
        ->toContain('href="'.route('profile.edit').'"')
        ->not->toContain('href="#"')
        ->and(view('back.layouts.partials.aside')->render())->toContain($libelle);
})->with([
    ['directeur', 'Directeur'],
    ['prefet', 'Préfet'],
    ['saisisseur', 'Saisisseur de note'],
    ['econome', 'Économe'],
]);

// ==================== TYPES DE NOTES ====================

test('les types de notes sont listes dans l ordre des evaluations', function () {
    typesDeNotesDansLeDesordre();
    TypeNote::factory()->create(['nom' => 'Interrogation 10', 'code' => 'INT10']);
    TypeNote::factory()->create(['nom' => 'TP', 'code' => 'TP']);

    // Interrogations (dans l'ordre des numéros), puis devoirs, puis compositions, puis le reste
    expect(TypeNote::ordonnes()->pluck('nom')->all())->toBe([
        'Interrogation 1', 'Interrogation 2', 'Interrogation 3', 'Interrogation 4', 'Interrogation 10',
        'DEVOIR', 'COMPOSITION', 'TP',
    ]);
});

test('un nom de type saisi tout en majuscules est affiche comme les autres', function (string $nom, string $libelle) {
    expect((new TypeNote(['nom' => $nom]))->libelle)->toBe($libelle);
})->with([
    'devoir' => ['DEVOIR', 'Devoir'],
    'composition' => ['COMPOSITION', 'Composition'],
    'plusieurs mots' => ['DEVOIR DE MAISON', 'Devoir de maison'],
    'nom deja correct' => ['Interrogation 1', 'Interrogation 1'],
    'sigle court' => ['TP', 'TP'],
]);

test('les listes de types sont dans le bon ordre et sans majuscules', function (string $route) {
    typesDeNotesDansLeDesordre();

    $this->actingAs(adminAffichage())
        ->get(route($route))
        ->assertOk()
        ->assertSeeInOrder(['Interrogation 1', 'Interrogation 2', 'Interrogation 3', 'Interrogation 4', 'Devoir', 'Composition'])
        ->assertDontSee('DEVOIR')
        ->assertDontSee('COMPOSITION');
})->with([
    'filtre de la liste des notes' => ['admin.notes.index'],
    'saisie des notes' => ['admin.notes.create'],
]);
