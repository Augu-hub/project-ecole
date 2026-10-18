<?php

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\TypeNote;
use App\Models\User;
use App\Services\RepriseMatieresService;
use App\Support\AccesMenu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;

function membreSaisie(string $role = 'admin'): User
{
    return User::factory()->create(['role' => $role, 'actif' => true]);
}

function enseignantDeSaisie(): Enseignant
{
    return Enseignant::factory()->create(['user_id' => membreSaisie('enseignant')->id]);
}

/**
 * Année active 2026-2027 et année précédente 2025-2026.
 *
 * @return array{0: AnneeScolaire, 1: AnneeScolaire}
 */
function anneesDeSaisie(): array
{
    $precedente = AnneeScolaire::factory()->create(['libelle' => '2025-2026', 'date_debut' => '2025-09-15', 'date_fin' => '2026-07-15']);
    $active = AnneeScolaire::factory()->active()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-14', 'date_fin' => '2027-07-15']);

    return [$active, $precedente];
}

/**
 * Ouvre la même classe (« 6ème A ») sur chacune des années données : une classe_annee par année, dans le même ordre.
 * Les niveaux sont classés dans l'ordre où ils sont créés.
 *
 * @param  list<AnneeScolaire>  $annees
 * @return list<ClasseAnnee>
 */
function classeDeSaisie(string $nom, array $annees): array
{
    [$niveau, $suffixe] = explode(' ', $nom);

    $classe = Classe::factory()->create([
        'niveau_id' => Niveau::firstOrCreate(['nom' => $niveau], ['ordre' => Niveau::count() + 1, 'cycle' => 'secondaire'])->id,
        'suffixe' => $suffixe,
    ]);

    return array_map(
        fn (AnneeScolaire $annee) => ClasseAnnee::factory()->create(['classe_id' => $classe->id, 'annee_scolaire_id' => $annee->id]),
        $annees
    );
}

function rattacher(ClasseAnnee $classeAnnee, string $nom, float $coefficient = 1, ?Enseignant $enseignant = null): Matiere
{
    $matiere = Matiere::firstOrCreate(['nom_matiere' => $nom]);

    ClasseMatiere::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'coefficient' => $coefficient,
        'enseignant_id' => $enseignant?->id,
    ]);

    return $matiere;
}

/**
 * Matières rattachées à une classe, par ordre alphabétique : [nom, coefficient, enseignant].
 */
function matieresDe(ClasseAnnee $classeAnnee): array
{
    return DB::table('classe_matieres')
        ->join('matieres', 'matieres.id', '=', 'classe_matieres.matiere_id')
        ->where('classe_annee_id', $classeAnnee->id)
        ->orderBy('nom_matiere')
        ->get()
        ->map(fn ($ligne) => [$ligne->nom_matiere, (float) $ligne->coefficient, $ligne->enseignant_id])
        ->all();
}

/**
 * Données que la page embarque pour lier les listes (matières de chaque classe).
 */
function donneesEmbarquees(TestResponse $reponse): array
{
    preg_match('#<script type="application/json" id="saisie-notes-donnees">(.*?)</script>#s', $reponse->getContent(), $trouve);

    return json_decode($trouve[1] ?? 'null', true);
}

/**
 * Liste déroulante telle qu'elle est envoyée au navigateur : [valeur choisie, valeurs proposées].
 */
function listeDeroulante(TestResponse $reponse, string $champ): array
{
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="UTF-8">'.$reponse->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

    $choisie = null;
    $proposees = [];
    foreach ((new DOMXPath($dom))->query('//select[@name="'.$champ.'"]/option') as $option) {
        $proposees[] = $option->getAttribute('value');
        if ($option->hasAttribute('selected')) {
            $choisie = $option->getAttribute('value');
        }
    }

    return [$choisie, $proposees];
}

// ==================== LISTES LIÉES ====================

