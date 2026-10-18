<?php

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('une offre integralement payee est marquee payee dans l\'api mobile', function () {
    AnneeScolaire::factory()->create(['est_active' => false]);
    $annee = AnneeScolaire::factory()->create(['est_active' => true]);
    $eleve = Eleve::factory()->create();
    $parent = Parents::factory()->create();

    $offrePayee = CantineOffre::factory()->ticket()->create(['annee_scolaire_id' => $annee->id, 'mois' => 9, 'montant' => 6000]);
    $offreNonPayee = CantineOffre::factory()->petitDejeuner()->create(['annee_scolaire_id' => $annee->id, 'mois' => 9, 'montant' => 5000]);

    Paiement::create([
        'eleve_id' => $eleve->id,
        'payable_type' => CantineOffre::class,
        'payable_id' => $offrePayee->id,
        'montant' => 6000,
        'date_paiement' => now()->toDateString(),
        'mode_paiement' => 'especes',
        'parent_id' => $parent->id,
    ]);

    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->getJson("/api/eleves/{$eleve->id}/cantine");
    $response->assertOk();

    $data = collect($response->json());
    $ticket = $data->firstWhere('id', $offrePayee->id);
    $petitDej = $data->firstWhere('id', $offreNonPayee->id);

    expect($ticket['paye'])->toBeTrue()
        ->and($petitDej['paye'])->toBeFalse();
});

test('un paiement partiel ne marque pas l\'offre comme payee', function () {
    $annee = AnneeScolaire::factory()->create(['est_active' => true]);
    $eleve = Eleve::factory()->create();
    $parent = Parents::factory()->create();

    $offre = CantineOffre::factory()->ticket()->create(['annee_scolaire_id' => $annee->id, 'montant' => 12000]);

    Paiement::create([
        'eleve_id' => $eleve->id,
        'payable_type' => CantineOffre::class,
        'payable_id' => $offre->id,
        'montant' => 5000,
        'date_paiement' => now()->toDateString(),
        'mode_paiement' => 'especes',
        'parent_id' => $parent->id,
    ]);

    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->getJson("/api/eleves/{$eleve->id}/cantine");
    $data = collect($response->json());

    expect($data->firstWhere('id', $offre->id)['paye'])->toBeFalse();
});
