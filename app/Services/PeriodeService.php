<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Periode;

/**
 * Initialise les 3 trimestres pour une nouvelle année scolaire — sans ça, le cahier de notes
 * (et tout ce qui dépend d'une période : saisie de notes, bulletins) n'a aucune période à
 * proposer et redirige systématiquement en arrière avec une erreur "Aucune période disponible".
 */
class PeriodeService
{
    private const NOMS = ['Trimestre 1', 'Trimestre 2', 'Trimestre 3'];

    public function creerPeriodesParDefaut(AnneeScolaire $annee): void
    {
        foreach (self::NOMS as $nom) {
            Periode::firstOrCreate(
                ['annee_scolaire_id' => $annee->id, 'nom' => $nom],
                ['date_debut' => $annee->date_debut, 'date_fin' => $annee->date_fin]
            );
        }
    }
}
