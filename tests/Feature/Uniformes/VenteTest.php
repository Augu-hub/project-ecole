<?php

use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Niveau;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\Uniforme;
use App\Models\UniformeVente;
use App\Models\User;

function creerEleveSecondaireUniforme(): Eleve
{
    $niveau = Niveau::factory()->create(['cycle' => 'secondaire']);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id]);
    $classeAnnee = ClasseAnnee::factory()->create(['classe_id' => $classe->id]);
    $parent = Parents::factory()->create();

    return Eleve::factory()->create(['classe_annee_id' => $classeAnnee->id, 'parent_id' => $parent->id]);
}

test('une vente decremente le stock, meme au-dela de la quantite disponible', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $eleve = creerEleveSecondaireUniforme();
    $uniforme = Uniforme::factory()->create(['prix' => 5000, 'quantite_stock' => 2]);

    $this->actingAs($admin)->post(route('admin.uniformes.vendre', $eleve), [
        'uniforme_id' => $uniforme->id,
        'quantite' => 5,
    ])->assertRedirect();

    expect($uniforme->fresh()->quantite_stock)->toBe(-3);
});

test('une vente avec paiement immediat cree le paiement associe', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $eleve = creerEleveSecondaireUniforme();
    $uniforme = Uniforme::factory()->create(['prix' => 5000, 'quantite_stock' => 10]);

    $this->actingAs($admin)->post(route('admin.uniformes.vendre', $eleve), [
        'uniforme_id' => $uniforme->id,
        'quantite' => 2,
        'montant' => 10000,
        'mode_paiement' => 'especes',
    ])->assertRedirect();

    $vente = UniformeVente::where('eleve_id', $eleve->id)->first();

    expect($vente)->not->toBeNull()
        ->and($vente->montant_total)->toEqual(10000.0)
        ->and($vente->statut)->toBe('paye')
        ->and(Paiement::where('payable_type', UniformeVente::class)->where('payable_id', $vente->id)->exists())->toBeTrue();
});

test('une vente sans paiement reste impayee', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $eleve = creerEleveSecondaireUniforme();
    $uniforme = Uniforme::factory()->create(['prix' => 5000, 'quantite_stock' => 10]);

    $this->actingAs($admin)->post(route('admin.uniformes.vendre', $eleve), [
        'uniforme_id' => $uniforme->id,
        'quantite' => 1,
    ])->assertRedirect();

    $vente = UniformeVente::where('eleve_id', $eleve->id)->first();

    expect($vente->statut)->toBe('impaye');
});

test('payer() sur une vente existante complete progressivement le solde', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $eleve = creerEleveSecondaireUniforme();
    $uniforme = Uniforme::factory()->create(['prix' => 5000]);
    $vente = UniformeVente::factory()->create([
        'uniforme_id' => $uniforme->id,
        'eleve_id' => $eleve->id,
        'quantite' => 2,
        'prix_unitaire' => 5000,
        'montant_total' => 10000,
    ]);

    $this->actingAs($admin)->post(route('admin.uniformes.payer', $vente), [
        'montant' => 4000,
        'mode_paiement' => 'especes',
    ])->assertRedirect();

    expect($vente->fresh()->statut)->toBe('partiel');

    $this->actingAs($admin)->post(route('admin.uniformes.payer', $vente), [
        'montant' => 6000,
        'mode_paiement' => 'especes',
    ])->assertRedirect();

    expect($vente->fresh()->statut)->toBe('paye');
});
