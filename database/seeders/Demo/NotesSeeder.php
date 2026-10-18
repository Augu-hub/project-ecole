<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Data\Curriculum;
use Database\Seeders\Demo\Support\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Notes d'une année scolaire : pour chaque classe, matière et trimestre, une composition (toujours
 * notée), un devoir dans les matières principales et, pour l'année active, une interrogation (une note de conduite par trimestre), saisies par
 * l'enseignant affecté à la classe, à des dates plausibles. Les notes sont insérées dans l'ordre
 * chronologique des évaluations (le tableau de bord lit les dernières saisies par identifiant).
 *
 * Modèle de note : niveau propre de l'élève + affinité par matière + difficulté de la matière +
 * difficulté de l'épreuve + progression sur l'année + bruit, borné à [0 ; 20] et arrondi au demi
 * ou au quart de point. Quelques absences (note manquante, jamais comptée comme 0).
 *
 * Les moyennes (matière, générale pondérée, annuelle) sont calculées au fil de l'eau avec
 * EXACTEMENT la formule de BulletinService : la campagne de réinscription s'en sert pour décider
 * des passages et redoublements.
 */
class NotesSeeder extends Seeder
{
    private const COMMENTAIRES_FAIBLES = [
        'Travail insuffisant, revoir les bases.', 'Leçons non apprises.', 'Copie inachevée.',
        'Beaucoup d\'erreurs de calcul.', 'Hors sujet partiel.', 'Doit fournir plus d\'efforts.',
    ];

    private const COMMENTAIRES_BONS = [
        'Excellent travail, félicitations.', 'Très bonne copie.', 'Travail soigné et rigoureux.',
        'Bonne maîtrise du cours.', 'Continue ainsi !', 'Raisonnement clair et bien rédigé.',
    ];

    /** @var array<int, array<int, array<string, array{0: float, 1: int, 2: float, 3: int}>>> acc[eleve][periode][matiere] */
    private array $acc = [];

    public function __construct(private DemoContext $ctx) {}

