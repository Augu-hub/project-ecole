<?php

namespace App\Services;

use App\Models\ClasseAnnee;
use App\Models\Eleve;

/**
 * Génération/normalisation des matricules élèves. Extrait de ImportEleveController pour être
 * réutilisé par la création manuelle d'élève (EleveController) sans dupliquer la logique.
 */
class MatriculeService
{
    /**
     * Formate un matricule saisi/importé.
     * Format attendu : "24-0138"
     * Si le numéro après le tiret ne commence pas par "01", on l'ajoute.
     * Exemple : "24-138" → "24-010138"
     */
    public function formater(string $matricule): string
    {
        if (preg_match('/^(\d{2})-(\d+)$/', $matricule, $matches)) {
            $annee = $matches[1];
            $numero = $matches[2];

            if (! str_starts_with($numero, '01')) {
                $numero = '01'.ltrim($numero, '0');
                $matricule = $annee.'-'.str_pad($numero, 6, '0', STR_PAD_LEFT);
            }
        }

        return strtoupper($matricule);
    }

    /**
     * Génère un matricule automatique pour une classe_annee donnée.
     * Format : AA-01XXXX (ex: 25-010001)
     */
    public function genererPour(ClasseAnnee $classeAnnee, int $offset = 0): string
    {
        $annee = now()->format('y');
        $count = Eleve::where('classe_annee_id', $classeAnnee->id)->count();
        $numero = str_pad($count + $offset + 1, 4, '0', STR_PAD_LEFT);
        $matricule = $annee.'-01'.$numero;

        $i = 1;
        while (Eleve::where('matricule', $matricule)->exists()) {
            $matricule = $annee.'-01'.str_pad($count + $offset + $i, 4, '0', STR_PAD_LEFT);
            $i++;
        }

        return strtoupper($matricule);
    }

    /**
     * Génère un matricule unique en ajoutant un suffixe numérique à un matricule déjà pris.
     */
    public function genererUnique(string $base): string
    {
        $original = $base;
        $i = 1;
        while (Eleve::where('matricule', $base)->exists()) {
            $base = $original.'-'.str_pad($i, 2, '0', STR_PAD_LEFT);
            $i++;
        }

        return $base;
    }
}
