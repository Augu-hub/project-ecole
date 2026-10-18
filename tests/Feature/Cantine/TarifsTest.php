<?php

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use App\Models\User;

test('l\'enregistrement tabulaire cree les tarifs ticket et petit-dejeuner pour les mois soumis', function () {
    $annee = AnneeScolaire::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $this->actingAs($admin)->post(route('admin.cantine.tarifs.enregistrer'), [
        'annee_scolaire_id' => $annee->id,
        'ticket' => [9 => 6000, 10 => 12000],
        'petit_dejeuner' => [9 => 5000, 10 => 5000],
    ])->assertRedirect();

    expect(CantineOffre::where('annee_scolaire_id', $annee->id)->count())->toBe(4);

    $septembreTicket = CantineOffre::where('annee_scolaire_id', $annee->id)
        ->where('mois', 9)->where('type', 'ticket')->first();
    expect((float) $septembreTicket->montant)->toBe(6000.0);
});

test('resoumettre le formulaire met a jour les montants existants sans dupliquer', function () {
    $annee = AnneeScolaire::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $this->actingAs($admin)->post(route('admin.cantine.tarifs.enregistrer'), [
        'annee_scolaire_id' => $annee->id,
        'ticket' => [9 => 6000],
        'petit_dejeuner' => [9 => 5000],
    ]);

    $this->actingAs($admin)->post(route('admin.cantine.tarifs.enregistrer'), [
        'annee_scolaire_id' => $annee->id,
        'ticket' => [9 => 7000],
        'petit_dejeuner' => [9 => 5000],
    ]);

    expect(CantineOffre::where('annee_scolaire_id', $annee->id)->count())->toBe(2);

    $ticket = CantineOffre::where('annee_scolaire_id', $annee->id)->where('mois', 9)->where('type', 'ticket')->first();
    expect((float) $ticket->montant)->toBe(7000.0);
});

test('un montant vide n\'est pas enregistre', function () {
    $annee = AnneeScolaire::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $this->actingAs($admin)->post(route('admin.cantine.tarifs.enregistrer'), [
        'annee_scolaire_id' => $annee->id,
        'ticket' => [9 => 6000, 10 => ''],
        'petit_dejeuner' => [9 => 5000],
    ]);

    expect(CantineOffre::where('annee_scolaire_id', $annee->id)->where('mois', 10)->exists())->toBeFalse();
});
