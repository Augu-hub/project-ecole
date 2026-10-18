<?php

use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\CreneauHoraire;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Services\EmploiDuTempsConflitService;

/**
 * Crée une classe_annee secondaire + une matière assignée à un enseignant (donné ou nouveau).
 *
 * @return array{0: ClasseAnnee, 1: Matiere, 2: Enseignant}
 */
function creerClasseMatierePourEmploi(?Enseignant $enseignant = null): array
{
    $niveau = Niveau::factory()->create(['cycle' => 'secondaire']);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id]);
    $classeAnnee = ClasseAnnee::factory()->create(['classe_id' => $classe->id]);
    $matiere = Matiere::factory()->create();
    $enseignant ??= Enseignant::factory()->create();

    ClasseMatiere::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'coefficient' => 1,
        'enseignant_id' => $enseignant->id,
    ]);

    return [$classeAnnee, $matiere, $enseignant];
}

test('deux creneaux adjacents (8h-10h puis 10h-12h) pour la meme classe et le meme enseignant ne sont pas en conflit', function () {
    $enseignant = Enseignant::factory()->create();
    [$classeAnnee, $matiere] = creerClasseMatierePourEmploi($enseignant);

    $creneau1 = CreneauHoraire::factory()->create(['heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);
    $creneau2 = CreneauHoraire::factory()->create(['heure_debut' => '10:00:00', 'heure_fin' => '12:00:00']);

    EmploiDuTemps::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'jour_semaine' => 'lundi',
        'creneau_horaire_id' => $creneau1->id,
    ]);

    $conflit = app(EmploiDuTempsConflitService::class)
        ->detecter($classeAnnee->id, $matiere->id, 'lundi', $creneau2);

    expect($conflit)->toBeNull();
});

test('deux creneaux qui se chevauchent reellement pour le meme enseignant sur des classes differentes sont en conflit', function () {
    $enseignant = Enseignant::factory()->create();
    [$classeAnneeA, $matiereA] = creerClasseMatierePourEmploi($enseignant);
    [$classeAnneeB, $matiereB] = creerClasseMatierePourEmploi($enseignant);

    $creneau1 = CreneauHoraire::factory()->create(['heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);
    $creneau2 = CreneauHoraire::factory()->create(['heure_debut' => '09:00:00', 'heure_fin' => '11:00:00']);

    EmploiDuTemps::create([
        'classe_annee_id' => $classeAnneeA->id,
        'matiere_id' => $matiereA->id,
        'jour_semaine' => 'lundi',
        'creneau_horaire_id' => $creneau1->id,
    ]);

    $conflit = app(EmploiDuTempsConflitService::class)
        ->detecter($classeAnneeB->id, $matiereB->id, 'lundi', $creneau2);

    expect($conflit)->not->toBeNull()
        ->and($conflit)->toContain('enseignant');
});

test('deux creneaux qui se chevauchent pour la meme classe (matieres differentes) sont en conflit', function () {
    $niveau = Niveau::factory()->create(['cycle' => 'secondaire']);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id]);
    $classeAnnee = ClasseAnnee::factory()->create(['classe_id' => $classe->id]);

    $matiere1 = Matiere::factory()->create();
    $matiere2 = Matiere::factory()->create();
    $enseignant1 = Enseignant::factory()->create();
    $enseignant2 = Enseignant::factory()->create();

    ClasseMatiere::create(['classe_annee_id' => $classeAnnee->id, 'matiere_id' => $matiere1->id, 'coefficient' => 1, 'enseignant_id' => $enseignant1->id]);
    ClasseMatiere::create(['classe_annee_id' => $classeAnnee->id, 'matiere_id' => $matiere2->id, 'coefficient' => 1, 'enseignant_id' => $enseignant2->id]);

    $creneau1 = CreneauHoraire::factory()->create(['heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);
    $creneau2 = CreneauHoraire::factory()->create(['heure_debut' => '09:00:00', 'heure_fin' => '11:00:00']);

    EmploiDuTemps::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere1->id,
        'jour_semaine' => 'lundi',
        'creneau_horaire_id' => $creneau1->id,
    ]);

    $conflit = app(EmploiDuTempsConflitService::class)
        ->detecter($classeAnnee->id, $matiere2->id, 'lundi', $creneau2);

    expect($conflit)->not->toBeNull()
        ->and($conflit)->toContain('classe');
});

test('deux creneaux qui se chevauchent mais classe et enseignant differents ne sont pas en conflit', function () {
    [$classeAnneeA, $matiereA] = creerClasseMatierePourEmploi();
    [$classeAnneeB, $matiereB] = creerClasseMatierePourEmploi();

    $creneau1 = CreneauHoraire::factory()->create(['heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);
    $creneau2 = CreneauHoraire::factory()->create(['heure_debut' => '09:00:00', 'heure_fin' => '11:00:00']);

    EmploiDuTemps::create([
        'classe_annee_id' => $classeAnneeA->id,
        'matiere_id' => $matiereA->id,
        'jour_semaine' => 'lundi',
        'creneau_horaire_id' => $creneau1->id,
    ]);

    $conflit = app(EmploiDuTempsConflitService::class)
        ->detecter($classeAnneeB->id, $matiereB->id, 'lundi', $creneau2);

    expect($conflit)->toBeNull();
});
