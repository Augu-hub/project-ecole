<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Enseignant;
use App\Models\Parents;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('les enseignants peuvent se connecter avec leur telephone', function () {
    $enseignant = Enseignant::factory()->create(['telephone' => '0197000001']);

    $response = $this->post('/login', [
        'login' => '0197000001',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($enseignant->user);
    $response->assertRedirect(route('enseignant.dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('les parents sont orientes vers l application mobile', function () {
    $parent = Parents::factory()->create(['telephone' => '0197000002']);

    $this->post('/login', ['login' => '0197000002', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => LoginRequest::MESSAGE_PARENT]);

    $this->post('/login', ['login' => $parent->user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['login' => LoginRequest::MESSAGE_PARENT]);

    $this->assertGuest();
});

test('un parent avec un mauvais mot de passe recoit le message generique', function () {
    Parents::factory()->create(['telephone' => '0197000003']);

    $this->post('/login', ['login' => '0197000003', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => trans('auth.failed')]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
