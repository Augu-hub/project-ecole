<?php

use App\Models\ConnexionLog;
use App\Models\Parents;
use App\Models\User;

test('une connexion parent reussie cree une ligne dans le journal', function () {
    $parent = Parents::factory()->create(['telephone' => '22990000001']);
    $user = User::find($parent->user_id);
    $user->update(['password' => bcrypt('secret123'), 'actif' => true]);

    $response = $this->postJson('/api/login', [
        'telephone' => '22990000001',
        'password' => 'secret123',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    expect(ConnexionLog::where('user_id', $user->id)->where('parent_id', $parent->id)->exists())->toBeTrue();
});

test('une connexion echouee ne cree pas de ligne dans le journal', function () {
    $parent = Parents::factory()->create(['telephone' => '22990000002']);
    $user = User::find($parent->user_id);
    $user->update(['password' => bcrypt('secret123'), 'actif' => true]);

    $this->postJson('/api/login', [
        'telephone' => '22990000002',
        'password' => 'wrong-password',
    ])->assertStatus(401);

    expect(ConnexionLog::where('user_id', $user->id)->exists())->toBeFalse();
});

test('le journal des connexions est accessible aux admins et filtrable par parent', function () {
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $parent = Parents::factory()->create(['nom' => 'DOSSOU', 'prenom' => 'Marie']);

    ConnexionLog::create([
        'user_id' => $parent->user_id,
        'parent_id' => $parent->id,
        'ip_address' => '10.0.0.5',
        'user_agent' => 'TestAgent',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.connexion-logs.index', ['q' => 'DOSSOU']));

    $response->assertOk()->assertSee('DOSSOU');
});

test('un enseignant n\'a pas acces au journal des connexions', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant', 'actif' => true]);

    $this->actingAs($enseignant)->get(route('admin.connexion-logs.index'))->assertForbidden();
});
