<?php

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Niveau;
use App\Models\User;

test('consulter une annee archivee ne change pas l\'annee active', function () {
    $niveau = Niveau::factory()->create(['cycle' => 'secondaire']);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id]);

    $anneeArchivee = AnneeScolaire::factory()->create(['est_active' => false]);
    $anneeActive = AnneeScolaire::factory()->create(['est_active' => true]);

    $classeAnneeArchivee = ClasseAnnee::factory()->create(['classe_id' => $classe->id, 'annee_scolaire_id' => $anneeArchivee->id]);
    $eleve = Eleve::factory()->create(['classe_annee_id' => $classeAnneeArchivee->id, 'statut' => 'actif']);

    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $response = $this->actingAs($admin)->get(route('admin.eleves.index', [
        'annee_scolaire_id' => $anneeArchivee->id,
        'classe_annee_id' => $classeAnneeArchivee->id,
    ]));

    $response->assertOk()->assertSee($eleve->nom);

    expect(AnneeScolaire::active()->id)->toBe($anneeActive->id);
});
