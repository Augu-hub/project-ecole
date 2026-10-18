<?php

namespace Database\Seeders\Demo;

use App\Models\AnneeScolaire;
use App\Services\CantineTarifService;
use Database\Seeders\Demo\Data\Curriculum;
use Database\Seeders\Demo\Support\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Référentiel : années scolaires et trimestres, niveaux, classes (ouvertes sur les deux années),
 * matières, types de notes, créneaux horaires et tarifs de la cantine.
 */
class ReferentielSeeder extends Seeder
{
    /** Calendrier : libellé => [début, fin, active, [trimestres]]. */
    private const ANNEES = [
        'precedente' => ['2024-2025', '2024-09-01', '2025-07-31', false, [
            ['Trimestre 1', '2024-09-16', '2024-12-20'],
            ['Trimestre 2', '2025-01-06', '2025-03-28'],
            ['Trimestre 3', '2025-04-14', '2025-06-27'],
        ]],
        'active' => ['2025-2026', '2025-09-01', '2026-07-31', true, [
            ['Trimestre 1', '2025-09-15', '2025-12-19'],
            ['Trimestre 2', '2026-01-05', '2026-03-27'],
            ['Trimestre 3', '2026-04-13', '2026-06-26'],
        ]],
    ];

    public function __construct(private DemoContext $ctx) {}

    public function run(): void
    {
        DB::transaction(function () {
            $this->annees();
            $this->niveauxEtClasses();
            $this->matieres();
            $this->typesDeNotes();
            $this->creneaux();
            $this->cantine();
        });

        $this->command?->info(sprintf(
            '  %d classes sur 2 années, %d matières, %d trimestres.',
            count($this->ctx->classes), count($this->ctx->matieres), array_sum(array_map('count', $this->ctx->periodes))
        ));
    }

    private function annees(): void
    {
        foreach (self::ANNEES as $cle => [$libelle, $debut, $fin, $active, $trimestres]) {
            $creeLe = date('Y-m-d H:i:s', strtotime($debut.' -45 days 09:12:00'));
            $id = DB::table('annee_scolaires')->insertGetId([
                'libelle' => $libelle, 'date_debut' => $debut, 'date_fin' => $fin, 'est_active' => $active,
                'created_at' => $creeLe, 'updated_at' => $creeLe,
            ]);
            $this->ctx->annees[$cle] = ['id' => $id, 'libelle' => $libelle, 'debut' => $debut, 'fin' => $fin];

            foreach ($trimestres as [$nom, $tDebut, $tFin]) {
                $pid = DB::table('periodes')->insertGetId([
                    'annee_scolaire_id' => $id, 'nom' => $nom, 'date_debut' => $tDebut, 'date_fin' => $tFin,
                    'created_at' => $creeLe, 'updated_at' => $creeLe,
                ]);
                $this->ctx->periodes[$id][] = ['id' => $pid, 'nom' => $nom, 'debut' => $tDebut, 'fin' => $tFin];
            }
        }
    }

    private function niveauxEtClasses(): void
    {
        $creeLe = '2024-07-15 10:00:00';
        $salleCollege = 101;
        $salleLycee = 201;

        foreach (Curriculum::NIVEAUX as $nom => [$ordre, , $classes]) {
            $niveauId = DB::table('niveaux')->insertGetId([
                'nom' => $nom, 'cycle' => 'secondaire', 'ordre' => $ordre, 'created_at' => $creeLe, 'updated_at' => $creeLe,
            ]);

            foreach ($classes as $suffixe => [$profil, $lv2, $effectif]) {
                $classeId = DB::table('classes')->insertGetId([
                    'niveau_id' => $niveauId, 'suffixe' => $suffixe, 'created_at' => $creeLe, 'updated_at' => $creeLe,
                ]);
                $salle = Curriculum::estLycee($nom) ? 'Salle '.$salleLycee++ : 'Salle '.$salleCollege++;
                $cle = $nom.'|'.$suffixe;

                $this->ctx->classes[$cle] = [
                    'classe_id' => $classeId, 'niveau' => $nom, 'niveau_id' => $niveauId, 'ordre' => $ordre,
                    'suffixe' => $suffixe, 'profil' => $profil, 'lv2' => $lv2, 'effectif' => $effectif, 'salle' => $salle,
                ];

                foreach ($this->ctx->annees as $annee) {
                    $caCree = date('Y-m-d H:i:s', strtotime($annee['debut'].' -40 days 11:00:00'));
                    $this->ctx->classeAnnees[$annee['id']][$cle] = DB::table('classe_annees')->insertGetId([
                        'classe_id' => $classeId, 'annee_scolaire_id' => $annee['id'], 'created_at' => $caCree, 'updated_at' => $caCree,
                    ]);
                }
            }
        }
    }

