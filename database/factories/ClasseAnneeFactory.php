<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClasseAnnee>
 */
class ClasseAnneeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'annee_scolaire_id' => AnneeScolaire::factory(),
        ];
    }
}
