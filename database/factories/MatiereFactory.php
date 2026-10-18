<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Matiere>
 */
class MatiereFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom_matiere' => fake()->unique()->word(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
