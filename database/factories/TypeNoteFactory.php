<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TypeNote>
 */
class TypeNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->word(),
            'code' => fake()->unique()->lexify('TN-????'),
            'max_par_periode' => fake()->numberBetween(1, 5),
        ];
    }
}
