<?php

use App\Models\CertificatScolarite;
use App\Models\Eleve;

test('un numero valide affiche les informations du certificat sans authentification', function () {
    $eleve = Eleve::factory()->create(['nom' => 'DOSSOU', 'prenom' => 'Marie']);
    $certificat = CertificatScolarite::factory()->create(['eleve_id' => $eleve->id]);

    $response = $this->get(route('certificats.verifier', $certificat->numero_unique));

    $response->assertOk();
    $response->assertSee('DOSSOU');
    $response->assertSee($certificat->numero_unique);
});

test('un numero inconnu affiche un message d\'echec', function () {
    $response = $this->get(route('certificats.verifier', 'CCPA-CERT-INCONNU'));

    $response->assertOk();
    $response->assertSee('Numéro invalide');
});
