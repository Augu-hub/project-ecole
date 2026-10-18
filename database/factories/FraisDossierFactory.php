<?php

namespace Database\Factories;

use App\Models\ClasseAnnee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FraisDossier>
 */
class FraisDossierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classe_annee_id' => ClasseAnnee::factory(),
            'montant' => fake()->randomElement([5000, 10000, 15000]),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
