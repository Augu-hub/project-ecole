<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;

/**
 * Initialise les tarifs cantine (ticket + petit-déjeuner, Septembre à Juin, primaire ET
 * secondaire séparément) pour une nouvelle année scolaire : reprend les montants de l'année
 * la plus récente déjà configurée pour ce cycle comme point de départ (l'admin peut ensuite
 * les ajuster via Admin > Cantine > Tarifs), ou les tarifs de référence sinon.
 */
class CantineTarifService
{
    private const TARIFS_TICKET_REFERENCE = [
        9 => 6000, 10 => 12000, 11 => 12000, 12 => 10000, 1 => 12000,
        2 => 10000, 3 => 12000, 4 => 10000, 5 => 12000, 6 => 10000,
    ];

    private const PRIX_PETIT_DEJEUNER_REFERENCE = 5000;

    private const CYCLES = ['primaire', 'secondaire'];

    public function creerTarifsParDefaut(AnneeScolaire $annee): void
    {
        foreach (self::CYCLES as $cycle) {
            $anneePrecedente = AnneeScolaire::where('id', '!=', $annee->id)
                ->where('date_debut', '<', $annee->date_debut)
                ->whereHas('cantineOffres', fn ($q) => $q->where('cycle', $cycle))
                ->orderByDesc('date_debut')
                ->first();

            $tarifsPrecedents = $anneePrecedente
                ? CantineOffre::where('annee_scolaire_id', $anneePrecedente->id)
                    ->where('cycle', $cycle)
                    ->get()
                    ->groupBy('mois')
                    ->map(fn ($group) => $group->keyBy('type'))
                : collect();

            foreach (CantineOffre::MOIS_ANNEE_SCOLAIRE as $mois) {
                $ticketPrecedent = $tarifsPrecedents->get($mois)?->get('ticket');
                $petitDejPrecedent = $tarifsPrecedents->get($mois)?->get('petit_dejeuner');

                CantineOffre::firstOrCreate(
                    ['annee_scolaire_id' => $annee->id, 'mois' => $mois, 'type' => 'ticket', 'cycle' => $cycle],
                    ['montant' => $ticketPrecedent?->montant ?? self::TARIFS_TICKET_REFERENCE[$mois]]
                );

                CantineOffre::firstOrCreate(
                    ['annee_scolaire_id' => $annee->id, 'mois' => $mois, 'type' => 'petit_dejeuner', 'cycle' => $cycle],
                    ['montant' => $petitDejPrecedent?->montant ?? self::PRIX_PETIT_DEJEUNER_REFERENCE]
                );
            }
        }
    }
}
