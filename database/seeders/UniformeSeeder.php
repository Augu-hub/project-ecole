<?php

namespace Database\Seeders;

use App\Models\Uniforme;
use Illuminate\Database\Seeder;

/**
 * Catalogue de démonstration (secondaire) : aucun tarif réel n'a été communiqué pour ce module,
 * ces articles/prix/stocks sont indicatifs et à ajuster par l'admin via l'écran catalogue.
 */
class UniformeSeeder extends Seeder
{
    private const ARTICLES = [
        ['type' => 'Chemise garçon', 'taille' => 'S', 'prix' => 4000, 'quantite_stock' => 20],
        ['type' => 'Chemise garçon', 'taille' => 'M', 'prix' => 4000, 'quantite_stock' => 20],
        ['type' => 'Chemise garçon', 'taille' => 'L', 'prix' => 4500, 'quantite_stock' => 15],
        ['type' => 'Blouse fille', 'taille' => 'S', 'prix' => 4000, 'quantite_stock' => 20],
        ['type' => 'Blouse fille', 'taille' => 'M', 'prix' => 4000, 'quantite_stock' => 20],
        ['type' => 'Blouse fille', 'taille' => 'L', 'prix' => 4500, 'quantite_stock' => 15],
        ['type' => 'Pantalon', 'taille' => 'M', 'prix' => 6000, 'quantite_stock' => 15],
        ['type' => 'Pantalon', 'taille' => 'L', 'prix' => 6500, 'quantite_stock' => 15],
        ['type' => 'Jupe', 'taille' => 'M', 'prix' => 5500, 'quantite_stock' => 15],
        ['type' => 'Jupe', 'taille' => 'L', 'prix' => 6000, 'quantite_stock' => 15],
    ];

    public function run(): void
    {
        // firstOrCreate (pas updateOrCreate) : ne doit jamais écraser un stock déjà décrémenté
        // par des ventes réelles si le seeder est rejoué.
        foreach (self::ARTICLES as $article) {
            Uniforme::firstOrCreate(
                ['type' => $article['type'], 'taille' => $article['taille']],
                ['prix' => $article['prix'], 'quantite_stock' => $article['quantite_stock']]
            );
        }
    }
}
