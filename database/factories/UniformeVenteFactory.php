<?php

namespace Database\Factories;

use App\Models\Eleve;
use App\Models\Uniforme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UniformeVente>
 */
class UniformeVenteFactory extends Factory
{
    public function definition(): array
    {
        $prixUnitaire = fake()->randomElement([4000, 5000, 6000]);
        $quantite = fake()->numberBetween(1, 3);

        return [
            'uniforme_id' => Uniforme::factory(),
            'eleve_id' => Eleve::factory(),
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaire,
            'montant_total' => $prixUnitaire * $quantite,
            'date_vente' => now()->toDateString(),
        ];
    }
}
