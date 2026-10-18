<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\Niveau;
use Illuminate\Database\Seeder;

/**
 * Catégorise les niveaux réels déjà en base (Terminale, Sixième — sans ambiguïté : secondaire),
 * et ajoute des niveaux de test pour couvrir maternelle/primaire (absents jusqu'ici), afin que
 * les futurs modules "secondaire uniquement" et le module cantine (tous cycles) soient testables.
 */
class NiveauClasseSeeder extends Seeder
{
    public function run(): void
    {
        // Catégorisation des niveaux réels existants : uniquement si non encore catégorisés
        // (ne jamais écraser une catégorisation déjà faite manuellement par un admin).
        Niveau::whereIn('nom', ['Terminale', 'Sixième'])
            ->whereNull('cycle')
            ->update(['cycle' => 'secondaire']);

        $niveauxTest = [
            ['nom' => 'Petite Section', 'cycle' => 'maternelle', 'ordre' => -3],
            ['nom' => 'Moyenne Section', 'cycle' => 'maternelle', 'ordre' => -2],
            ['nom' => 'CP1', 'cycle' => 'primaire', 'ordre' => 2],
            ['nom' => 'CE1', 'cycle' => 'primaire', 'ordre' => 3],
        ];

        foreach ($niveauxTest as $data) {
            $niveau = Niveau::firstOrCreate(['nom' => $data['nom']], $data);

            $classe = Classe::firstOrCreate(['niveau_id' => $niveau->id, 'suffixe' => 'A']);

            foreach (AnneeScolaire::all() as $annee) {
                ClasseAnnee::firstOrCreate([
                    'classe_id' => $classe->id,
                    'annee_scolaire_id' => $annee->id,
                ]);
            }
        }

        // Classes-années pour l'année PASSÉE sur les classes réelles existantes, pour pouvoir
        // y rattacher l'élève de démonstration "promu" (voir EleveNoteSeeder).
        $anneePassee = AnneeScolaire::where('libelle', '2024-2025')->first();
        if ($anneePassee) {
            foreach (Classe::whereHas('niveau', fn ($q) => $q->whereIn('nom', ['Terminale', 'Sixième']))->get() as $classe) {
                ClasseAnnee::firstOrCreate([
                    'classe_id' => $classe->id,
                    'annee_scolaire_id' => $anneePassee->id,
                ]);
            }
        }
    }
}
