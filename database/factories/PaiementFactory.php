<?php

namespace Database\Factories;

use App\Models\Eleve;
use App\Models\Parents;
use App\Models\Tranche;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Paiement>
 */
class PaiementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'tranche_id' => Tranche::factory(),
            'montant' => fake()->randomFloat(2, 5000, 50000),
            'date_paiement' => fake()->date(),
            'mode_paiement' => fake()->randomElement(['especes', 'mobile_money', 'virement', 'carte', 'cheque']),
            'reference' => fake()->optional()->bothify('REF-####??'),
            'parent_id' => Parents::factory(),
            'commentaire' => fake()->optional()->sentence(),
        ];
    }
}
