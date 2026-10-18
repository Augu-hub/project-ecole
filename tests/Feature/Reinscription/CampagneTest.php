<?php

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Reinscription;
use App\Models\TypeNote;
use App\Models\User;

function creerContexteReinscription(): array
{
    $niveau = Niveau::factory()->create(['cycle' => 'secondaire']);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id]);

    $anneeSource = AnneeScolaire::factory()->create(['est_active' => false]);
    $anneeCible = AnneeScolaire::factory()->create(['est_active' => true]);

    $classeAnneeSource = ClasseAnnee::factory()->create(['classe_id' => $classe->id, 'annee_scolaire_id' => $anneeSource->id]);
    $classeAnneeCible = ClasseAnnee::factory()->create(['classe_id' => $classe->id, 'annee_scolaire_id' => $anneeCible->id]);

    $matiere = Matiere::factory()->create();
    ClasseMatiere::create(['classe_annee_id' => $classeAnneeSource->id, 'matiere_id' => $matiere->id, 'coefficient' => 2]);

    $periode = Periode::factory()->create(['annee_scolaire_id' => $anneeSource->id]);
    $typeNote = TypeNote::factory()->create();

    return compact('anneeSource', 'anneeCible', 'classeAnneeSource', 'classeAnneeCible', 'matiere', 'periode', 'typeNote');
}

test('un eleve promu est reinscrit dans la classe cible et l\'historique est cree', function () {
    $ctx = creerContexteReinscription();

    $eleve = Eleve::factory()->create(['classe_annee_id' => $ctx['classeAnneeSource']->id, 'statut' => 'actif']);

    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_annee_id' => $ctx['classeAnneeSource']->id,
        'matiere_id' => $ctx['matiere']->id,
        'periode_id' => $ctx['periode']->id,
        'type_note_id' => $ctx['typeNote']->id,
        'valeur' => 15,
    ]);

    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $response = $this->actingAs($admin)->post(route('admin.reinscriptions.confirmer', [$ctx['anneeSource'], $ctx['classeAnneeSource']]), [
        'decisions' => [
            $eleve->id => [
                'action' => 'reinscrire',
                'classe_annee_id' => $ctx['classeAnneeCible']->id,
                'type' => 'promu',
                'moyenne_suggestion' => 15,
            ],
        ],
    ]);

    $response->assertRedirect();

    $eleve->refresh();
    expect($eleve->classe_annee_id)->toBe($ctx['classeAnneeCible']->id)
        ->and($eleve->statut)->toBe('actif');

    $reinscription = Reinscription::where('eleve_id', $eleve->id)->first();
    expect($reinscription)->not->toBeNull()
        ->and($reinscription->type)->toBe('promu')
        ->and($reinscription->classe_annee_precedente_id)->toBe($ctx['classeAnneeSource']->id)
        ->and($reinscription->classe_annee_id)->toBe($ctx['classeAnneeCible']->id)
        ->and($reinscription->annee_scolaire_id)->toBe($ctx['anneeCible']->id);
});

test('un eleve qui ne se reinscrit pas passe au statut ancien sans ligne d\'historique', function () {
    $ctx = creerContexteReinscription();

    $eleve = Eleve::factory()->create(['classe_annee_id' => $ctx['classeAnneeSource']->id, 'statut' => 'actif']);

    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $this->actingAs($admin)->post(route('admin.reinscriptions.confirmer', [$ctx['anneeSource'], $ctx['classeAnneeSource']]), [
        'decisions' => [
            $eleve->id => [
                'action' => 'ne_revient_pas',
            ],
        ],
    ]);

    $eleve->refresh();
    expect($eleve->statut)->toBe('ancien');
    expect(Reinscription::where('eleve_id', $eleve->id)->exists())->toBeFalse();
});

test('la campagne d\'une classe ne montre que les eleves de cette classe, pas d\'une autre', function () {
    $ctx = creerContexteReinscription();

    // Une deuxième classe (même année source) avec un élève qui ne doit PAS apparaître.
    $autreClasse = Classe::factory()->create(['niveau_id' => $ctx['classeAnneeSource']->classe->niveau_id, 'suffixe' => 'Z']);
    $autreClasseAnneeSource = ClasseAnnee::factory()->create([
        'classe_id' => $autreClasse->id,
        'annee_scolaire_id' => $ctx['anneeSource']->id,
    ]);

    $eleveClasseA = Eleve::factory()->create(['classe_annee_id' => $ctx['classeAnneeSource']->id, 'statut' => 'actif']);
    $eleveClasseB = Eleve::factory()->create(['classe_annee_id' => $autreClasseAnneeSource->id, 'statut' => 'actif']);

    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $response = $this->actingAs($admin)->get(route('admin.reinscriptions.campagne-classe', [
        $ctx['anneeSource'], $ctx['classeAnneeSource'],
    ]));

    $response->assertOk();
    $eleves = $response->viewData('eleves');

    expect($eleves->pluck('id'))->toContain($eleveClasseA->id)
        ->and($eleves->pluck('id'))->not->toContain($eleveClasseB->id);
});
