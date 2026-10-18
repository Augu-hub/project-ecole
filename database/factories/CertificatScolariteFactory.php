<?php

namespace Database\Factories;

use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificatScolarite>
 */
class CertificatScolariteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'classe_annee_id' => ClasseAnnee::factory(),
            'numero_unique' => 'CCPA-CERT-'.strtoupper(Str::random(8)),
            'motif' => fake()->optional()->sentence(),
            'genere_par_user_id' => User::factory()->state(['role' => 'admin']),
        ];
    }
}
