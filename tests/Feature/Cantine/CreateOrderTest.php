<?php

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('createOrder refuse une selection ou toutes les offres sont deja payees', function () {
    $annee = AnneeScolaire::factory()->create(['est_active' => true]);
    $eleve = Eleve::factory()->create();
    $parent = Parents::factory()->create();
    $offre = CantineOffre::factory()->ticket()->create(['annee_scolaire_id' => $annee->id, 'montant' => 6000]);

    Paiement::create([
        'eleve_id' => $eleve->id,
        'payable_type' => CantineOffre::class,
        'payable_id' => $offre->id,
        'montant' => 6000,
        'date_paiement' => now()->toDateString(),
        'mode_paiement' => 'especes',
        'parent_id' => $parent->id,
    ]);

    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/cantine/create-order', [
        'eleve_id' => $eleve->id,
        'offre_ids' => [$offre->id],
    ]);

    $response->assertStatus(422);
    expect($response->json('success'))->toBeFalse();
});

test('createOrder valide les champs requis', function () {
    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/cantine/create-order', []);

    $response->assertStatus(422);
});
