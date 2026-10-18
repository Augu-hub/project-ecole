<?php

namespace App\Services;

use App\Models\ClasseMatiere;
use App\Models\CreneauHoraire;
use App\Models\EmploiDuTemps;

/**
 * Détecte les conflits d'emploi du temps par CHEVAUCHEMENT réel d'horaires (et non par simple
 * égalité de créneau) : deux créneaux adjacents (ex. 8h-10h puis 10h-12h) ne sont jamais en
 * conflit, seul un chevauchement effectif (heure_debut_A < heure_fin_B ET heure_fin_A > heure_debut_B)
 * l'est.
 */
class EmploiDuTempsConflitService
{
    public function detecter(int $classeAnneeId, int $matiereId, string $jour, CreneauHoraire $creneau, ?int $excludeId = null): ?string
    {
        $enseignantId = ClasseMatiere::where('classe_annee_id', $classeAnneeId)
            ->where('matiere_id', $matiereId)
            ->value('enseignant_id');

        $creneauxChevauchants = CreneauHoraire::where('heure_debut', '<', $creneau->heure_fin)
            ->where('heure_fin', '>', $creneau->heure_debut)
            ->pluck('id');

        $candidats = EmploiDuTemps::where('jour_semaine', $jour)
            ->whereIn('creneau_horaire_id', $creneauxChevauchants)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get();

        foreach ($candidats as $candidat) {
            if ($candidat->classe_annee_id == $classeAnneeId) {
                return 'Cette classe a déjà un cours sur ce créneau (chevauchement horaire).';
            }

            if ($enseignantId && $candidat->classeMatiere()?->enseignant_id == $enseignantId) {
                return 'Cet enseignant est déjà occupé sur ce créneau (chevauchement horaire).';
            }
        }

        return null;
    }
}