    public function run(string $annee): void
    {
        $anneeId = $this->ctx->anneeId($annee);
        $rng = $this->ctx->rng;
        $types = $this->ctx->typesNotes;

        // Élèves de chaque classe pour cette année (y compris ceux partis depuis)
        $effectifs = [];
        $tendance = [];
        foreach ($this->ctx->eleves as $id => $e) {
            if (isset($e['classes'][$anneeId])) {
                $effectifs[$e['classes'][$anneeId]][] = $id;
                $tendance[$id] = $rng->gauss(0, 0.35);
            }
        }

        $total = 0;
        $barre = $this->command?->getOutput()->createProgressBar(count($this->ctx->periodes[$anneeId]) * count($effectifs));
        $barre?->start();

        foreach ($this->ctx->periodes[$anneeId] as $pi => $periode) {
            $evaluations = $this->planifier($anneeId, $periode, $annee === 'precedente');

            DB::transaction(function () use ($evaluations, $effectifs, $tendance, $pi, $periode, $types, $anneeId, &$total, $barre, $rng) {
                $tampon = $this->ctx->tampon('notes');
                $finSaisie = date('Y-m-d', strtotime($periode['fin'].' +7 days'));
                $classesVues = [];

                foreach ($evaluations as $ev) {
                    [$date, $cle, $code, $type, $numero, $difficulte, $enseignantId] = $ev;
                    $caId = $this->ctx->classeAnnees[$anneeId][$cle];
                    $matiereId = $this->ctx->matieres[$code];
                    $typeId = $types[$type];

                    // Saisie quelques jours après l'épreuve
                    $saisie = min(date('Y-m-d', strtotime($date.' +'.$rng->int(1, 6).' days')), $finSaisie);
                    $saisieLe = $rng->heureOuvree($saisie, 9, 19);

                    foreach ($effectifs[$cle] ?? [] as $eleveId) {
                        $e = $this->ctx->eleves[$eleveId];
                        if (isset($e['depart'][$anneeId]) && $pi >= $e['depart'][$anneeId]) {
                            continue; // parti en cours d'année
                        }
                        if ($type !== 'COMPO' && $rng->chance(0.015)) {
                            continue; // absent (la composition est toujours notée : chaque matière garde une moyenne)
                        }

                        $valeur = $this->valeur($e, $code, $type, $difficulte, $tendance[$eleveId] * $pi);
                        $commentaire = null;
                        if ($type === 'COMPO' && $code !== 'COND' && $rng->chance(0.03)) {
                            $commentaire = $valeur < 8 ? $rng->pick(self::COMMENTAIRES_FAIBLES) : ($valeur >= 15 ? $rng->pick(self::COMMENTAIRES_BONS) : null);
                        }

                        $tampon->add([
                            'eleve_id' => $eleveId, 'classe_annee_id' => $caId, 'matiere_id' => $matiereId,
                            'periode_id' => $periode['id'], 'type_note_id' => $typeId, 'numero' => $numero,
                            'enseignant_id' => $enseignantId, 'valeur' => $valeur, 'commentaire' => $commentaire,
                            'created_at' => $saisieLe, 'updated_at' => $saisieLe,
                        ]);

                        $a = &$this->acc[$eleveId][$pi][$code];
                        $a ??= [0.0, 0, 0.0, 0];
                        if ($type === 'INT') {
                            $a[0] += $valeur;
                            $a[1]++;
                        } else {
                            $a[2] += $valeur;
                            $a[3]++;
                        }
                        unset($a);
                    }

                    if (! isset($classesVues[$cle]) && $type === 'COMPO') {
                        $classesVues[$cle] = true;
                        $barre?->advance();
                    }
                }

                $total += $tampon->flush();
            });
        }

        $barre?->finish();
        $this->command?->newLine();

        $this->calculerMoyennes($anneeId, $effectifs);

        $moyennes = $this->ctx->moyennesAnnuelles[$anneeId];
        $admis = count(array_filter($moyennes, fn ($m) => $m >= 10));
        $this->command?->info(sprintf(
            '  %s : %s notes ; moyenne annuelle des élèves %.2f, %d %% d\'admis (≥ 10).',
            $this->ctx->annees[$annee]['libelle'], number_format($total, 0, ',', ' '),
            array_sum($moyennes) / max(1, count($moyennes)), round(100 * $admis / max(1, count($moyennes)))
        ));
    }

    /**
     * Évaluations du trimestre pour toutes les classes, triées par date :
     * [date, classe, matière, type, numéro, difficulté de l'épreuve, enseignant].
     */
    private function planifier(int $anneeId, array $periode, bool $archive): array
    {
        $rng = $this->ctx->rng;
        $debut = strtotime($periode['debut']);
        $fin = strtotime($periode['fin']);
        $jours = ($fin - $debut) / 86400;
        $semaineCompo = $rng->int(5, 11); // compositions la ou les dernières semaines du trimestre

        $evaluations = [];
        foreach ($this->ctx->classeAnnees[$anneeId] as $cle => $caId) {
            foreach ($this->ctx->programmes[$caId] as $code => $matiere) {
                $ens = $matiere['enseignant_id'];

                if ($code === 'COND') {
                    $evaluations[] = [$this->jourOuvre($fin - 2 * 86400), $cle, $code, 'COMPO', 1, 0.0, $ens];

                    continue;
                }

                // Volume volontairement réduit (~30 000 notes sur les deux années) : année active =
                // une interrogation, un devoir dans les matières principales (coef ≥ 3) et la
                // composition ; année archivée = composition (+ devoir des matières principales),
                // ce qui suffit aux bulletins et aux décisions de passage.
                $principale = $matiere['coef'] >= 3 && ! in_array($code, ['EPS', 'INFO'], true);
                $nbInt = $archive ? 0 : 1;
                $nbDev = $principale ? 1 : 0;

                for ($j = 1; $j <= $nbInt; $j++) {
                    $t = $debut + 86400 * ($jours * (0.3 + 0.4 * ($j - 1) / max(1, $nbInt - 1)) + $rng->int(-4, 4));
                    $evaluations[] = [$this->jourOuvre((int) $t), $cle, $code, 'INT', $j, $rng->gauss(0, 0.9), $ens];
                }
                foreach (array_slice([0.42, 0.72], 0, $nbDev) as $j => $part) {
                    $t = $debut + 86400 * ($jours * ($nbDev === 1 ? 0.6 : $part) + $rng->int(-3, 3));
                    $evaluations[] = [$this->jourOuvre((int) $t), $cle, $code, 'DEV', $j + 1, $rng->gauss(0, 0.7), $ens];
                }
                $t = $fin - 86400 * ($semaineCompo + $rng->int(0, 4));
                $evaluations[] = [$this->jourOuvre((int) $t), $cle, $code, 'COMPO', 1, $rng->gauss(0, 0.5), $ens];
            }
        }

        usort($evaluations, fn ($a, $b) => $a[0] <=> $b[0]);

        return $evaluations;
    }

