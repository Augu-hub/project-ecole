<?php

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use App\Models\User;

test('creer une annee scolaire initialise automatiquement 18 tarifs cantine', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $response = $this->actingAs($admin)->post(route('admin.annees-scolaires.store'), [
        'libelle' => '2030-2031',
        'date_debut' => '2030-09-01',
        'date_fin' => '2031-07-31',
    ]);

    $response->assertRedirect();

    $annee = AnneeScolaire::where('libelle', '2030-2031')->first();
    expect($annee)->not->toBeNull()
        ->and(CantineOffre::where('annee_scolaire_id', $annee->id)->count())->toBe(18);
});

test('les tarifs de la nouvelle annee reprennent ceux de l\'annee precedente la plus recente', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $anneePrecedente = AnneeScolaire::factory()->create(['date_debut' => '2029-09-01', 'date_fin' => '2030-07-31']);
    CantineOffre::create(['annee_scolaire_id' => $anneePrecedente->id, 'mois' => 9, 'type' => 'ticket', 'montant' => 7777]);
    CantineOffre::create(['annee_scolaire_id' => $anneePrecedente->id, 'mois' => 9, 'type' => 'petit_dejeuner', 'montant' => 5555]);

    $this->actingAs($admin)->post(route('admin.annees-scolaires.store'), [
        'libelle' => '2030-2031',
        'date_debut' => '2030-09-01',
        'date_fin' => '2031-07-31',
    ]);

    $annee = AnneeScolaire::where('libelle', '2030-2031')->first();
    $ticketSeptembre = CantineOffre::where('annee_scolaire_id', $annee->id)->where('mois', 9)->where('type', 'ticket')->first();

    expect((float) $ticketSeptembre->montant)->toBe(7777.0);
});