test('la page embarque pour chaque classe de l annee active ses seules matieres rattachees', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    [$cinquieme, $cinquiemeAvant] = classeDeSaisie('5ème B', [$active, $precedente]);

    $maths = rattacher($sixieme, 'Mathématiques', 4, enseignantDeSaisie());
    $francais = rattacher($sixieme, 'Français', 3);
    $anglais = rattacher($cinquieme, 'Anglais', 2);
    rattacher($cinquiemeAvant, 'Grec');                      // année précédente : hors liste
    Matiere::factory()->create(['nom_matiere' => 'Latin']);  // rattachée à aucune classe
    TypeNote::factory()->create(['nom' => 'Interrogation', 'code' => 'INT', 'max_par_periode' => 4]);

    $reponse = $this->actingAs(membreSaisie('admin'))->get(route('admin.notes.create'))->assertOk();

    $matieresDeSixieme = [
        ['id' => $francais->id, 'nom' => 'Français', 'enseignant' => false],
        ['id' => $maths->id, 'nom' => 'Mathématiques', 'enseignant' => true],
    ];
    $matieresDeCinquieme = [['id' => $anglais->id, 'nom' => 'Anglais', 'enseignant' => false]];

    expect($reponse->viewData('matieresParClasse'))->toBe([$sixieme->id => $matieresDeSixieme, $cinquieme->id => $matieresDeCinquieme])
        // Les mêmes données sont dans la page, avec le lien vers les matières de chaque classe
        ->and(donneesEmbarquees($reponse)['classes'])->toBe([
            $sixieme->id => ['matieres' => $matieresDeSixieme, 'lien' => route('admin.classe-matieres.index', $sixieme)],
            $cinquieme->id => ['matieres' => $matieresDeCinquieme, 'lien' => route('admin.classe-matieres.index', $cinquieme)],
        ]);

    $reponse->assertDontSee('Latin')
        ->assertDontSee('Grec')
        // Le script qui lie les listes est bien envoyé au navigateur, et le n° dépend du type choisi
        ->assertSee('id="saisie-notes-script"', false)
        ->assertSee('data-max="4"', false)
        // Un enseignant existe : une matière sans enseignant sera enregistrée avec l'enseignant par défaut, rien à signaler
        ->assertDontSee('id="alerte-enseignant"', false);
});

test('sans aucun enseignant dans l etablissement, la page previent que les notes ne pourront pas etre enregistrees', function () {
    [$active] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    rattacher($sixieme, 'Français', 3);

    $this->actingAs(membreSaisie('admin'))->get(route('admin.notes.create'))
        ->assertOk()
        ->assertSee('id="alerte-enseignant"', false)
        ->assertSee("Aucun enseignant n'est encore enregistré dans l'établissement.", false);
});

test('un enseignant ne voit que ses classes et pour chacune ses seules matieres', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    [$cinquieme, $cinquiemeAvant] = classeDeSaisie('5ème B', [$active, $precedente]);
    $prof = enseignantDeSaisie();

    $maths = rattacher($sixieme, 'Mathématiques', 4, $prof);
    rattacher($sixieme, 'Français', 3, enseignantDeSaisie());  // un collègue
    rattacher($cinquieme, 'Anglais', 2);                       // personne
    rattacher($cinquiemeAvant, 'Mathématiques', 4, $prof);     // année précédente

    $reponse = $this->actingAs($prof->user)->get(route('admin.notes.create'))->assertOk()
        ->assertDontSee('Français')
        ->assertDontSee('Anglais')
        ->assertDontSee('id="alerte-enseignant"', false);

    expect($reponse->viewData('classesAnnees')->pluck('id')->all())->toBe([$sixieme->id])
        ->and($reponse->viewData('matieresParClasse'))->toBe([
            $sixieme->id => [['id' => $maths->id, 'nom' => 'Mathématiques', 'enseignant' => true]],
        ])
        ->and(donneesEmbarquees($reponse)['classes'][$sixieme->id]['lien'])->toBeNull();
});

test('apres un retour en erreur le choix deja fait est conserve et l erreur est affichee une fois sous le champ', function () {
    [$active] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    [$cinquieme] = classeDeSaisie('5ème B', [$active]);
    $maths = rattacher($sixieme, 'Mathématiques');
    $francais = rattacher($sixieme, 'Français');
    rattacher($cinquieme, 'Anglais');
    Periode::factory()->create(['annee_scolaire_id' => $active->id]);
    $type = TypeNote::factory()->create(['nom' => 'Interrogation', 'code' => 'INT', 'max_par_periode' => 3]);
    $admin = membreSaisie('admin');

    // Période oubliée : la validation renvoie au formulaire
    $this->actingAs($admin)->from(route('admin.notes.create'))
        ->post(route('admin.notes.preview'), [
            'classe_annee_id' => $sixieme->id, 'matiere_id' => $maths->id, 'type_note_id' => $type->id, 'numero' => 2,
        ])
        ->assertRedirect(route('admin.notes.create'))
        ->assertSessionHasErrors('periode_id');

    $erreur = session('errors')->first('periode_id');

    $reponse = $this->actingAs($admin)->get(route('admin.notes.create'))->assertOk();

    expect(listeDeroulante($reponse, 'classe_annee_id')[0])->toBe((string) $sixieme->id)
        // Seules les matières de la classe déjà choisie, et la matière déjà choisie
        ->and(listeDeroulante($reponse, 'matiere_id'))->toBe([(string) $maths->id, ['', (string) $francais->id, (string) $maths->id]])
        ->and(listeDeroulante($reponse, 'periode_id')[0])->toBeNull()
        ->and(listeDeroulante($reponse, 'type_note_id')[0])->toBe((string) $type->id)
        ->and(listeDeroulante($reponse, 'numero'))->toBe(['2', ['1', '2', '3']])
        ->and(substr_count($reponse->getContent(), e($erreur)))->toBe(1);
});

