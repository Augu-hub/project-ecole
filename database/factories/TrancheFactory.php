<?php

namespace Database\Factories;

use App\Models\Scolarite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tranche>
 */
class TrancheFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scolarite_id' => Scolarite::factory(),
            'libelle' => fake()->randomElement(['1ère tranche', '2ème tranche', '3ème tranche']),
            'date_echeance' => fake()->date(),
            'montant' => fake()->randomFloat(2, 10000, 50000),
            'ordre' => fake()->numberBetween(1, 3),
        ];
    }
}
