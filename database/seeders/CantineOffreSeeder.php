<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use Illuminate\Database\Seeder;

/**
 * Tarifs réels de la cantine (ticket mensuel variable + petit-déjeuner fixe à 5.000 FCFA/mois)
 * pour l'année scolaire active. Idempotent (updateOrCreate) : peut être relancé sans dupliquer,
 * et sert aussi de référence pour la configuration en production.
 */
class CantineOffreSeeder extends Seeder
{
    private const TARIFS_TICKET = [
        9 => 6000,   // Septembre
        10 => 12000, // Octobre
        11 => 12000, // Novembre
        12 => 10000, // Décembre
        1 => 12000,  // Janvier
        2 => 10000,  // Février
        3 => 12000,  // Mars
        4 => 10000,  // Avril
        5 => 12000,  // Mai
    ];

    private const PRIX_PETIT_DEJEUNER = 5000;

    public function run(): void
    {
        $anneeActive = AnneeScolaire::active();

        if (! $anneeActive) {
            return;
        }

        foreach (self::TARIFS_TICKET as $mois => $prixTicket) {
            CantineOffre::updateOrCreate(
                ['annee_scolaire_id' => $anneeActive->id, 'mois' => $mois, 'type' => 'ticket'],
                ['montant' => $prixTicket]
            );

            CantineOffre::updateOrCreate(
                ['annee_scolaire_id' => $anneeActive->id, 'mois' => $mois, 'type' => 'petit_dejeuner'],
                ['montant' => self::PRIX_PETIT_DEJEUNER]
            );
        }
    }
}
