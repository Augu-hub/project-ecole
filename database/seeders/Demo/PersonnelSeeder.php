<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Data\BeninNames;
use Database\Seeders\Demo\Data\Curriculum;
use Database\Seeders\Demo\Support\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Personnel : direction, censorat, économat, préfecture de discipline (préfet + surveillants),
 * secrétariat/saisie des notes, puis le corps enseignant (≈ 12 h hebdomadaires chacun, plusieurs classes) et
 * l'affectation enseignant + coefficient de chaque matière de chaque classe, sur les deux années.
 */
class PersonnelSeeder extends Seeder
{
    /**
     * Comptes nominatifs : [clé, email (null = généré), rôle, prénom, nom].
     * Les emails fixes sont les comptes de démonstration documentés dans le README.
     */
    private const EQUIPE = [
        ['admin', 'admin@ccpa.test', 'admin', 'Romaric', 'HOUNKPATIN'],
        ['directeur', 'directeur@ccpa.test', 'directeur', 'Père Jean-Baptiste', 'AHOUANDJINOU'],
        ['censeur', 'censeur@ccpa.test', 'censeur', 'Gérard', 'KPADONOU'],
        ['censeur2', null, 'censeur', 'Hortense', 'ZINSOU'],
        ['econome', 'econome@ccpa.test', 'econome', 'Marie-Claire', 'ADJOVI'],
        ['comptable', 'comptable@ccpa.test', 'econome', 'Fiacre', 'DOSSOU'],
        ['prefet', 'prefet@ccpa.test', 'prefet', 'Frère Augustin', 'TOSSOU'],
        ['surveillant', 'surveillant@ccpa.test', 'surveillant', 'Kossi', 'AMOUSSOU'],
        ['surveillant2', null, 'surveillant', 'Bertin', 'HOUNNOU'],
        ['surveillant3', null, 'surveillant', 'Clarisse', 'AKAKPO'],
        ['surveillant4', null, 'surveillant', 'Ulrich', 'SOSSA'],
        ['surveillant5', null, 'surveillant', 'Rachidi', 'OSSENI'],
        ['surveillant6', null, 'surveillant', 'Edwige', 'DEGBEY'],
        ['saisisseur', 'saisisseur@ccpa.test', 'saisisseur', 'Pélagie', 'HOUNSOU'],
        ['saisisseur2', null, 'saisisseur', 'Félicité', 'AGOSSOU'],
    ];

    /** Charge horaire visée par enseignant (heures/semaine). */
    private const CHARGE_VISEE = 12;

    public function __construct(private DemoContext $ctx) {}

    public function run(): void
    {
        DB::transaction(function () {
            $this->equipe();
            $this->enseignants();
        });

        $this->command?->info(sprintf(
            '  %d membres du personnel administratif, %d enseignants.',
            count(self::EQUIPE), count($this->ctx->enseignants)
        ));
    }

    private function equipe(): void
    {
        $prefetId = null;

        foreach (self::EQUIPE as [$cle, $email, $role, $prenom, $nom]) {
            $email = $email ? $this->ctx->reserverEmail($email) : $this->ctx->email($prenom, $nom);
            $creeLe = $this->ctx->rng->heureOuvree($this->ctx->rng->date('2024-07-01', '2024-08-31'), 8, 17);

            $id = DB::table('users')->insertGetId($this->ctx->ligneUser(
                $prenom.' '.$nom, $email, $role, $creeLe,
                ['superieur_id' => $role === 'surveillant' ? $prefetId : null]
            ));

            if ($role === 'prefet') {
                $prefetId = $id;
            }

            $this->ctx->comptes[$cle] = $id;
            $this->ctx->personnel[$role][] = $id;
        }
    }

