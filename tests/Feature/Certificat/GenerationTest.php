<?php

use App\Models\CertificatScolarite;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Niveau;
use App\Models\User;

function creerEleveDansCycle(string $cycle, string $statut = 'actif'): Eleve
{
    $niveau = Niveau::factory()->create(['cycle' => $cycle]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id]);
    $classeAnnee = ClasseAnnee::factory()->create(['classe_id' => $classe->id]);

    return Eleve::factory()->create(['classe_annee_id' => $classeAnnee->id, 'statut' => $statut]);
}

test('la generation est refusee pour un eleve hors cycle secondaire', function () {
    $eleve = creerEleveDansCycle('primaire');
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $response = $this->actingAs($admin)->post(route('admin.certificats.store', $eleve), []);

    $response->assertRedirect();
    expect(CertificatScolarite::count())->toBe(0);
});

test('la generation est refusee pour un eleve inactif', function () {
    $eleve = creerEleveDansCycle('secondaire', 'inactif');
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $this->actingAs($admin)->post(route('admin.certificats.store', $eleve), []);

    expect(CertificatScolarite::count())->toBe(0);
});

test('la generation reussit pour un eleve secondaire actif et cree un numero unique', function () {
    $eleve = creerEleveDansCycle('secondaire');
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $response = $this->actingAs($admin)->post(route('admin.certificats.store', $eleve), [
        'motif' => 'Dossier de bourse',
    ]);

    $certificat = CertificatScolarite::where('eleve_id', $eleve->id)->first();

    expect($certificat)->not->toBeNull()
        ->and($certificat->numero_unique)->toStartWith('CCPA-CERT-')
        ->and($certificat->classe_annee_id)->toBe($eleve->classe_annee_id)
        ->and($certificat->motif)->toBe('Dossier de bourse')
        ->and($certificat->genere_par_user_id)->toBe($admin->id);

    $response->assertRedirect(route('admin.certificats.download', $certificat));
});
