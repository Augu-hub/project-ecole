<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function prefet(): User
{
    return User::factory()->create(['role' => 'prefet', 'actif' => true]);
}

function surveillantDe(User $prefet, array $attributs = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'surveillant',
        'actif' => true,
        'superieur_id' => $prefet->id,
    ], $attributs));
}

test('le prefet ajoute un surveillant a son equipe', function () {
    $prefet = prefet();

    $this->actingAs($prefet)->get(route('admin.equipe.create'))->assertOk();

    $this->actingAs($prefet)
        ->post(route('admin.equipe.store'), ['name' => 'Paul HOUNSOU', 'email' => 'paul@ecole.bj', 'role' => 'admin'])
        ->assertRedirect(route('admin.equipe.index'));

    $membre = User::where('email', 'paul@ecole.bj')->first();
    expect($membre->role)->toBe('surveillant')
        ->and($membre->superieur_id)->toBe($prefet->id)
        ->and($membre->actif)->toBeTruthy();
});

test('le prefet ne voit que sa propre equipe', function () {
    $prefet = prefet();
    $autrePrefet = prefet();
    surveillantDe($prefet, ['name' => 'Surveillant De Mon Equipe']);
    surveillantDe($autrePrefet, ['name' => 'Surveillant Autre Equipe']);

    $this->actingAs($prefet)->get(route('admin.equipe.index'))
        ->assertOk()
        ->assertSee('Surveillant De Mon Equipe')
        ->assertDontSee('Surveillant Autre Equipe');
});

test('le prefet modifie, desactive et reinitialise un membre de son equipe', function () {
    $prefet = prefet();
    $membre = surveillantDe($prefet);

    $this->actingAs($prefet)->get(route('admin.equipe.edit', $membre))->assertOk();

    $this->actingAs($prefet)
        ->put(route('admin.equipe.update', $membre), ['name' => 'Nouveau Nom', 'email' => 'nouveau@ecole.bj'])
        ->assertRedirect(route('admin.equipe.index'));

    $membre->refresh();
    expect($membre->name)->toBe('Nouveau Nom')
        ->and($membre->actif)->toBeFalsy()
        ->and($membre->role)->toBe('surveillant');

    $this->actingAs($prefet)->post(route('admin.equipe.reset-password', $membre))->assertRedirect();
    expect(Hash::check('password123', $membre->fresh()->password))->toBeTrue();

    $this->actingAs($prefet)->delete(route('admin.equipe.destroy', $membre))->assertRedirect(route('admin.equipe.index'));
    expect(User::find($membre->id))->toBeNull();
});

test('le prefet ne peut pas gerer le surveillant d un autre prefet ni un autre compte', function () {
    $prefet = prefet();
    $autre = surveillantDe(prefet());
    $censeur = User::factory()->create(['role' => 'censeur', 'actif' => true]);

    foreach ([$autre, $censeur] as $compte) {
        $this->actingAs($prefet)->get(route('admin.equipe.edit', $compte))->assertForbidden();
        $this->actingAs($prefet)->put(route('admin.equipe.update', $compte), ['name' => 'X', 'email' => 'x@ecole.bj'])->assertForbidden();
        $this->actingAs($prefet)->post(route('admin.equipe.reset-password', $compte))->assertForbidden();
        $this->actingAs($prefet)->delete(route('admin.equipe.destroy', $compte))->assertForbidden();
    }

    expect(User::find($autre->id))->not->toBeNull()
        ->and(User::find($censeur->id))->not->toBeNull();
});

test('seul le prefet accede a la gestion d equipe', function (string $role) {
    $utilisateur = User::factory()->create(['role' => $role, 'actif' => true]);

    $this->actingAs($utilisateur)->get(route('admin.equipe.index'))->assertForbidden();
    $this->actingAs($utilisateur)->post(route('admin.equipe.store'), ['name' => 'X', 'email' => 'x@ecole.bj'])->assertForbidden();
})->with(['admin', 'censeur', 'directeur', 'surveillant']);

test('le surveillant a les memes acces que le prefet', function () {
    $surveillant = surveillantDe(prefet());

    $this->actingAs($surveillant)->get(route('admin.communiques.index'))->assertOk();
    $this->actingAs($surveillant)->get(route('admin.creneaux-horaires.index'))->assertOk();
    $this->actingAs($surveillant)->get(route('admin.personnel.index'))->assertForbidden();
    $this->actingAs($surveillant)->get(route('admin.eleves.index'))->assertForbidden();
});

test('supprimer un prefet detache son equipe sans supprimer les surveillants', function () {
    $prefet = prefet();
    $membre = surveillantDe($prefet);

    $prefet->delete();

    expect($membre->fresh())->not->toBeNull()
        ->and($membre->fresh()->superieur_id)->toBeNull();
});

test('l administration voit l equipe de rattachement et detache un membre qui change de role', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $prefet = prefet();
    $membre = surveillantDe($prefet, ['name' => 'Membre Rattache']);

    $this->actingAs($admin)->get(route('admin.personnel.index'))
        ->assertOk()
        ->assertSee('Équipe de '.$prefet->name);

    $this->actingAs($admin)->put(route('admin.personnel.update', $membre), [
        'name' => $membre->name, 'email' => $membre->email, 'role' => 'saisisseur', 'actif' => 1,
    ])->assertRedirect(route('admin.personnel.index'));

    expect($membre->fresh()->superieur_id)->toBeNull();
});