    private function matieres(): void
    {
        foreach (Curriculum::MATIERES as $code => [$nom, $description]) {
            $this->ctx->matieres[$code] = DB::table('matieres')->insertGetId([
                'nom_matiere' => $nom, 'description' => $description,
                'created_at' => '2024-07-15 10:30:00', 'updated_at' => '2024-07-15 10:30:00',
            ]);
        }
    }

    /**
     * Une ligne « Interrogation » (jusqu'à 5 par trimestre, pré-moyennées), une ligne « Devoir »
     * (2 par trimestre) et une « Composition » : exactement les cases du bordereau de saisie.
     * La migration des types crée déjà « Composition » (code COMPO) sur une base vide.
     */
    private function typesDeNotes(): void
    {
        $types = [
            'INT' => ['nom' => 'Interrogation', 'max_par_periode' => 5, 'pre_moyenner' => true, 'groupe_moyenne' => null],
            'DEV' => ['nom' => 'Devoir', 'max_par_periode' => 2, 'pre_moyenner' => false, 'groupe_moyenne' => null],
            'COMPO' => ['nom' => 'Composition', 'max_par_periode' => 1, 'pre_moyenner' => false, 'groupe_moyenne' => null],
        ];

        foreach ($types as $code => $valeurs) {
            DB::table('type_notes')->updateOrInsert(
                ['code' => $code],
                $valeurs + ['created_at' => '2024-07-15 10:45:00', 'updated_at' => '2024-07-15 10:45:00']
            );
            $this->ctx->typesNotes[$code] = (int) DB::table('type_notes')->where('code', $code)->value('id');
        }
    }

    private function creneaux(): void
    {
        foreach (Curriculum::CRENEAUX as $ordre => [$debut, $fin]) {
            DB::table('creneaux_horaires')->insert([
                'heure_debut' => $debut, 'heure_fin' => $fin, 'ordre' => $ordre,
                'created_at' => '2024-08-20 09:00:00', 'updated_at' => '2024-08-20 09:00:00',
            ]);
        }
    }

    /**
     * Tarifs cantine des deux années, générés par le service de l'application (le même que lors de
     * la création d'une année), l'année active reprenant les tarifs de la précédente.
     */
    private function cantine(): void
    {
        $service = app(CantineTarifService::class);
        foreach (['precedente', 'active'] as $cle) {
            $service->creerTarifsParDefaut(AnneeScolaire::findOrFail($this->ctx->anneeId($cle)));

            $configureLe = date('Y-m-d H:i:s', strtotime($this->ctx->annees[$cle]['debut'].' -30 days 10:20:00'));
            DB::table('cantine_offres')->where('annee_scolaire_id', $this->ctx->anneeId($cle))
                ->update(['created_at' => $configureLe, 'updated_at' => $configureLe]);
        }

        // Hausse de 1 000 FCFA du ticket mensuel à la rentrée 2025 (hors septembre, mois incomplet).
        DB::table('cantine_offres')
            ->where('annee_scolaire_id', $this->ctx->anneeId('active'))
            ->where('type', 'ticket')->where('mois', '!=', 9)
            ->update(['montant' => DB::raw('montant + 1000')]);
    }
}
