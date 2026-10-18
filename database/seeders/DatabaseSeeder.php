<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Le jeu de démonstration complet (DemoSeeder) n'est volontairement PAS appelé ici : il se charge
     * à la demande, sur une base vide et jamais en production (voir README, « Données de démonstration ») :
     *   php artisan migrate:fresh --seed --seeder=DemoSeeder
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->raw(['name' => 'Test User'])
        );

        $this->call([
            RolesUsersSeeder::class,
            AnneeScolaireSeeder::class,
            NiveauClasseSeeder::class,
            PedagogieSeeder::class,
            EleveNoteSeeder::class,
            ScolaritePaiementSeeder::class,
            CantineOffreSeeder::class,
            UniformeSeeder::class,
        ]);
    }
}
