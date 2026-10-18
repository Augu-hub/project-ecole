<?php

use App\Models\Uniforme;
use App\Models\UniformeVente;
use App\Models\User;

test('creer un article ajoute une ligne au catalogue', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $this->actingAs($admin)->post(route('admin.uniformes.store'), [
        'type' => 'Chemise garçon',
        'taille' => 'M',
        'prix' => 4000,
        'quantite_stock' => 20,
    ])->assertRedirect();

    expect(Uniforme::where('type', 'Chemise garçon')->where('taille', 'M')->exists())->toBeTrue();
});

test('un meme type et taille ne peut pas etre cree deux fois', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    Uniforme::factory()->create(['type' => 'Pantalon', 'taille' => 'L']);

    $this->actingAs($admin)->post(route('admin.uniformes.store'), [
        'type' => 'Pantalon',
        'taille' => 'L',
        'prix' => 6000,
        'quantite_stock' => 10,
    ])->assertSessionHasErrors('type');

    expect(Uniforme::where('type', 'Pantalon')->where('taille', 'L')->count())->toBe(1);
});

test('la suppression est refusee si des ventes existent deja pour cet article', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $uniforme = Uniforme::factory()->create();
    UniformeVente::factory()->create(['uniforme_id' => $uniforme->id]);

    $this->actingAs($admin)->delete(route('admin.uniformes.destroy', $uniforme))
        ->assertRedirect();

    expect(Uniforme::find($uniforme->id))->not->toBeNull();
});

test('la suppression fonctionne si aucune vente n\'existe', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $uniforme = Uniforme::factory()->create();

    $this->actingAs($admin)->delete(route('admin.uniformes.destroy', $uniforme))
        ->assertRedirect();

    expect(Uniforme::find($uniforme->id))->toBeNull();
});
