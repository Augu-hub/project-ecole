<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Periode;
use App\Models\TypeNote;
use Illuminate\Database\Seeder;

/**
 * Périodes de l'année passée + coefficients/enseignant sur les classe_annees de TEST créées par
 * NiveauClasseSeeder. Ne touche jamais aux classe_matieres réelles déjà configurées (classes
 * Terminale C / Sixième A / Sixième B de l'année active), afin de ne rien modifier de la
 * configuration pédagogique réelle existante.
 */
class PedagogieSeeder extends Seeder
{
    public function run(): void
    {
        $francais = Matiere::firstOrCreate(['nom_matiere' => 'Français']);
        $maths = Matiere::firstOrCreate(['nom_matiere' => 'Mathématiques']);
        $typeInterro = TypeNote::firstOrCreate(['code' => 'INT'], ['nom' => 'Interrogation', 'max_par_periode' => 5]);
        $typeDevoir = TypeNote::firstOrCreate(['code' => 'DEV'], ['nom' => 'Devoir', 'max_par_periode' => 3]);

        $enseignantTest = Enseignant::whereHas('user', fn ($q) => $q->where('email', 'test.enseignant@ccpa.test'))->first();

        $anneePassee = AnneeScolaire::where('libelle', '2024-2025')->first();
        if ($anneePassee) {
            foreach (['Trimestre 1', 'Trimestre 2', 'Trimestre 3'] as $nom) {
                Periode::firstOrCreate(
                    ['annee_scolaire_id' => $anneePassee->id, 'nom' => $nom],
                    ['date_debut' => $anneePassee->date_debut, 'date_fin' => $anneePassee->date_fin]
                );
            }
        }

        // Classe_annees "de test" uniquement : celles des niveaux de test (maternelle/primaire)
        // et celles de l'année passée pour les niveaux réels (Terminale/Sixième).
        $classeAnneesTest = ClasseAnnee::whereHas('classe.niveau', function ($q) {
            $q->whereIn('nom', ['Petite Section', 'Moyenne Section', 'CP1', 'CE1']);
        })->orWhere(function ($q) use ($anneePassee) {
            if ($anneePassee) {
                $q->where('annee_scolaire_id', $anneePassee->id);
            }
        })->get();

        foreach ($classeAnneesTest as $classeAnnee) {
            foreach ([$francais->id => 2, $maths->id => 3] as $matiereId => $coefficient) {
                ClasseMatiere::firstOrCreate(
                    ['classe_annee_id' => $classeAnnee->id, 'matiere_id' => $matiereId],
                    ['coefficient' => $coefficient, 'enseignant_id' => $enseignantTest?->id]
                );
            }
        }
    }
}