// ==================== ÉTATS VIDES ====================

test('sans annee scolaire active la page l explique', function () {
    $this->actingAs(membreSaisie('admin'))->get(route('admin.notes.create'))->assertOk()
        ->assertSee("La saisie des notes n'est pas encore possible", false)
        ->assertSee("Aucune année scolaire n'est active")
        ->assertSee('Gérer les années scolaires')
        ->assertSee('<fieldset disabled', false);
});

test('aucune classe, aucune periode, aucun type de note : chaque manque est explique avec le lien pour le regler', function () {
    anneesDeSaisie();
    TypeNote::query()->delete();  // une base de test fraîche contient « Composition » (migration pre_moyenner)

    $manques = [
        "Aucune classe n'est ouverte pour l'année scolaire 2026-2027" => "Ouvrir les classes de l'année",
        "Aucune période n'est créée pour l'année scolaire 2026-2027" => 'Gérer les périodes',
        "Aucun type de note n'est défini" => 'Gérer les types de notes',
    ];

    $admin = $this->actingAs(membreSaisie('admin'))->get(route('admin.notes.create'))->assertOk();
    $saisisseur = $this->actingAs(membreSaisie('saisisseur'))->get(route('admin.notes.create'))->assertOk();

    foreach ($manques as $manque => $lien) {
        $admin->assertSee($manque)->assertSee($lien);
        // Le saisisseur n'a pas accès à ces réglages : pas de lien, mais à qui s'adresser
        $saisisseur->assertSee($manque)->assertDontSee($lien);
    }

    $admin->assertSee('<fieldset disabled', false);
    $saisisseur->assertSee("Ces réglages sont faits par l'administration", false);
});

test('une classe sans matiere est signalee avec le lien vers ses matieres pour qui peut les gerer', function (string $role, bool $peutGerer) {
    [$active] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    [$cinquieme] = classeDeSaisie('5ème B', [$active]);
    rattacher($sixieme, 'Mathématiques');
    Periode::factory()->create(['annee_scolaire_id' => $active->id]);

    $reponse = $this->actingAs(membreSaisie($role))->get(route('admin.notes.create'))->assertOk()
        ->assertSee("1 classe n'a pas encore de matières", false)
        ->assertSee('5ème B (sans matière)')
        // Une autre classe a des matières : la saisie reste possible
        ->assertDontSee('<fieldset disabled', false)
        ->assertDontSee("La saisie des notes n'est pas encore possible", false);

    $lien = route('admin.classe-matieres.index', $cinquieme);

    expect($reponse->viewData('classesSansMatiere')->pluck('id')->all())->toBe([$cinquieme->id])
        ->and(donneesEmbarquees($reponse)['classes'][$cinquieme->id])->toBe(['matieres' => [], 'lien' => $peutGerer ? $lien : null]);

    $peutGerer
        ? $reponse->assertSee('href="'.$lien.'"', false)
        : $reponse->assertDontSee($lien, false)->assertSee('Adressez-vous au censeur ou à la direction');
})->with([
    'admin' => ['admin', true],
    'censeur' => ['censeur', true],
    'saisisseur' => ['saisisseur', false],
]);

