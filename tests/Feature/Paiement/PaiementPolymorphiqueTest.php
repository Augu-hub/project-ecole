<?php

use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\Tranche;

test('un paiement de scolarite reste accessible via tranche() et via payable()', function () {
    $tranche = Tranche::factory()->create();
    $eleve = Eleve::factory()->create();
    $parent = Parents::factory()->create();

    $paiement = Paiement::create([
        'eleve_id' => $eleve->id,
        'tranche_id' => $tranche->id,
        'payable_type' => Tranche::class,
        'payable_id' => $tranche->id,
        'montant' => 10000,
        'date_paiement' => now()->toDateString(),
        'mode_paiement' => 'especes',
        'parent_id' => $parent->id,
    ]);

    expect($paiement->tranche->id)->toBe($tranche->id)
        ->and($paiement->payable)->not->toBeNull()
        ->and($paiement->payable->id)->toBe($tranche->id)
        ->and($paiement->payable_type)->toBe(Tranche::class);
});
