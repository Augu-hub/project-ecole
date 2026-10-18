<?php

namespace Database\Seeders;

use Database\Seeders\Demo\CantineSeeder;
use Database\Seeders\Demo\EffectifsSeeder;
use Database\Seeders\Demo\EmploiDuTempsSeeder;
use Database\Seeders\Demo\FinanceSeeder;
use Database\Seeders\Demo\NotesSeeder;
use Database\Seeders\Demo\PersonnelSeeder;
use Database\Seeders\Demo\ReferentielSeeder;
use Database\Seeders\Demo\Support\DemoContext;
use Database\Seeders\Demo\UniformesSeeder;
use Database\Seeders\Demo\VieScolaireSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Jeu de données de démonstration complet du Complexe Catholique Père Aupiais (CCPA) :
 * deux années scolaires (2024-2025 archivée, 2025-2026 active), 12 classes de la Sixième à la
 * Terminale, ~300 élèves par an, ~240 parents, ~27 enseignants, toutes les notes des trois
 * trimestres, la scolarité et ses paiements, la cantine, les uniformes, l'emploi du temps, les
 * certificats, les communiqués, la messagerie et le journal des connexions.
 *
 * OPT-IN uniquement (jamais appelé par DatabaseSeeder) et sur une base VIDE :
 *
 *     php artisan migrate:fresh --seed --seeder=DemoSeeder
 *
 * Refuse de s'exécuter en production. Données reproductibles (graine fixe), insérées en masse
 * sans événements de modèle (aucune notification, diffusion Reverb, push Firebase ni e-mail).
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoSeeder refuse de s\'exécuter en production : il génère des milliers d\'élèves, de parents et de notes fictifs.'
            );
        }

        if (DB::table('eleves')->exists() || DB::table('classes')->exists()) {
            throw new RuntimeException(
                'La base contient déjà des classes ou des élèves. Le jeu de démonstration se charge sur une base vide : '
                .'php artisan migrate:fresh --seed --seeder=DemoSeeder'
            );
        }

        $debut = microtime(true);
        DB::connection()->disableQueryLog();
        // Ceinture et bretelles : aucun listener (diffusion, notifications) pendant le chargement.
        Event::fake();

        $contexte = new DemoContext;
        $contexte->aujourdhui = now()->toDateString();
        app()->instance(DemoContext::class, $contexte);

        $this->call([
            ReferentielSeeder::class,   // années, trimestres, niveaux, classes, matières, types de notes, créneaux
            PersonnelSeeder::class,     // direction, vie scolaire, économat, enseignants, coefficients
        ]);

        // 2024-2025 : inscriptions, puis notes (les moyennes annuelles décident des passages)
        $this->callWith(EffectifsSeeder::class, ['phase' => 'precedente']);
        $this->callWith(NotesSeeder::class, ['annee' => 'precedente']);

        // 2025-2026 : campagne de réinscription, nouveaux élèves, puis notes
        $this->callWith(EffectifsSeeder::class, ['phase' => 'active']);
        $this->callWith(NotesSeeder::class, ['annee' => 'active']);

        $this->call([
            FinanceSeeder::class,       // scolarité, tranches, frais de dossier, paiements
            CantineSeeder::class,       // tarifs mensuels et paiements cantine
            UniformesSeeder::class,     // catalogue, ventes, paiements
            EmploiDuTempsSeeder::class, // emploi du temps de l'année active, sans conflit
            VieScolaireSeeder::class,   // certificats, communiqués, messagerie, connexions
        ]);

        $this->resume(microtime(true) - $debut);
    }

    private function resume(float $duree): void
    {
        $tables = [
            'users', 'parents', 'enseignants', 'eleves', 'eleve_parent', 'classes', 'classe_annees',
            'classe_matieres', 'periodes', 'notes', 'reinscriptions', 'scolarites', 'tranches',
            'frais_dossiers', 'paiements', 'cantine_offres', 'uniformes', 'uniforme_ventes',
            'emplois_du_temps', 'certificats_scolarite', 'communiques', 'conversations', 'messages',
            'connexion_logs',
        ];

        $this->command?->newLine();
        $this->command?->table(
            ['Table', 'Lignes'],
            array_map(fn ($t) => [$t, number_format(DB::table($t)->count(), 0, ',', ' ')], $tables)
        );
        $this->command?->info(sprintf('Jeu de démonstration chargé en %.0f s. Comptes : voir README (« Données de démonstration »), mot de passe « %s ».', $duree, DemoContext::MOT_DE_PASSE));
    }
}
