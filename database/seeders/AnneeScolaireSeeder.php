<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use Illuminate\Database\Seeder;

/**
 * Garantit la présence d'une année active ET d'une année PASSÉE, indispensable pour tester le
 * scoping historique (bulletins, API mobile) mis en place dans la phase "Fondations" — jusqu'ici
 * une seule année existait en base, rendant ce scoping impossible à valider manuellement.
 */
class AnneeScolaireSeeder extends Seeder
{
    public function run(): void
    {
        // Ne recrée pas l'année active existante si elle est déjà là (ex: "2025-2026" réelle).
        if (! AnneeScolaire::active()) {
            AnneeScolaire::create([
                'libelle' => '2025-2026',
                'date_debut' => '2025-09-01',
                'date_fin' => '2026-07-31',
                'est_active' => true,
            ]);
        }

        AnneeScolaire::firstOrCreate(
            ['libelle' => '2024-2025'],
            [
                'date_debut' => '2024-09-01',
                'date_fin' => '2025-07-31',
                'est_active' => false,
            ]
        );
    }
}
