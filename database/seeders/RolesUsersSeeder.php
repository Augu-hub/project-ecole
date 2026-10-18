<?php

namespace Database\Seeders;

use App\Models\Enseignant;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Un compte de test par rôle, avec mot de passe connu ("password"), pour permettre le QA
 * manuel de chaque espace (admin, enseignant, parent, etc.) sans dépendre des comptes réels
 * dont on ne connaît pas les mots de passe. Idempotent (firstOrCreate par email) : ne duplique
 * jamais les comptes réels déjà en base.
 */
class RolesUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'directeur', 'censeur', 'econome', 'prefet', 'saisisseur', 'parent'];

        foreach ($roles as $role) {
            User::firstOrCreate(
                ['email' => "test.{$role}@ccpa.test"],
                [
                    'name' => 'Test '.ucfirst($role),
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'actif' => true,
                    'remember_token' => Str::random(10),
                    'email_verified_at' => now(),
                ]
            );
        }

        // Surveillant de test, rattaché à l'équipe du préfet de test.
        User::firstOrCreate(
            ['email' => 'test.surveillant@ccpa.test'],
            [
                'name' => 'Test Surveillant',
                'password' => Hash::make('password'),
                'role' => 'surveillant',
                'superieur_id' => User::where('email', 'test.prefet@ccpa.test')->value('id'),
                'actif' => true,
                'remember_token' => Str::random(10),
                'email_verified_at' => now(),
            ]
        );

        // Compte parent de test, avec fiche Parents associée (nécessaire pour l'app mobile).
        $parentUser = User::where('email', 'test.parent@ccpa.test')->first();
        Parents::firstOrCreate(
            ['user_id' => $parentUser->id],
            [
                'nom' => 'Test',
                'prenom' => 'Parent',
                'telephone' => '0100000001',
            ]
        );

        // Compte enseignant de test, avec fiche Enseignant CORRECTEMENT liée (contrairement à
        // l'unique enregistrement Enseignant réel déjà en base, orphelin d'un user role=parent).
        $enseignantUser = User::firstOrCreate(
            ['email' => 'test.enseignant@ccpa.test'],
            [
                'name' => 'Test Enseignant',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'actif' => true,
                'remember_token' => Str::random(10),
                'email_verified_at' => now(),
            ]
        );

        Enseignant::firstOrCreate(
            ['user_id' => $enseignantUser->id],
            [
                'nom' => 'Test',
                'prenom' => 'Enseignant',
                'telephone' => '0100000002',
                'matiere_principale' => 'Mathématiques',
            ]
        );
    }
}
