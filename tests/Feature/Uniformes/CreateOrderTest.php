<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('createOrder valide les champs requis', function () {
    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/uniformes/create-order', []);

    $response->assertStatus(422);
});

test('createOrder refuse un article inexistant dans le panier', function () {
    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/uniformes/create-order', [
        'eleve_id' => 999999,
        'items' => [
            ['uniforme_id' => 999999, 'quantite' => 1],
        ],
    ]);

    $response->assertStatus(422);
});

test('createOrder refuse une quantite invalide', function () {
    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $eleve = \App\Models\Eleve::factory()->create();
    $uniforme = \App\Models\Uniforme::factory()->create();

    $response = $this->postJson('/api/uniformes/create-order', [
        'eleve_id' => $eleve->id,
        'items' => [
            ['uniforme_id' => $uniforme->id, 'quantite' => 0],
        ],
    ]);

    $response->assertStatus(422);
});
