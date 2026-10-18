<?php

namespace Database\Seeders\Demo;

use App\Models\FraisDossier;
use App\Models\Tranche;
use Database\Seeders\Demo\Data\Curriculum;
use Database\Seeders\Demo\Support\DemoContext;
use Database\Seeders\Demo\Support\InsertBuffer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Finances : scolarité (frais d'écolage) de chaque classe des deux années, échéancier en trois
 * tranches, frais de dossier (inscription / réinscription) et paiements des familles.
 *
 * Écrit les paiements exactement comme l'application :
 * - scolarité : payable_type = Tranche et tranche_id renseigné en double écriture (même valeur que
 *   payable_id), comme Api\PaiementController::captureOrder ; un paiement FedaPay qui couvre
 *   plusieurs tranches est réparti tranche par tranche, sous la même référence « FEDA-<id> » et le
 *   commentaire « Paiement via FedaPay » ;
 * - frais de dossier : payable_type = FraisDossier, tranche_id = null, commentaire « Frais de
 *   dossier », comme Admin\FraisDossierController::payer. Un élève est « en règle » quand la somme
 *   de ses paiements atteint le montant du frais de dossier de sa classe.
 *
 * Le numéro de reçu est l'identifiant du paiement (pdf/recu_paiement : N° 00000123) : aucune
 * colonne de numérotation à remplir. La table ne garde pas l'agent qui a encaissé.
 *
 * Comportements simulés (profil « payeur » de la famille tiré par EffectifsSeeder) :
 * - 2024-2025 : année soldée ; un élève réinscrit a tout réglé avant sa réinscription (quitus de
 *   l'économat), quelques familles en difficulté parties sans solder la dernière tranche ;
 * - 2025-2026 : selon les tranches échues à la date du jour, ≈ 55 % d'élèves à jour, ≈ 30 % en
 *   versement partiel sur la dernière tranche échue, ≈ 10-15 % sans rien sur celle-ci (en retard) ;
 *   quelques boursiers (Caritas paroissiale) dont les 2e et 3e tranches sont prises en charge.
 * Aucun paiement daté après « aujourd'hui » ; espèces, chèque, carte et virements jamais un
 * dimanche (économat fermé, banques sans valeur ce jour-là) ; mobile money et FedaPay à toute heure.
 */
class FinanceSeeder extends Seeder
{
    private const TYPE_TRANCHE = Tranche::class;

    private const TYPE_FRAIS = FraisDossier::class;

    /** Frais d'écolage 2024-2025 par niveau (FCFA). */
    private const ECOLAGE = [
        'Sixième' => 150000,
        'Cinquième' => 150000,
        'Quatrième' => 160000,
        'Troisième' => 170000,
        'Seconde' => 190000,
        'Première' => 200000,
        'Terminale' => 215000,
    ];

    /** Travaux pratiques de laboratoire des séries scientifiques (C et D) au lycée. */
    private const SUPPLEMENT_SCIENTIFIQUE = 10000;

    /** Hausse votée pour la rentrée 2025 (conseil de gestion), par cycle. */
    private const HAUSSE_ACTIVE = ['college' => 5000, 'lycee' => 10000];

    /** Frais de dossier (inscription ou réinscription, assurance, carte scolaire), par cycle. */
    private const FRAIS_DOSSIER = ['college' => 15000, 'lycee' => 20000];

    /** Échéancier : [libellé, part du montant annuel, jour d'échéance (mois-jour), année +1 ?]. */
    private const ECHEANCIER = [
        ['1ère tranche', 0.40, '10-15', false],
        ['2e tranche', 0.35, '01-15', true],
        ['3e tranche', 0.25, '04-15', true],
    ];

    /** Situation de l'année active selon le profil payeur de la famille (poids). */
    private const SITUATIONS = [
        'exemplaire' => ['a_jour' => 90, 'partiel' => 10],
        'normal' => ['a_jour' => 40, 'partiel' => 60],
        'retard' => ['partiel' => 30, 'retard' => 70],
        'difficulte' => ['partiel' => 15, 'retard' => 85],
    ];

    private const OPERATEURS = ['MTN MoMo' => 62, 'Moov Money' => 30, 'Celtiis Cash' => 8];

    private const BANQUES = ['BOA-Bénin', 'Ecobank', 'Orabank', 'BIIC', 'SGB', 'UBA', 'NSIA Banque', 'Coris Bank'];

    /** @var array<int, array<int, array{scolarite_id: int, montant: float, tranches: list<array{id: int, libelle: string, montant: float, echeance: string}>, frais: array{id: int, montant: float}}>> grilles[anneeId][classe_annee_id] */
    private array $grilles = [];

    /** @var array<int, string> date (Y-m-d) de la réinscription / inscription 2025-2026, par élève */
    private array $reinscriptions = [];

    /** @var array<int, bool> identifiants de transaction FedaPay déjà attribués */
    private array $transactionsFedaPay = [];

    /** @var array<string, array<string, mixed>> */
    private array $stats = [];

    public function __construct(private DemoContext $ctx) {}

    public function run(): void
    {
        $actives = DB::table('reinscriptions')
            ->where('annee_scolaire_id', $this->ctx->anneeId('active'))
            ->get(['eleve_id', 'created_at']);
        foreach ($actives as $r) {
            $this->reinscriptions[(int) $r->eleve_id] = substr((string) $r->created_at, 0, 10);
        }

        DB::transaction(function () {
            foreach (['precedente', 'active'] as $cle) {
                $this->tarifs($cle);
                $this->paiements($cle);
            }
        });

        foreach ($this->stats as $libelle => $s) {
            $this->command?->info(sprintf(
                '  Scolarité %s : %d classes, %d élèves, %s FCFA attendus, %s FCFA encaissés (%d paiements dont %d via FedaPay) ; '
                .'tranches échues : %d à jour, %d en paiement partiel, %d en retard ; %d boursiers ; frais de dossier : %d en règle sur %d.',
                $libelle, $s['classes'], $s['eleves'], $this->fcfa($s['attendu']), $this->fcfa($s['encaisse']), $s['paiements'],
                $s['fedapay'], $s['a_jour'], $s['partiel'], $s['retard'], $s['boursiers'], $s['frais_en_regle'], $s['eleves']
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // GRILLE TARIFAIRE : SCOLARITÉS, TRANCHES, FRAIS DE DOSSIER
    // ─────────────────────────────────────────────────────────────────

    private function tarifs(string $cle): void
    {
        $annee = $this->ctx->annees[$cle];
        $anneeId = $annee['id'];
        $anneeDebut = (int) substr($annee['debut'], 0, 4);
        $configureLe = $this->plusJours($annee['debut'], -32).' 09:40:00';

        foreach ($this->ctx->classeAnnees[$anneeId] ?? [] as $cleClasse => $caId) {
            $classe = $this->ctx->classes[$cleClasse];
            $lycee = Curriculum::estLycee($classe['niveau']);
            $cycle = $lycee ? 'lycee' : 'college';
            $scientifique = $lycee && preg_match('/-(C|D)$/', $classe['profil']) === 1;

            $montant = (self::ECOLAGE[$classe['niveau']] ?? 180000)
                + ($scientifique ? self::SUPPLEMENT_SCIENTIFIQUE : 0)
                + ($cle === 'active' ? self::HAUSSE_ACTIVE[$cycle] : 0);

            $nomClasse = trim($classe['niveau'].' '.$classe['suffixe']);
            $description = sprintf(
                'Frais d\'écolage %s — %s, payables en trois tranches (15 octobre, 15 janvier, 15 avril)%s.',
                $annee['libelle'], $nomClasse,
                $scientifique ? ', travaux pratiques de laboratoire compris' : ''
            );

            $scolariteId = DB::table('scolarites')->insertGetId([
                'classe_annee_id' => $caId, 'montant_annuel' => $montant, 'description' => $description,
                'created_at' => $configureLe, 'updated_at' => $configureLe,
            ]);

            // Répartition 40 / 35 / 25 % arrondie à 5 000 FCFA, la dernière tranche prenant le reste
            $tranches = [];
            $reste = $montant;
            foreach (self::ECHEANCIER as $i => [$libelle, $part, $jour, $anneeSuivante]) {
                $montantTranche = $i === count(self::ECHEANCIER) - 1
                    ? $reste
                    : (int) (round($montant * $part / 5000) * 5000);
                $reste -= $montantTranche;
                $echeance = ($anneeDebut + ($anneeSuivante ? 1 : 0)).'-'.$jour;

                $id = DB::table('tranches')->insertGetId([
                    'scolarite_id' => $scolariteId, 'libelle' => $libelle, 'date_echeance' => $echeance,
                    'montant' => $montantTranche, 'ordre' => $i + 1,
                    'created_at' => $configureLe, 'updated_at' => $configureLe,
                ]);
                $tranches[] = ['id' => $id, 'libelle' => $libelle, 'montant' => (float) $montantTranche, 'echeance' => $echeance];
            }

            $frais = self::FRAIS_DOSSIER[$cycle];
            $fraisId = DB::table('frais_dossiers')->insertGetId([
                'classe_annee_id' => $caId, 'montant' => $frais,
                'description' => 'Inscription ou réinscription '.$annee['libelle'].' : dossier, assurance scolaire, carte et livret scolaires',
                'created_at' => $configureLe, 'updated_at' => $configureLe,
            ]);

            $this->grilles[$anneeId][$caId] = [
                'scolarite_id' => $scolariteId, 'montant' => (float) $montant, 'tranches' => $tranches,
                'frais' => ['id' => $fraisId, 'montant' => (float) $frais],
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // PAIEMENTS
    // ─────────────────────────────────────────────────────────────────

    private function paiements(string $cle): void
    {
        $rng = $this->ctx->rng;
        $annee = $this->ctx->annees[$cle];
        $anneeId = $annee['id'];
        $activeId = $this->ctx->anneeId('active');
        $estActive = $cle === 'active';
        $aujourdhui = $this->ctx->aujourdhui;
        $periodes = $this->ctx->periodes[$anneeId];
        $anneeDebut = (int) substr($annee['debut'], 0, 4);

        $tampon = $this->ctx->tampon('paiements');
        $s = [
            'classes' => count($this->grilles[$anneeId] ?? []), 'eleves' => 0, 'attendu' => 0.0, 'encaisse' => 0.0,
            'paiements' => 0, 'fedapay' => 0, 'a_jour' => 0, 'partiel' => 0, 'retard' => 0, 'boursiers' => 0,
            'frais_en_regle' => 0,
        ];
        $demoVus = 0;

        foreach ($this->ctx->eleves as $eleveId => $e) {
            if (! isset($e['classes'][$anneeId])) {
                continue;
            }
            $caId = $this->ctx->classeAnnees[$anneeId][$e['classes'][$anneeId]] ?? null;
            $grille = $caId ? ($this->grilles[$anneeId][$caId] ?? null) : null;
            if (! $grille) {
                continue;
            }

            $famille = $this->ctx->familles[$e['famille']];
            $demo = (bool) ($e['demo'] ?? false);
            $payeur = $famille['payeur'] ?? 'normal';
            $s['eleves']++;
            $s['attendu'] += $grille['montant'];

            // Le parent principal règle le plus souvent ; le second parent, une année sur cinq
            $parentId = (int) $famille['principal_id'];
            if (count($famille['parents']) > 1 && ! $demo && $rng->chance(0.2)) {
                $parentId = (int) $famille['parents'][1]['id'];
            }

            // Ouverture du dossier de l'année : inscription (nouvel élève) ou réinscription
            if (($e['nouveau_en'] ?? null) === $anneeId) {
                $ouverture = $e['inscription'];
            } elseif ($estActive) {
                $ouverture = $this->reinscriptions[$eleveId] ?? $annee['debut'];
            } else {
                $ouverture = $rng->date($anneeDebut.'-08-19', $anneeDebut.'-09-13');
            }
            $ouverture = $this->ouvrable(min($ouverture, $aujourdhui));
            if ($ouverture > $aujourdhui) {
                $ouverture = $aujourdhui;
            }

            // Départ en cours d'année : plus rien n'est dû après la fin du trimestre de départ
            $finScolarite = isset($e['depart'][$anneeId]) ? $periodes[$e['depart'][$anneeId] - 1]['fin'] : null;

            // Date limite de règlement : la réinscription suivante exige le solde (quitus) ;
            // un élève qui ne revient pas a jusqu'à la fin de l'année, parfois sans solder.
            $reinscritEnSuivante = ! $estActive && isset($e['classes'][$activeId]) && isset($this->reinscriptions[$eleveId]);
            $limite = $reinscritEnSuivante ? $this->reinscriptions[$eleveId] : $annee['fin'];
            if ($finScolarite !== null) {
                $limite = min($limite, $this->plusJours($finScolarite, 10));
            }
            $limite = min($limite, $aujourdhui);

            // ── Frais de dossier ──
            $this->fraisDeDossier($tampon, $s, $eleveId, $parentId, $grille['frais'], $famille, $annee['libelle'], $ouverture, $estActive, $payeur, $demo);

            // ── Scolarité ──
            $tranches = $grille['tranches'];
            $applicables = [];
            foreach ($tranches as $k => $t) {
                if ($finScolarite === null || $t['echeance'] <= $finScolarite) {
                    $applicables[] = $k;
                }
            }

            $boursier = ! $demo && $finScolarite === null && $rng->chance($payeur === 'difficulte' ? 0.15 : 0.006);
            $s['boursiers'] += $boursier ? 1 : 0;

            // Tranches échues à la date du jour : 0 à 3
            $echues = 0;
            foreach ($tranches as $t) {
                $echues += $t['echeance'] <= $aujourdhui ? 1 : 0;
            }

            // Montant visé par tranche : 'plein', 'partiel' ou 'rien'
            $cibles = array_fill_keys($applicables, 'plein');
            if ($estActive) {
                $situation = $demo
                    ? ($demoVus++ === 0 ? 'a_jour' : 'partiel')
                    : (string) $rng->weighted(self::SITUATIONS[$payeur] ?? self::SITUATIONS['normal']);
                $L = $echues;
                foreach ($applicables as $k) {
                    $cibles[$k] = match (true) {
                        $L === 0, $k >= $L => 'plein', // pas encore échue : réglée seulement si la date est passée
                        $situation === 'a_jour' => 'plein',
                        $situation === 'partiel' => $k === $L - 1 ? 'partiel' : 'plein',
                        default => match (true) { // en retard : rien sur la dernière tranche échue
                            $k === $L - 1 => 'rien',
                            $k === $L - 2 => $rng->chance($payeur === 'difficulte' ? 0.8 : 0.5) ? 'partiel' : 'plein',
                            default => 'plein',
                        },
                    };
                    if ($L > 0 && $k >= $L && $situation !== 'a_jour') {
                        $cibles[$k] = 'rien'; // déjà en retard : n'anticipe pas la tranche suivante
                    }
                }
            } elseif (! $reinscritEnSuivante && $applicables) {
                // Parti en fin d'année : quelques familles en difficulté n'ont jamais soldé
                $derniere = end($applicables);
                if (($payeur === 'difficulte' && $rng->chance(0.5)) || ($payeur === 'retard' && $rng->chance(0.12))) {
                    $cibles[$derniere] = 'partiel';
                }
            }
            if ($boursier) {
                foreach ($cibles as $k => $c) {
                    if ($k >= 1) {
                        $cibles[$k] = 'bourse';
                    }
                }
            }

            // Versements : [k => montant] par transaction, avec date et note
            $transactions = $this->planifier($tranches, $cibles, $payeur, $ouverture, $limite, $estActive);

            $payeParTranche = [];
            foreach ($transactions as $tx) {
                if ($tx['date'] > $aujourdhui || $tx['date'] < $ouverture && ! $tx['bourse']) {
                    continue;
                }
                $this->ecrireTransaction($tampon, $s, $eleveId, $parentId, $tranches, $tx, $famille, $annee['libelle'], $estActive);
                foreach ($tx['parts'] as $k => $montant) {
                    $payeParTranche[$k] = ($payeParTranche[$k] ?? 0) + $montant;
                }
            }

            // Situation sur les tranches échues (comme l'application : reste > 0 et échéance passée)
            $retard = false;
            $partiel = false;
            foreach ($tranches as $k => $t) {
                if ($t['echeance'] > $aujourdhui) {
                    continue;
                }
                $paye = $payeParTranche[$k] ?? 0;
                if ($paye <= 0) {
                    $retard = true;
                } elseif ($paye < $t['montant']) {
                    $partiel = true;
                }
            }
            $s[$retard ? 'retard' : ($partiel ? 'partiel' : 'a_jour')]++;
        }

        $tampon->flush();
        $this->stats[$annee['libelle']] = $s;
    }

    /**
     * Calendrier des versements d'un élève pour l'année.
     *
     * @return list<array{date: string, parts: array<int, float>, note: string, bourse: bool, groupe: bool}>
     */
    private function planifier(array $tranches, array $cibles, string $payeur, string $ouverture, string $limite, bool $estActive): array
    {
        $rng = $this->ctx->rng;
        $aujourdhui = $this->ctx->aujourdhui;
        $tx = [];

        // Règlement de l'année entière (ou des deux premières tranches) dès l'inscription
        $pleines = array_keys(array_filter($cibles, fn ($c) => $c === 'plein'));
        if ($payeur === 'exemplaire' && count($pleines) >= 2 && $pleines[0] === 0 && $rng->chance(0.22)) {
            $groupe = $rng->chance(0.6) ? $pleines : array_slice($pleines, 0, 2);
            $date = $this->borner($rng->date($ouverture, $this->plusJours($ouverture, 12)), $ouverture, $limite);
            $parts = [];
            foreach ($groupe as $k) {
                $parts[$k] = $tranches[$k]['montant'];
                unset($cibles[$k]);
            }
            $tx[] = ['date' => $date, 'parts' => $parts, 'note' => count($groupe) === count($tranches) ? ' — règlement annuel' : ' — règlement groupé des 1ère et 2e tranches','bourse' => false, 'groupe' => true];
        }

        foreach ($cibles as $k => $cible) {
            $t = $tranches[$k];
            $d = $t['echeance'];
            $m = $t['montant'];
            $echue = $d <= $aujourdhui;

            if ($cible === 'rien') {
                continue;
            }

            if ($cible === 'bourse') {
                // Virement de la Caritas paroissiale, reçu un peu avant l'échéance
                $date = $this->plusJours($d, -$rng->int(0, 20));
                $tx[] = ['date' => max($date, $ouverture), 'parts' => [$k => $m], 'note' => ' — bourse de la Caritas paroissiale (prise en charge)', 'bourse' => true, 'groupe' => false];

                continue;
            }

            if ($cible === 'partiel') {
                $total = $this->arrondi($m * $rng->between(0.3, 0.7), 5000, $m - 5000);
                $versements = $payeur === 'difficulte' && $total >= 10000 ? $rng->int(1, 2) : 1;
                $date = $this->plusJours($d, $rng->int(-10, 25));
                for ($v = 0; $v < $versements; $v++) {
                    $montant = $v === $versements - 1 ? $total : $this->arrondi($total / 2, 5000, $total - 5000);
                    $total -= $montant;
                    $tx[] = ['date' => $this->dateAuPlusTard($date, $ouverture, $limite, $d, $echue), 'parts' => [$k => $montant], 'note' => $v === 0 ? ' — acompte' : ' — 2e acompte', 'bourse' => false, 'groupe' => false];
                    $date = $this->plusJours($date, $rng->int(12, 35));
                }

                continue;
            }

            // Tranche réglée en entier
            [$date, $deuxFois, $troisFois] = match ($payeur) {
                'exemplaire' => [$this->plusJours($d, -$rng->int(8, 40)), false, false],
                'normal' => [$this->plusJours($d, $rng->int(-12, 20)), $rng->chance(0.25), false],
                'retard' => [$this->plusJours($d, $rng->int(15, 70)), $rng->chance(0.4), false],
                default => [$this->plusJours($d, $rng->int(-5, 40)), true, $m >= 45000 && $rng->chance(0.5)],
            };

            // Payée en entier : jamais après la date limite, et forcément déjà payée si échue (année active)
            if (! $deuxFois) {
                $tx[] = ['date' => $this->dateAuPlusTard($date, $ouverture, $limite, $d, $echue && $estActive), 'parts' => [$k => $m], 'note' => '', 'bourse' => false, 'groupe' => false];

                continue;
            }

            $n = $troisFois ? 3 : 2;
            $restant = $m;
            for ($v = 0; $v < $n; $v++) {
                $montant = $v === $n - 1 ? $restant : $this->arrondi($m * ($n === 2 ? $rng->between(0.45, 0.65) : $rng->between(0.3, 0.4)), 5000, $restant - 5000);
                $restant -= $montant;
                $note = match (true) {
                    $v === $n - 1 => ' — solde',
                    $v === 0 => ' — 1er versement',
                    default => ' — 2e versement',
                };
                $tx[] = ['date' => $this->dateAuPlusTard($date, $ouverture, $limite, $d, $echue && $estActive), 'parts' => [$k => $montant], 'note' => $note, 'bourse' => false, 'groupe' => false];
                $date = $this->plusJours($date, $rng->int(10, 40));
            }
        }

        // L'économat (comme FedaPay, cf. calculerRepartition) impute dans l'ordre des tranches : un
        // versement sur une tranche n'est jamais antérieur au dernier versement des tranches
        // précédentes. Le plancher est une date déjà bornée, donc toujours valide.
        usort($tx, fn ($a, $b) => [min(array_keys($a['parts'])), $a['date']] <=> [min(array_keys($b['parts'])), $b['date']]);
        $plancher = null;
        foreach ($tx as $i => $versement) {
            if ($plancher !== null && $versement['date'] < $plancher) {
                $tx[$i]['date'] = $plancher;
            }
            $plancher = max($plancher ?? $tx[$i]['date'], $tx[$i]['date']);
        }

        // Une même tranche peut avoir des versements ramenés au même jour : ordre chronologique
        usort($tx, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $tx;
    }

    /**
     * Borne une date de versement : jamais avant l'ouverture du dossier ni après la date limite.
     * Si le versement doit exister à la date du jour (tranche échue de l'année active) mais tombe
     * dans le futur, il est ramené entre l'échéance (-10 j) et aujourd'hui.
     */
    private function dateAuPlusTard(string $date, string $ouverture, string $limite, string $echeance, bool $obligatoire): string
    {
        $aujourdhui = $this->ctx->aujourdhui;
        if ($obligatoire && $date > $aujourdhui) {
            $date = $this->ctx->rng->date(max($ouverture, $this->plusJours($echeance, -10)), $aujourdhui);
        }

        return $this->borner($date, $ouverture, $limite);
    }

    private function borner(string $date, string $min, string $max): string
    {
        if ($date > $max) {
            // Tassement juste avant la limite plutôt que tous le même jour
            $date = $this->ctx->rng->date(max($min, $this->plusJours($max, -15)), $max);
        }

        return max($date, $min);
    }

    private function ecrireTransaction(InsertBuffer $tampon, array &$s, int $eleveId, int $parentId, array $tranches, array $tx, array $famille, string $libelleAnnee, bool $estActive): void
    {
        $rng = $this->ctx->rng;
        $canal = $tx['bourse'] ? 'bourse' : $this->canal($famille, $estActive, $tx);
        $date = in_array($canal, ['especes', 'cheque', 'carte', 'virement', 'bourse'], true) ? $this->ouvrable($tx['date']) : $tx['date'];
        if ($date > $this->ctx->aujourdhui) {
            $date = $tx['date'];
        }

        $operateur = (string) $rng->weighted(self::OPERATEURS);
        [$mode, $reference, $suffixe] = match ($canal) {
            'fedapay' => ['mobile_money', 'FEDA-'.$this->transactionFedaPay($date), ''],
            'momo' => ['mobile_money', $this->referenceMobile($operateur, $date), ' — '.$operateur],
            'cheque' => ['cheque', 'CHQ '.$rng->int(1000000, 9999999).' '.$rng->pick(self::BANQUES), ' — chèque'],
            'virement', 'bourse' => ['virement', 'VIR-'.str_replace('-', '', substr($date, 2)).'-'.$rng->code(6), $canal === 'virement' ? ' — virement bancaire' : ''],
            'carte' => ['carte', 'TPE-'.$rng->code(6, '0123456789'), ' — carte bancaire (TPE)'],
            default => ['especes', null, ''],
        };

        $horodatage = $canal === 'fedapay' ? $rng->heureOuvree($date, 6, 22) : $rng->heureOuvree($date, 7, 16);
        if ($canal === 'fedapay') {
            $s['fedapay']++;
        }

        foreach ($tx['parts'] as $k => $montant) {
            $t = $tranches[$k];
            $commentaire = $canal === 'fedapay'
                ? 'Paiement via FedaPay' // libellé écrit par Api\PaiementController::captureOrder
                : 'Scolarité '.$libelleAnnee.' — '.$t['libelle'].$tx['note'].$suffixe;

            $tampon->add([
                'eleve_id' => $eleveId,
                'tranche_id' => $t['id'],
                'payable_type' => self::TYPE_TRANCHE,
                'payable_id' => $t['id'],
                'montant' => $montant,
                'date_paiement' => $date,
                'mode_paiement' => $mode,
                'reference' => $reference,
                'parent_id' => $parentId,
                'recu_path' => null,
                'commentaire' => $commentaire,
                'created_at' => $horodatage,
                'updated_at' => $horodatage,
            ]);
            $s['paiements']++;
            $s['encaisse'] += $montant;
        }
    }

    /** Canal de paiement d'une transaction, d'après les habitudes de la famille. */
    private function canal(array $famille, bool $estActive, array $tx): string
    {
        $rng = $this->ctx->rng;
        $mode = $famille['mode'] ?? 'especes';

        // Petits acomptes et versements fractionnés : souvent en liquide au guichet de l'économat
        if (str_contains($tx['note'], 'acompte') && $rng->chance(0.5)) {
            return 'especes';
        }
        if ($mode !== 'especes' && $rng->chance(0.12)) {
            return 'especes';
        }

        return match ($mode) {
            'mobile_money' => ($famille['mobile'] ?? false) && $rng->chance($estActive ? 0.75 : 0.4) ? 'fedapay' : 'momo',
            'virement', 'cheque', 'carte' => $mode,
            default => 'especes',
        };
    }

    private function fraisDeDossier(InsertBuffer $tampon, array &$s, int $eleveId, int $parentId, array $frais, array $famille,
        string $libelleAnnee, string $ouverture, bool $estActive, string $payeur, bool $demo): void
    {
        $rng = $this->ctx->rng;
        $montant = $frais['montant'];

        // Année active : quelques dossiers non soldés (acompte seulement, ou rien) chez les familles en retard
        $etat = 'plein';
        if ($estActive && ! $demo) {
            $risque = match ($payeur) {
                'difficulte' => 0.45,
                'retard' => 0.18,
                'normal' => 0.02,
                default => 0.0,
            };
            if ($rng->chance($risque)) {
                $etat = $rng->chance(0.65) ? 'acompte' : 'rien';
            }
        }

        if ($etat === 'rien') {
            return;
        }
        if ($etat === 'acompte') {
            $montant = $this->arrondi($montant / 2, 5000, $montant - 5000);
        } else {
            $s['frais_en_regle']++;
        }

        // Réglés au guichet le jour de l'inscription, parfois par mobile money
        $momo = ($famille['mode'] ?? '') === 'mobile_money' && $rng->chance(0.45);
        $operateur = (string) $rng->weighted(self::OPERATEURS);
        $date = $momo ? $ouverture : $this->ouvrable($ouverture);
        if ($date > $this->ctx->aujourdhui) {
            $date = $ouverture;
        }
        $horodatage = $rng->heureOuvree($date, 7, 16);

        $tampon->add([
            'eleve_id' => $eleveId,
            'tranche_id' => null,
            'payable_type' => self::TYPE_FRAIS,
            'payable_id' => $frais['id'],
            'montant' => $montant,
            'date_paiement' => $date,
            'mode_paiement' => $momo ? 'mobile_money' : 'especes',
            'reference' => $momo ? $this->referenceMobile($operateur, $date) : null,
            'parent_id' => $parentId,
            'recu_path' => null,
            // Même libellé qu'Admin\FraisDossierController::payer, précisé de l'année
            'commentaire' => 'Frais de dossier — '.$libelleAnnee.($etat === 'acompte' ? ' — acompte' : '').($momo ? ' — '.$operateur : ''),
            'created_at' => $horodatage,
            'updated_at' => $horodatage,
        ]);
        $s['paiements']++;
        $s['encaisse'] += $montant;
    }

    // ─────────────────────────────────────────────────────────────────
    // OUTILS
    // ─────────────────────────────────────────────────────────────────

    /** Identifiant de transaction tel qu'affiché par l'opérateur sur le SMS de confirmation. */
    private function referenceMobile(string $operateur, string $date): string
    {
        $rng = $this->ctx->rng;

        return match ($operateur) {
            'MTN MoMo' => (string) $rng->int(1000000000, 9999999999),
            'Moov Money' => 'MP'.str_replace('-', '', substr($date, 2)).'.'.$rng->code(4, '0123456789').'.'.$rng->code(5, 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789'),
            default => 'CLT'.$rng->code(9, '0123456789'),
        };
    }

    /**
     * Identifiant de transaction FedaPay : croissant avec la date (≈ 1 800 transactions par jour
     * sur la plateforme), unique.
     */
    private function transactionFedaPay(string $date): int
    {
        $jours = intdiv(strtotime($date.' 00:00:00 UTC') - strtotime('2024-01-01 00:00:00 UTC'), 86400);
        do {
            $id = 28000000 + $jours * 1800 + $this->ctx->rng->int(0, 1799);
        } while (isset($this->transactionsFedaPay[$id]));
        $this->transactionsFedaPay[$id] = true;

        return $id;
    }

    /** Montant arrondi à l'inférieur au pas donné, borné à [pas, max]. */
    private function arrondi(float $montant, int $pas, float $max): float
    {
        $valeur = (float) (intdiv((int) $montant, $pas) * $pas);

        return max((float) $pas, min($valeur, max((float) $pas, $max)));
    }

    private function plusJours(string $date, int $jours): string
    {
        return gmdate('Y-m-d', strtotime($date.' 00:00:00 UTC') + 86400 * $jours);
    }

    /** L'économat est fermé le dimanche : report au lundi. */
    private function ouvrable(string $date): string
    {
        return gmdate('w', strtotime($date.' 00:00:00 UTC')) === '0' ? $this->plusJours($date, 1) : $date;
    }

    private function fcfa(float $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
