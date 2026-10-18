<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Periode>
 */
class PeriodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'nom' => 'Trimestre '.fake()->numberBetween(1, 3).' '.fake()->unique()->numerify('##'),
            'date_debut' => fake()->date(),
            'date_fin' => fake()->date(),
        ];
    }
}
