<?php

use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\CreneauHoraire;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('l\'api mobile retourne l\'emploi du temps groupe par jour et trie par creneau', function () {
    $niveau = Niveau::factory()->create(['cycle' => 'secondaire']);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id]);
    $classeAnnee = ClasseAnnee::factory()->create(['classe_id' => $classe->id]);
    $eleve = Eleve::factory()->create(['classe_annee_id' => $classeAnnee->id]);

    $matiere = Matiere::factory()->create(['nom_matiere' => 'Mathématiques']);
    $enseignant = Enseignant::factory()->create(['nom' => 'DOSSOU', 'prenom' => 'Jean']);
    ClasseMatiere::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'coefficient' => 1,
        'enseignant_id' => $enseignant->id,
    ]);

    $creneauMatin = CreneauHoraire::factory()->create(['heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);
    $creneauApresMidi = CreneauHoraire::factory()->create(['heure_debut' => '14:00:00', 'heure_fin' => '16:00:00']);

    // Créé dans le désordre pour vérifier le tri par heure de début.
    EmploiDuTemps::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'jour_semaine' => 'lundi',
        'creneau_horaire_id' => $creneauApresMidi->id,
        'salle' => 'B12',
    ]);
    EmploiDuTemps::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'jour_semaine' => 'lundi',
        'creneau_horaire_id' => $creneauMatin->id,
        'salle' => 'A01',
    ]);

    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->getJson("/api/eleves/{$eleve->id}/emploi-du-temps");

    $response->assertOk();
    $jours = collect($response->json());
    $lundi = $jours->firstWhere('jour', 'lundi');

    expect($lundi['creneaux'])->toHaveCount(2)
        ->and($lundi['creneaux'][0]['heure_debut'])->toBe('08:00')
        ->and($lundi['creneaux'][0]['salle'])->toBe('A01')
        ->and($lundi['creneaux'][0]['matiere'])->toBe('Mathématiques')
        ->and($lundi['creneaux'][0]['enseignant'])->toBe('DOSSOU Jean')
        ->and($lundi['creneaux'][1]['heure_debut'])->toBe('14:00');
});

test('l\'api mobile retourne un planning vide pour un eleve sans creneaux', function () {
    $eleve = Eleve::factory()->create();
    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->getJson("/api/eleves/{$eleve->id}/emploi-du-temps");

    $response->assertOk();
    $jours = collect($response->json());
    expect($jours->every(fn ($j) => empty($j['creneaux'])))->toBeTrue();
});