    /** Pas d'évaluation le dimanche (repoussée au lundi). */
    private function jourOuvre(int $timestamp): string
    {
        if ((int) date('w', $timestamp) === 0) {
            $timestamp += 86400;
        }

        return date('Y-m-d', $timestamp);
    }

    private function valeur(array $e, string $code, string $type, float $difficulte, float $tendance): float
    {
        $rng = $this->ctx->rng;

        if ($code === 'COND') {
            return DemoContext::note(14.6 + 1.7 * $e['comp'] + $rng->gauss(0, 0.8), 0.5);
        }

        if ($code === 'EPS') {
            return min(19.0, DemoContext::note(13.2 + 0.6 * $e['apt'] + 1.4 * $e['aff'][$code] + $tendance + $difficulte + $rng->gauss(0, 1.4), 0.5));
        }

        $base = 10.9 + 2.6 * $e['apt'] + 1.7 * $e['aff'][$code] + Curriculum::MATIERES[$code][2] + $tendance + $difficulte;

        return match ($type) {
            'INT' => DemoContext::note($base + 0.2 + $rng->gauss(0, 2.4), 0.5),
            'DEV' => DemoContext::note($base - 0.2 + $rng->gauss(0, 2.0)),
            default => DemoContext::note($base - 0.4 + $rng->gauss(0, 1.8)),
        };
    }

    /**
     * Moyennes par trimestre et annuelles, selon BulletinService : interrogations pré-moyennées en
     * une valeur, puis moyenne simple avec devoirs et composition ; moyenne générale pondérée par
     * les coefficients des matières notées ; moyenne annuelle = moyenne des trimestres.
     */
    private function calculerMoyennes(int $anneeId, array $effectifs): void
    {
        $periodes = $this->ctx->periodes[$anneeId];

        foreach ($effectifs as $cle => $ids) {
            $programme = $this->ctx->programmes[$this->ctx->classeAnnees[$anneeId][$cle]];

            foreach ($ids as $id) {
                $generales = [];
                foreach ($periodes as $pi => $periode) {
                    $sommePond = 0.0;
                    $sommeCoef = 0.0;
                    foreach ($programme as $code => $m) {
                        [$sInt, $nInt, $sAutres, $nAutres] = $this->acc[$id][$pi][$code] ?? [0.0, 0, 0.0, 0];
                        $pool = $sAutres;
                        $n = $nAutres;
                        if ($nInt > 0) {
                            $pool += round($sInt / $nInt, 2);
                            $n++;
                        }
                        if ($n === 0) {
                            continue;
                        }
                        $sommePond += round($pool / $n, 2) * $m['coef'];
                        $sommeCoef += $m['coef'];
                    }
                    if ($sommeCoef > 0) {
                        $generales[$periode['id']] = round($sommePond / $sommeCoef, 2);
                    }
                }

                $this->ctx->moyennesPeriodes[$anneeId][$id] = $generales;
                if ($generales) {
                    $this->ctx->moyennesAnnuelles[$anneeId][$id] = round(array_sum($generales) / count($generales), 2);
                }
            }
        }

        $this->acc = [];
    }
}
