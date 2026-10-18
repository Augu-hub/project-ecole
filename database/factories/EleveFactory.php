<?php

namespace Database\Factories;

use App\Models\ClasseAnnee;
use App\Models\Parents;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Eleve>
 */
class EleveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'matricule' => fake()->unique()->numerify('MAT-#####'),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'sexe' => fake()->randomElement(['M', 'F']),
            'date_naissance' => fake()->date(),
            'classe_annee_id' => ClasseAnnee::factory(),
            'parent_id' => Parents::factory(),
            'date_inscription' => fake()->date(),
            'statut' => 'actif',
        ];
    }
}
