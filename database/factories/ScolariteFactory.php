<?php

namespace Database\Factories;

use App\Models\ClasseAnnee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scolarite>
 */
class ScolariteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classe_annee_id' => ClasseAnnee::factory(),
            'montant_annuel' => fake()->randomElement([50000, 75000, 100000, 125000]),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
