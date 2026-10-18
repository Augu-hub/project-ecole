<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Niveau>
 */
class NiveauFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->word(),
            'ordre' => fake()->unique()->numberBetween(1, 100),
            'cycle' => fake()->randomElement(['maternelle', 'primaire', 'secondaire']),
        ];
    }
}
