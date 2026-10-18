<?php

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Communique;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\FraisDossier;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\Periode;
use App\Models\Scolarite;
use App\Models\Tranche;
use App\Models\User;

function adminAnnee(): User
{
    return User::factory()->create(['role' => 'admin', 'actif' => true]);
}

/**
 * Une classe « 6ème A » ouverte sur l'année active et une « 5ème B » restée sur une année archivée.
 */
function deuxAnnees(): array
{
    $active = AnneeScolaire::factory()->active()->create(['libelle' => '2026-2027']);
    $archivee = AnneeScolaire::factory()->create(['libelle' => '2019-2020']);

    $sixieme = Niveau::factory()->create(['nom' => '6ème', 'cycle' => 'secondaire', 'ordre' => 1]);
    $cinquieme = Niveau::factory()->create(['nom' => '5ème', 'cycle' => 'secondaire', 'ordre' => 2]);

    $classeActive = ClasseAnnee::factory()->create([
        'classe_id' => Classe::factory()->create(['niveau_id' => $sixieme->id, 'suffixe' => 'A'])->id,
        'annee_scolaire_id' => $active->id,
    ]);
    $classeArchivee = ClasseAnnee::factory()->create([
        'classe_id' => Classe::factory()->create(['niveau_id' => $cinquieme->id, 'suffixe' => 'B'])->id,
        'annee_scolaire_id' => $archivee->id,
    ]);

    return compact('active', 'archivee', 'classeActive', 'classeArchivee');
}

test('le tableau de bord ne compte que l annee active', function () {
    $ctx = deuxAnnees();

    $parent = Parents::factory()->create();
    Eleve::factory()->count(2)->create(['classe_annee_id' => $ctx['classeActive']->id, 'sexe' => 'M', 'parent_id' => $parent->id]);
    Eleve::factory()->create(['classe_annee_id' => $ctx['classeActive']->id, 'sexe' => 'F', 'parent_id' => $parent->id]);
    Eleve::factory()->create(['classe_annee_id' => $ctx['classeActive']->id, 'sexe' => null, 'parent_id' => $parent->id]);
    Eleve::factory()->count(5)->create(['classe_annee_id' => $ctx['classeArchivee']->id, 'sexe' => 'M']);

    $matiere = Matiere::factory()->create();
    $enPoste = Enseignant::factory()->create(['nom' => 'ENPOSTE']);
    $ancien = Enseignant::factory()->create(['nom' => 'ANCIENPROF']);
    ClasseMatiere::create(['classe_annee_id' => $ctx['classeActive']->id, 'matiere_id' => $matiere->id, 'coefficient' => 2, 'enseignant_id' => $enPoste->id]);
    ClasseMatiere::create(['classe_annee_id' => $ctx['classeArchivee']->id, 'matiere_id' => $matiere->id, 'coefficient' => 2, 'enseignant_id' => $ancien->id]);

    Communique::create(['titre' => 'Reunion de rentree', 'contenu' => '...', 'type' => 'general', 'date_publication' => today(), 'actif' => true])
        ->classesAnnees()->attach($ctx['classeActive']->id);
    Communique::create(['titre' => 'Vieux communique', 'contenu' => '...', 'type' => 'general', 'date_publication' => today(), 'actif' => true])
        ->classesAnnees()->attach($ctx['classeArchivee']->id);

    $reponse = $this->actingAs(adminAnnee())->get(route('admin.dashboard'))->assertOk();

    $reponse->assertSee('Année scolaire 2026-2027')
        ->assertViewHas('totalEleves', 4)
        ->assertViewHas('elevesActifs', 4)
        ->assertViewHas('elevesHommes', 2)
        ->assertViewHas('elevesFemmes', 1)
        ->assertViewHas('elevesSexeNonRenseigne', 1)
        ->assertViewHas('totalClasses', 1)
        ->assertViewHas('totalEnseignants', 1)
        ->assertViewHas('enseignantsInscrits', 2)
        ->assertViewHas('totalParents', 1)
        ->assertViewHas('parentsInscrits', 6)
        ->assertSee('ENPOSTE')
        ->assertDontSee('ANCIENPROF')
        ->assertSee('Reunion de rentree')
        ->assertDontSee('Vieux communique');
});

