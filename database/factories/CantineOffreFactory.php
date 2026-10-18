<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CantineOffre>
 */
class CantineOffreFactory extends Factory
{
    public function definition(): array
    {
        static $mois = 0;
        $mois = $mois % 12 + 1;

        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'mois' => $mois,
            'type' => fake()->randomElement(['ticket', 'petit_dejeuner']),
            'montant' => fake()->randomElement([5000, 6000, 10000, 12000]),
        ];
    }

    public function ticket(): static
    {
        return $this->state(['type' => 'ticket']);
    }

    public function petitDejeuner(): static
    {
        return $this->state(['type' => 'petit_dejeuner']);
    }
}
