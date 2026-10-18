<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreneauHoraire>
 */
class CreneauHoraireFactory extends Factory
{
    public function definition(): array
    {
        static $ordre = 0;
        $ordre++;

        $heureDebut = 8 + ($ordre - 1) * 2;

        return [
            'heure_debut' => sprintf('%02d:00:00', $heureDebut),
            'heure_fin' => sprintf('%02d:00:00', $heureDebut + 2),
            'ordre' => $ordre,
        ];
    }
}