test('les encaissements de l annee suivent ce qui est paye et non la classe actuelle de l eleve', function () {
    $ctx = deuxAnnees();

    // Élève réinscrit : il est aujourd'hui dans une classe de l'année active mais a payé
    // une tranche de l'année archivée.
    $eleve = Eleve::factory()->create(['classe_annee_id' => $ctx['classeActive']->id]);

    $trancheActive = Tranche::factory()->create(['scolarite_id' => Scolarite::factory()->create(['classe_annee_id' => $ctx['classeActive']->id])->id]);
    $trancheArchivee = Tranche::factory()->create(['scolarite_id' => Scolarite::factory()->create(['classe_annee_id' => $ctx['classeArchivee']->id])->id]);
    $frais = FraisDossier::factory()->create(['classe_annee_id' => $ctx['classeActive']->id]);
    $cantine = CantineOffre::factory()->create(['annee_scolaire_id' => $ctx['active']->id]);
    $cantineArchivee = CantineOffre::factory()->create(['annee_scolaire_id' => $ctx['archivee']->id]);

    $payer = fn (array $cible, int $montant) => Paiement::factory()->create($cible + ['eleve_id' => $eleve->id, 'montant' => $montant]);

    $payer(['tranche_id' => $trancheActive->id, 'payable_type' => Tranche::class, 'payable_id' => $trancheActive->id], 10000);
    $payer(['tranche_id' => $trancheArchivee->id, 'payable_type' => Tranche::class, 'payable_id' => $trancheArchivee->id], 70000);
    $payer(['tranche_id' => null, 'payable_type' => FraisDossier::class, 'payable_id' => $frais->id], 2000);
    $payer(['tranche_id' => null, 'payable_type' => CantineOffre::class, 'payable_id' => $cantine->id], 300);
    $payer(['tranche_id' => null, 'payable_type' => CantineOffre::class, 'payable_id' => $cantineArchivee->id], 40000);

    $reponse = $this->actingAs(adminAnnee())->get(route('admin.dashboard'))->assertOk();

    expect((float) $reponse->viewData('paiementsAnnee'))->toBe(12300.0)
        ->and((float) $reponse->viewData('totalPaiements'))->toBe(122300.0);
});

test('le tableau de bord s affiche sans annee active', function () {
    ClasseAnnee::factory()->create();
    Eleve::factory()->count(2)->create();

    $this->actingAs(adminAnnee())->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Aucune année scolaire n\'est active', false)
        ->assertViewHas('totalEleves', 0)
        ->assertViewHas('totalClasses', 0);
});

test('les listes deroulantes ne proposent que les classes de l annee active', function (string $route) {
    $ctx = deuxAnnees();

    $ids = $this->actingAs(adminAnnee())->get(route($route))->assertOk()
        ->viewData('classesAnnees')->pluck('id')->all();

    expect($ids)->toBe([$ctx['classeActive']->id]);
})->with([
    'scolarité' => 'admin.scolarites.create',
    'frais de dossier' => 'admin.frais-dossiers.create',
    'communiqué' => 'admin.communiques.create',
    'import élèves' => 'admin.eleves.import.create',
    'saisie des notes' => 'admin.notes.create',
]);

test('l edition d une fiche d une annee archivee garde sa classe dans la liste', function () {
    $ctx = deuxAnnees();
    $scolarite = Scolarite::factory()->create(['classe_annee_id' => $ctx['classeArchivee']->id]);

    $ids = $this->actingAs(adminAnnee())->get(route('admin.scolarites.edit', $scolarite))->assertOk()
        ->viewData('classesAnnees')->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([$ctx['classeActive']->id, $ctx['classeArchivee']->id]);
});

test('la saisie des notes ne propose que les periodes de l annee active', function () {
    $ctx = deuxAnnees();
    $periodeActive = Periode::factory()->create(['annee_scolaire_id' => $ctx['active']->id]);
    Periode::factory()->create(['annee_scolaire_id' => $ctx['archivee']->id]);

    $ids = $this->actingAs(adminAnnee())->get(route('admin.notes.create'))->assertOk()
        ->viewData('periodes')->pluck('id')->all();

    expect($ids)->toBe([$periodeActive->id]);
});

test('la liste des notes s ouvre sur l annee active et peut afficher toutes les annees', function () {
    $ctx = deuxAnnees();
    $admin = adminAnnee();

    $noteActive = Note::factory()->create(['eleve_id' => Eleve::factory()->create(['classe_annee_id' => $ctx['classeActive']->id])->id]);
    $noteArchivee = Note::factory()->create(['eleve_id' => Eleve::factory()->create(['classe_annee_id' => $ctx['classeArchivee']->id])->id]);

    $parDefaut = $this->actingAs($admin)->get(route('admin.notes.index'))->assertOk();
    expect($parDefaut->viewData('notes')->pluck('id')->all())->toBe([$noteActive->id])
        ->and($parDefaut->viewData('anneeSelectionneeId'))->toBe($ctx['active']->id);

    $toutes = $this->actingAs($admin)->get(route('admin.notes.index', ['annee_scolaire_id' => '']))->assertOk();
    expect($toutes->viewData('notes')->pluck('id')->sort()->values()->all())->toBe([$noteActive->id, $noteArchivee->id]);
});

test('le filtre par classe des parents ne propose que l annee active', function () {
    $ctx = deuxAnnees();

    $ids = $this->actingAs(adminAnnee())->get(route('admin.parents.index'))->assertOk()
        ->viewData('classes')->pluck('id')->all();

    expect($ids)->toBe([$ctx['classeActive']->id]);
});

test('une nouvelle association classe-annee preselectionne l annee active', function () {
    $ctx = deuxAnnees();

    $this->actingAs(adminAnnee())->get(route('admin.classe-annees.create'))
        ->assertOk()
        ->assertSee('value="'.$ctx['active']->id.'" selected', false);
});
