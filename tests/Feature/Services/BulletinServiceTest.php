<?php

use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Periode;
use App\Models\TypeNote;
use App\Services\BulletinService;

function fakeEleve(int $id): object
{
    return (object) ['id' => $id];
}

function fakeMatiere(int $id, float $coefficient): object
{
    return (object) ['id' => $id, 'coefficient' => $coefficient];
}

test('moyenne generale ponderee applique correctement les coefficients', function () {
    $service = new BulletinService;

    $eleves = collect([fakeEleve(1)]);
    $matieres = collect([fakeMatiere(10, 2), fakeMatiere(20, 4)]);
    $typesNotes = collect([(object) ['id' => 100]]);
    // eleve 1 : maths (coef 2) = 10, français (coef 4) = 16
    $notes = [1 => [10 => [100 => 10], 20 => [100 => 16]]];

    [$moyMatiere, $moyGenerale] = $service->calculerMoyennes($eleves, $matieres, $typesNotes, $notes, 'ponderee');

    // (10*2 + 16*4) / (2+4) = 84/6 = 14
    expect($moyGenerale[1])->toBe(14.0);
});

test('eleve sans note a une moyenne generale nulle', function () {
    $service = new BulletinService;

    $eleves = collect([fakeEleve(1)]);
    $matieres = collect([fakeMatiere(10, 2)]);
    $typesNotes = collect([(object) ['id' => 100]]);

    [$moyMatiere, $moyGenerale] = $service->calculerMoyennes($eleves, $matieres, $typesNotes, [], 'ponderee');

    expect($moyMatiere[1][10])->toBeNull()
        ->and($moyGenerale[1])->toBeNull();
});

test('les ex-aequo recoivent le meme rang et le rang suivant saute', function () {
    $service = new BulletinService;

    $eleves = collect([fakeEleve(1), fakeEleve(2), fakeEleve(3)]);
    // 1 et 2 ex-aequo à 15, 3 troisième à 10
    $moyGenerale = [1 => 15.0, 2 => 15.0, 3 => 10.0];

    $rangs = $service->calculerRangs($eleves, $moyGenerale, true);

    expect($rangs[1])->toBe(1)
        ->and($rangs[2])->toBe(1)
        ->and($rangs[3])->toBe(3);
});

test('le bulletin d\'une annee passee garde les coefficients de la classe de l\'epoque apres une promotion', function () {
    $service = new BulletinService;

    // Année N-1 : élève en 6ème, coefficient maths = 2
    $classeAnneeN1 = ClasseAnnee::factory()->create();
    $matiere = Matiere::factory()->create();
    ClasseMatiere::create([
        'classe_annee_id' => $classeAnneeN1->id,
        'matiere_id' => $matiere->id,
        'coefficient' => 2,
    ]);
    $periodeN1 = Periode::factory()->create(['annee_scolaire_id' => $classeAnneeN1->annee_scolaire_id]);
    $typeNote = TypeNote::factory()->create();

    $eleve = Eleve::factory()->create(['classe_annee_id' => $classeAnneeN1->id]);

    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_annee_id' => $classeAnneeN1->id,
        'matiere_id' => $matiere->id,
        'periode_id' => $periodeN1->id,
        'type_note_id' => $typeNote->id,
        'valeur' => 12,
    ]);

    // Année N : élève promu dans une nouvelle classe_annee, même matière mais coefficient = 4
    $classeAnneeN = ClasseAnnee::factory()->create();
    ClasseMatiere::create([
        'classe_annee_id' => $classeAnneeN->id,
        'matiere_id' => $matiere->id,
        'coefficient' => 4,
    ]);
    $eleve->update(['classe_annee_id' => $classeAnneeN->id]);

    // Le bulletin de l'année N-1 doit utiliser le coefficient de l'époque (2), pas celui de la classe actuelle (4).
    $bulletin = $service->bulletinComplet($eleve->fresh(), $classeAnneeN1->annee_scolaire_id, 'ponderee');

    expect($bulletin['classeAnnee']->id)->toBe($classeAnneeN1->id)
        ->and((float) $bulletin['matieres']->firstWhere('id', $matiere->id)->coefficient)->toBe(2.0)
        ->and($bulletin['moyGenPeriode'][$periodeN1->id])->toBe(12.0);
});
