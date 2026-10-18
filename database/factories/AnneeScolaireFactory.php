<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnneeScolaire>
 */
class AnneeScolaireFactory extends Factory
{
    public function definition(): array
    {
        static $year = 2020;
        $year++;

        return [
            'libelle' => ($year).'-'.($year + 1),
            'date_debut' => "{$year}-09-01",
            'date_fin' => ($year + 1).'-07-31',
            'est_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'est_active' => true,
        ]);
    }
}
