<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Uniforme>
 */
class UniformeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->unique()->words(2, true),
            'taille' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'prix' => fake()->randomElement([4000, 5000, 6000]),
            'quantite_stock' => fake()->numberBetween(5, 30),
        ];
    }
}
