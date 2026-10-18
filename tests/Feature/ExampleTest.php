<?php

use App\Models\User;

test("l'adresse du site mène directement à la connexion", function () {
    $this->get('/')->assertRedirect(route('login'));
});

test("l'adresse du site mène au tableau de bord du compte quand on est déjà connecté", function (string $role, string $route) {
    $user = User::factory()->create(['role' => $role, 'actif' => true]);

    $this->actingAs($user)->get('/')->assertRedirect(route($route));
    // L'adresse générique /dashboard suit la même règle
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route($route));
})->with([
    'administration' => ['admin', 'admin.dashboard'],
    'saisisseur' => ['saisisseur', 'admin.dashboard'],
    'enseignant' => ['enseignant', 'enseignant.dashboard'],
]);

test("les anciennes pages d'accueil n'existent plus", function (string $adresse) {
    $this->get($adresse)->assertNotFound();
})->with(['/index', '/connexion.html', '/parent/dashboard']);