test('si aucune classe n a de matiere la saisie est bloquee et la reprise est proposee a qui peut la lancer', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme, $sixiemeAvant] = classeDeSaisie('6ème A', [$active, $precedente]);
    classeDeSaisie('5ème B', [$active]);  // nouvelle classe : rien à reprendre pour elle
    rattacher($sixiemeAvant, 'Mathématiques', 4);
    rattacher($sixiemeAvant, 'Français', 3);
    Periode::factory()->create(['annee_scolaire_id' => $active->id]);

    $admin = $this->actingAs(membreSaisie('admin'))->get(route('admin.notes.create'))->assertOk()
        ->assertSee("Aucune classe n'a encore de matières pour l'année scolaire 2026-2027", false)
        ->assertSee('<fieldset disabled', false)
        ->assertSee("Reprendre les matières de l'année précédente", false)
        ->assertSee('action="'.route('admin.classe-matieres.reprise-annee').'"', false);

    // L'aperçu affiché avant de confirmer : la 6ème A reprend ses 2 matières de 2025-2026
    expect($admin->viewData('reprise')->map(fn (array $ligne) => [$ligne['source']->id, $ligne['matieres']])->all())
        ->toBe([$sixieme->id => [$sixiemeAvant->id, 2]]);

    $this->actingAs(membreSaisie('saisisseur'))->get(route('admin.notes.create'))->assertOk()
        ->assertSee("Aucune classe n'a encore de matières pour l'année scolaire 2026-2027", false)
        ->assertDontSee("Reprendre les matières de l'année précédente", false)
        ->assertDontSee(route('admin.classe-matieres.reprise-annee'), false);
});

test('un enseignant sans classe attribuee ou sans fiche enseignant est prevenu', function () {
    [$active] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    rattacher($sixieme, 'Mathématiques');  // attribuée à personne

    $sansClasse = $this->actingAs(enseignantDeSaisie()->user)->get(route('admin.notes.create'))->assertOk()
        ->assertSee("Aucune classe ne vous est attribuée pour l'année scolaire 2026-2027")
        ->assertDontSee('Mathématiques');

    $sansFiche = $this->actingAs(membreSaisie('enseignant'))->get(route('admin.notes.create'))->assertOk()
        ->assertSee("Votre compte n'est relié à aucune fiche enseignant")
        ->assertDontSee('Mathématiques');

    expect($sansClasse->viewData('classesAnnees'))->toBeEmpty()
        ->and($sansFiche->viewData('classesAnnees'))->toBeEmpty();
});

// ==================== REPRISE DES MATIÈRES DE L'ANNÉE PRÉCÉDENTE ====================

test('la reprise recopie matieres, coefficients et enseignants de la meme classe dans l annee anterieure la plus recente qui en a', function () {
    [$active, $precedente] = anneesDeSaisie();
    $ancienne = AnneeScolaire::factory()->create(['libelle' => '2024-2025', 'date_debut' => '2024-09-16', 'date_fin' => '2025-07-15']);
    $suivante = AnneeScolaire::factory()->create(['libelle' => '2027-2028', 'date_debut' => '2027-09-13', 'date_fin' => '2028-07-15']);
    $prof = enseignantDeSaisie();

    // 6ème A : des matières en 2025-2026 (à reprendre), en 2024-2025 (plus anciennes) et en 2027-2028 (année postérieure)
    [$sixieme, $sixiemeAvant, $sixiemeAncienne, $sixiemeSuivante] = classeDeSaisie('6ème A', [$active, $precedente, $ancienne, $suivante]);
    rattacher($sixiemeAvant, 'Mathématiques', 4, $prof);
    rattacher($sixiemeAvant, 'Français', 3.5);
    rattacher($sixiemeAncienne, 'Latin', 2);
    rattacher($sixiemeSuivante, 'Chinois', 1);

    // 5ème B : ouverte en 2025-2026 mais sans matière, on remonte à 2024-2025
    [$cinquieme, , $cinquiemeAncienne] = classeDeSaisie('5ème B', [$active, $precedente, $ancienne]);
    rattacher($cinquiemeAncienne, 'Anglais', 2);

    // 4ème C : nouvelle classe, rien à reprendre
    [$quatrieme] = classeDeSaisie('4ème C', [$active]);

    $this->actingAs(membreSaisie('admin'))->post(route('admin.classe-matieres.reprise-annee'))
        ->assertRedirect(route('admin.notes.create'))
        ->assertSessionHas('success', 'Reprise terminée : 2 classes complétées, 3 matières rattachées (coefficients et enseignants compris).');

    expect(matieresDe($sixieme))->toBe([['Français', 3.5, null], ['Mathématiques', 4.0, $prof->id]])
        ->and(matieresDe($cinquieme))->toBe([['Anglais', 2.0, null]])
        ->and(matieresDe($quatrieme))->toBe([])
        // Les années d'origine ne sont pas modifiées
        ->and(matieresDe($sixiemeAvant))->toBe([['Français', 3.5, null], ['Mathématiques', 4.0, $prof->id]])
        ->and(DB::table('classe_matieres')->count())->toBe(8);
});

