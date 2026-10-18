<?php

namespace Database\Factories;

use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Periode;
use App\Models\TypeNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note>
 */
class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'classe_annee_id' => ClasseAnnee::factory(),
            'matiere_id' => Matiere::factory(),
            'periode_id' => Periode::factory(),
            'type_note_id' => TypeNote::factory(),
            'enseignant_id' => Enseignant::factory(),
            'valeur' => fake()->randomFloat(2, 0, 20),
            'commentaire' => fake()->optional()->sentence(),
        ];
    }
}
