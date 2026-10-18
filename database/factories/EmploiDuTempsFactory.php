<?php

namespace Database\Factories;

use App\Models\ClasseAnnee;
use App\Models\CreneauHoraire;
use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmploiDuTemps>
 */
class EmploiDuTempsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classe_annee_id' => ClasseAnnee::factory(),
            'matiere_id' => Matiere::factory(),
            'jour_semaine' => fake()->randomElement(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']),
            'creneau_horaire_id' => CreneauHoraire::factory(),
            'salle' => fake()->optional()->bothify('Salle ??##'),
        ];
    }
}