test('rejouer la reprise ne cree aucun doublon et le dit clairement', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme, $sixiemeAvant] = classeDeSaisie('6ème A', [$active, $precedente]);
    rattacher($sixiemeAvant, 'Mathématiques', 4);
    rattacher($sixiemeAvant, 'Français', 3);
    $admin = membreSaisie('admin');

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise-annee'))->assertSessionHas('success');
    $apresPremiereReprise = matieresDe($sixieme);

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise-annee'))
        ->assertRedirect(route('admin.notes.create'))
        ->assertSessionHas('info', "Rien à reprendre : toutes les classes de l'année ont déjà des matières.")
        ->assertSessionMissing('success');

    expect($apresPremiereReprise)->toHaveCount(2)
        ->and(matieresDe($sixieme))->toBe($apresPremiereReprise);
});

test('la reprise ne touche jamais une classe qui a deja des matieres', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme, $sixiemeAvant] = classeDeSaisie('6ème A', [$active, $precedente]);
    rattacher($sixiemeAvant, 'Mathématiques', 4, enseignantDeSaisie());
    rattacher($sixiemeAvant, 'Français', 3);
    rattacher($sixieme, 'Mathématiques', 9);  // déjà configurée cette année, autrement
    $admin = membreSaisie('admin');

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise-annee'))
        ->assertSessionHas('info', "Rien à reprendre : toutes les classes de l'année ont déjà des matières.");

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise', $sixieme))
        ->assertRedirect(route('admin.classe-matieres.index', $sixieme))
        ->assertSessionHas('info', 'Rien à reprendre : cette classe a déjà des matières, et elles ne sont jamais remplacées.');

    // Ni coefficient remplacé, ni matière ajoutée
    expect(matieresDe($sixieme))->toBe([['Mathématiques', 9.0, null]]);
});

test('sans matiere sur une annee precedente il n y a rien a reprendre et rien n est propose', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active, $precedente]);
    $admin = membreSaisie('admin');

    $this->actingAs($admin)->get(route('admin.notes.create'))->assertOk()
        ->assertDontSee("Reprendre les matières de l'année précédente", false)
        ->assertSee("il n'y a rien à reprendre", false);

    $this->actingAs($admin)->get(route('admin.classe-matieres.index', $sixieme))->assertOk()
        ->assertDontSee('Reprendre les matières', false);

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise-annee'))
        ->assertRedirect(route('admin.notes.create'))
        ->assertSessionHas('info', "Rien à reprendre : les classes sans matière n'en avaient pas non plus les années précédentes.");

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise', $sixieme))
        ->assertRedirect(route('admin.classe-matieres.index', $sixieme))
        ->assertSessionHas('info', "Rien à reprendre : cette classe n'avait pas de matières les années précédentes.");

    expect(DB::table('classe_matieres')->count())->toBe(0);
});

test('depuis la page des matieres d une classe vide on reprend les matieres de cette classe seule', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme, $sixiemeAvant] = classeDeSaisie('6ème A', [$active, $precedente]);
    [$cinquieme, $cinquiemeAvant] = classeDeSaisie('5ème B', [$active, $precedente]);
    $prof = enseignantDeSaisie();
    rattacher($sixiemeAvant, 'Mathématiques', 4, $prof);
    rattacher($sixiemeAvant, 'Français', 3);
    rattacher($cinquiemeAvant, 'Anglais', 2);
    $admin = membreSaisie('admin');

    $this->actingAs($admin)->get(route('admin.classe-matieres.index', $sixieme))->assertOk()
        ->assertSee("Reprendre les matières de l'année 2025-2026", false)
        ->assertSee('Français, Mathématiques')
        ->assertSee('Reprendre ces 2 matières')
        ->assertSee('action="'.route('admin.classe-matieres.reprise', $sixieme).'"', false);

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise', $sixieme))
        ->assertRedirect(route('admin.classe-matieres.index', $sixieme))
        ->assertSessionHas('success', "Reprise terminée : 2 matières reprises de l'année 2025-2026, avec les coefficients et les enseignants. Vérifiez-les et ajustez-les si besoin.");

    expect(matieresDe($sixieme))->toBe([['Français', 3.0, null], ['Mathématiques', 4.0, $prof->id]])
        ->and(matieresDe($cinquieme))->toBe([]);

    // Une fois la classe remplie, la reprise n'est plus proposée
    $this->actingAs($admin)->get(route('admin.classe-matieres.index', $sixieme))->assertOk()
        ->assertDontSee('Reprendre les matières', false);
});

