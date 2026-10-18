<?php

use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Uniforme;
use App\Models\UniformeVente;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('le catalogue retourne les articles avec leur stock', function () {
    Uniforme::factory()->create(['type' => 'Chemise', 'taille' => 'M', 'prix' => 4000, 'quantite_stock' => 10]);

    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/uniformes/catalogue');
    $response->assertOk();

    $data = collect($response->json());
    expect($data->firstWhere('type', 'Chemise')['quantite_stock'])->toBe(10);
});

test('l\'historique d\'un eleve reflete le statut de paiement de chaque vente', function () {
    $eleve = Eleve::factory()->create();
    $uniforme = Uniforme::factory()->create(['prix' => 5000]);

    $ventePayee = UniformeVente::factory()->create([
        'uniforme_id' => $uniforme->id,
        'eleve_id' => $eleve->id,
        'quantite' => 1,
        'prix_unitaire' => 5000,
        'montant_total' => 5000,
    ]);
    Paiement::create([
        'eleve_id' => $eleve->id,
        'payable_type' => UniformeVente::class,
        'payable_id' => $ventePayee->id,
        'montant' => 5000,
        'date_paiement' => now()->toDateString(),
        'mode_paiement' => 'especes',
        'parent_id' => $eleve->parent_id,
    ]);

    $venteImpayee = UniformeVente::factory()->create([
        'uniforme_id' => $uniforme->id,
        'eleve_id' => $eleve->id,
    ]);

    $user = User::factory()->create(['role' => 'parent']);
    Sanctum::actingAs($user);

    $response = $this->getJson("/api/eleves/{$eleve->id}/uniformes/historique");
    $response->assertOk();

    $data = collect($response->json());
    expect($data->firstWhere('id', $ventePayee->id)['statut'])->toBe('paye')
        ->and($data->firstWhere('id', $venteImpayee->id)['statut'])->toBe('impaye');
});
