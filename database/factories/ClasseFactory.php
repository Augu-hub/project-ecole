<?php

namespace Database\Factories;

use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classe>
 */
class ClasseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'niveau_id' => Niveau::factory(),
            'suffixe' => fake()->randomElement(['A', 'B', 'C']),
        ];
    }
}