test('la reprise est reservee aux roles qui gerent les matieres des classes', function (string $role, bool $autorise) {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme, $sixiemeAvant] = classeDeSaisie('6ème A', [$active, $precedente]);
    rattacher($sixiemeAvant, 'Mathématiques', 4);
    $membre = membreSaisie($role);

    foreach (['admin.classe-matieres.reprise-annee', 'admin.classe-matieres.reprise'] as $reprise) {
        DB::table('classe_matieres')->where('classe_annee_id', $sixieme->id)->delete();

        $reponse = $this->actingAs($membre)->post(route($reprise, $sixieme));

        $autorise ? $reponse->assertRedirect() : $reponse->assertForbidden();

        expect(matieresDe($sixieme))->toHaveCount($autorise ? 1 : 0)
            // Mêmes droits que la page des matières d'une classe
            ->and(AccesMenu::autorise($reprise, $membre))->toBe(AccesMenu::autorise('admin.classe-matieres.index', $membre))
            ->and(AccesMenu::autorise($reprise, $membre))->toBe($autorise);
    }
})->with([
    'admin' => ['admin', true],
    'censeur' => ['censeur', true],
    'directeur' => ['directeur', true],
    'econome' => ['econome', true],
    'saisisseur' => ['saisisseur', false],
    'enseignant' => ['enseignant', false],
    'prefet' => ['prefet', false],
    'surveillant' => ['surveillant', false],
]);

test('la reprise demande d etre connecte', function () {
    [$active, $precedente] = anneesDeSaisie();
    [$sixieme, $sixiemeAvant] = classeDeSaisie('6ème A', [$active, $precedente]);
    rattacher($sixiemeAvant, 'Mathématiques', 4);

    $this->post(route('admin.classe-matieres.reprise-annee'))->assertRedirect(route('login'));
    $this->post(route('admin.classe-matieres.reprise', $sixieme))->assertRedirect(route('login'));

    expect(matieresDe($sixieme))->toBe([]);
});

test('si la reprise echoue l utilisateur ne voit aucun detail technique et l erreur est journalisee', function () {
    Exceptions::fake();
    [$active] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    $this->mock(RepriseMatieresService::class)
        ->shouldReceive('reprendre')->andThrow(new RuntimeException('SQLSTATE[23000] detail interne'));

    $message = "Les matières n'ont pas pu être reprises et rien n'a été modifié. Veuillez réessayer.";
    $admin = membreSaisie('admin');

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise-annee'))
        ->assertRedirect(route('admin.notes.create'))
        ->assertSessionHas('error', $message);

    $this->actingAs($admin)->post(route('admin.classe-matieres.reprise', $sixieme))
        ->assertRedirect(route('admin.classe-matieres.index', $sixieme))
        ->assertSessionHas('error', $message);

    Exceptions::assertReported(RuntimeException::class);
});

test('une matiere reprise peut ensuite recevoir un enseignant, changer de coefficient ou etre retiree', function () {
    [$active] = anneesDeSaisie();
    [$sixieme] = classeDeSaisie('6ème A', [$active]);
    $maths = rattacher($sixieme, 'Mathématiques', 4);
    $prof = enseignantDeSaisie();
    $admin = membreSaisie('admin');

    $this->actingAs($admin)
        ->put(route('admin.classe-matieres.update', [$sixieme, $maths]), ['coefficient' => 5, 'enseignant_id' => $prof->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(matieresDe($sixieme))->toBe([['Mathématiques', 5.0, $prof->id]]);

    $this->actingAs($admin)
        ->delete(route('admin.classe-matieres.destroy', [$sixieme, $maths]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(matieresDe($sixieme))->toBe([]);

    // Matière qui n'est pas (ou plus) rattachée à la classe : page introuvable, pas d'erreur
    $this->actingAs($admin)
        ->put(route('admin.classe-matieres.update', [$sixieme, $maths]), ['coefficient' => 5])
        ->assertNotFound();
});