    private function enseignants(): void
    {
        $rng = $this->ctx->rng;

        // Volume horaire de chaque matière, classe par classe (dans l'ordre des niveaux)
        $parMatiere = [];
        foreach ($this->ctx->classes as $cle => $classe) {
            foreach (Curriculum::programmeDeClasse($classe['profil'], $classe['lv2']) as $code => [, $heures]) {
                if ($heures > 0) {
                    $parMatiere[$code][] = [$cle, $heures];
                }
            }
        }

        // Répartition : chaque enseignant prend des classes consécutives (mêmes niveaux) jusqu'à sa charge
        $affectations = []; // [cléClasse][code] => enseignant_id
        foreach ($parMatiere as $code => $classes) {
            $total = array_sum(array_column($classes, 1));
            $nombre = max(1, (int) round($total / self::CHARGE_VISEE));
            $plafond = (int) ceil($total / $nombre) + 1;

            $enseignantId = null;
            $charge = PHP_INT_MAX;
            foreach ($classes as [$cle, $heures]) {
                if ($charge + $heures > $plafond) {
                    $enseignantId = $this->creerEnseignant($code);
                    $charge = 0;
                }
                $affectations[$cle][$code] = $enseignantId;
                $charge += $heures;
            }
        }

        // Coefficients + enseignant pour chaque classe des deux années. La conduite est notée par le
        // professeur principal (l'enseignant de la matière dominante de la classe).
        $lignes = [];
        foreach ($this->ctx->classeAnnees as $anneeId => $classesDeLAnnee) {
            $creeLe = date('Y-m-d H:i:s', strtotime(($anneeId === $this->ctx->anneeId('active') ? '2025' : '2024').'-09-05 10:00:00'));
            foreach ($classesDeLAnnee as $cle => $caId) {
                $classe = $this->ctx->classes[$cle];
                $principal = $affectations[$cle][$this->matiereDominante($classe['profil'])];

                foreach (Curriculum::programmeDeClasse($classe['profil'], $classe['lv2']) as $code => [$coef, $heures]) {
                    $enseignantId = $code === 'COND' ? $principal : $affectations[$cle][$code];
                    $this->ctx->programmes[$caId][$code] = ['coef' => $coef, 'heures' => $heures, 'enseignant_id' => $enseignantId];
                    $lignes[] = [
                        'classe_annee_id' => $caId, 'matiere_id' => $this->ctx->matieres[$code], 'coefficient' => $coef,
                        'enseignant_id' => $enseignantId, 'created_at' => $creeLe, 'updated_at' => $creeLe,
                    ];
                }
            }
        }
        $this->ctx->inserer('classe_matieres', $lignes);
    }

    private function matiereDominante(string $profil): string
    {
        return match (true) {
            str_ends_with($profil, '-C'), str_ends_with($profil, '-D') => 'MATH',
            str_ends_with($profil, '-B') => 'ECO',
            default => 'FR',
        };
    }

    private function creerEnseignant(string $code): int
    {
        $rng = $this->ctx->rng;
        $estDemo = $code === 'MATH' && ! isset($this->ctx->comptes['enseignant']);

        if ($estDemo) {
            [$prenom, $nom, $email] = ['Mathieu', 'GBAGUIDI', $this->ctx->reserverEmail('enseignant@ccpa.test')];
        } else {
            $femme = $rng->chance(0.34);
            $prenom = $rng->pick($femme ? BeninNames::PRENOMS_F : BeninNames::PRENOMS_M);
            if ($rng->chance(0.25)) {
                $prenom .= ' '.$rng->pick($femme ? BeninNames::PRENOMS_LOCAUX_F : BeninNames::PRENOMS_LOCAUX_M);
            }
            $nom = $rng->pick(BeninNames::NOMS);
            $email = $this->ctx->email($prenom, $nom);
        }

        $creeLe = $rng->heureOuvree($rng->date('2024-08-01', '2024-09-10'), 8, 17);
        $userId = DB::table('users')->insertGetId($this->ctx->ligneUser($prenom.' '.$nom, $email, 'enseignant', $creeLe));
        $matiere = Curriculum::MATIERES[$code][0];

        $id = DB::table('enseignants')->insertGetId([
            'user_id' => $userId, 'nom' => $nom, 'prenom' => $prenom, 'telephone' => $this->ctx->telephone(),
            'matiere_principale' => $matiere, 'created_at' => $creeLe, 'updated_at' => $creeLe,
        ]);

        if ($estDemo) {
            $this->ctx->comptes['enseignant'] = $userId;
        }
        $this->ctx->enseignants[$id] = ['user_id' => $userId, 'nom' => $nom, 'prenom' => $prenom, 'matiere' => $code];

        return $id;
    }
}
