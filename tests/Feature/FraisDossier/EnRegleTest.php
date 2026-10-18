<?php

use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\FraisDossier;
use App\Models\Paiement;
use App\Models\Parents;

function totalPayeFraisDossier(FraisDossier $fraisDossier, int $eleveId): float
{
    return (float) Paiement::where('payable_type', FraisDossier::class)
        ->where('payable_id', $fraisDossier->id)
        ->where('eleve_id', $eleveId)
        ->sum('montant');
}

test('un eleve ayant paye l\'integralite du frais de dossier est en regle', function () {
    $classeAnnee = ClasseAnnee::factory()->create();
    $fraisDossier = FraisDossier::factory()->create(['classe_annee_id' => $classeAnnee->id, 'montant' => 10000]);
    $eleve = Eleve::factory()->create(['classe_annee_id' => $classeAnnee->id]);
    $parent = Parents::factory()->create();

    Paiement::create([
        'eleve_id' => $eleve->id,
        'payable_type' => FraisDossier::class,
        'payable_id' => $fraisDossier->id,
        'montant' => 10000,
        'date_paiement' => now()->toDateString(),
        'mode_paiement' => 'especes',
        'parent_id' => $parent->id,
    ]);

    expect(totalPayeFraisDossier($fraisDossier, $eleve->id))->toBeGreaterThanOrEqual($fraisDossier->montant);
});

test('un eleve avec un paiement partiel n\'est pas en regle', function () {
    $classeAnnee = ClasseAnnee::factory()->create();
    $fraisDossier = FraisDossier::factory()->create(['classe_annee_id' => $classeAnnee->id, 'montant' => 10000]);
    $eleve = Eleve::factory()->create(['classe_annee_id' => $classeAnnee->id]);
    $parent = Parents::factory()->create();

    Paiement::create([
        'eleve_id' => $eleve->id,
        'payable_type' => FraisDossier::class,
        'payable_id' => $fraisDossier->id,
        'montant' => 4000,
        'date_paiement' => now()->toDateString(),
        'mode_paiement' => 'especes',
        'parent_id' => $parent->id,
    ]);

    expect(totalPayeFraisDossier($fraisDossier, $eleve->id))->toBeLessThan((float) $fraisDossier->montant);
});
