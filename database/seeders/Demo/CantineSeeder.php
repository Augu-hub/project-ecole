<?php

namespace Database\Seeders\Demo;

use App\Models\CantineOffre;
use Database\Seeders\Demo\Support\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cantine : demi-pensionnaires et paiements mensuels (ticket + petit-déjeuner éventuel) sur les
 * deux années scolaires.
 *
 * Les tarifs (cantine_offres) sont déjà créés par ReferentielSeeder via CantineTarifService, comme
 * lors de la création d'une année dans l'application : ce seeder ne fait que les LIRE (cycle
 * « secondaire », le seul du CCPA) et enregistre des paiements polymorphes
 * (payable_type = CantineOffre, tranche_id = null), exactement comme Admin\CantineController
 * (saisie à l'économat) et Api\CantineController (paiement FedaPay depuis l'application parent).
 *
 * Règles respectées :
 * - une offre est « payée » quand la somme des paiements de l'élève atteint son montant : quelques
 *   règlements en deux fois (soldés) et quelques acomptes non soldés (offre restant « non payée ») ;
 * - aucun paiement daté après « aujourd'hui » ; un mois n'est dû qu'une fois commencé ;
 * - un élève parti en cours d'année ne paie plus après la fin du trimestre de son départ ;
 * - 2024-2025 presque entièrement soldée (quelques impayés de fin d'année), 2025-2026 avec des
 *   retards sur les derniers mois écoulés ;
 * - jamais de paiement avant l'inscription de l'élève, ni d'espèces/chèque un dimanche ;
 * - volumes déduits des effectifs réellement chargés (≈ 30 % de demi-pensionnaires) ; la famille de
 *   démonstration (parent@ccpa.test) règle via FedaPay et garde le mois en cours à payer.
 */
class CantineSeeder extends Seeder
{
    private const TYPE_PAYABLE = CantineOffre::class;

    /** Part de demi-pensionnaires selon le cycle (les plus jeunes déjeunent davantage sur place). */
    private const PART_COLLEGE = 0.36;

    private const PART_LYCEE = 0.24;

    /** Un demi-pensionnaire de l'an dernier, toujours inscrit, reste à la cantine dans 75 % des cas. */
    private const FIDELITE = 0.75;

    /** Un élève réinscrit qui ne mangeait pas à la cantine l'an dernier s'y met rarement. */
    private const NOUVEL_ABONNE_ANCIEN = 0.4;

    /** Part des demi-pensionnaires qui prennent aussi le petit-déjeuner. */
    private const PART_PETIT_DEJEUNER = 0.38;

    private const OPERATEURS = ['MTN MoMo' => 62, 'Moov Money' => 30, 'Celtiis Cash' => 8];

    /** @var array<int, array<int, array<string, array{id: int, montant: float}>>> offres[anneeId][mois][type] */
    private array $offres = [];

    /** @var array<int, bool> demi-pensionnaires de l'année précédente */
    private array $anciensDemiPensionnaires = [];

    /** @var array<int, bool> identifiants de transaction FedaPay déjà attribués (scolarité comprise) */
    private array $transactionsFedaPay = [];

    /** @var array{0: ?string, 1: string} [référence, horodatage] de la dernière transaction FedaPay écrite */
    private array $derniereTransaction = [null, ''];

    /** Bornes des dates de paiement de l'élève en cours de traitement. */
    private string $plancher = '0000-01-01';

    private string $plafond = '9999-12-31';

    private array $stats = [];

    public function __construct(private DemoContext $ctx) {}

    public function run(): void
    {
        $this->chargerOffres();

        // Même compte marchand FedaPay que la scolarité (secondaire) : un identifiant de
        // transaction ne peut pas servir deux fois.
        foreach (DB::table('paiements')->where('reference', 'like', 'FEDA-%')->pluck('reference') as $ref) {
            if (preg_match('/(\d+)$/', (string) $ref, $m)) {
                $this->transactionsFedaPay[(int) $m[1]] = true;
            }
        }

        DB::transaction(function () {
            foreach (['precedente', 'active'] as $cle) {
                $this->annee($cle);
            }
        });

        foreach ($this->stats as $libelle => $s) {
            $this->command?->info(sprintf(
                '  Cantine %s : %d demi-pensionnaires (%d avec petit-déjeuner), %d paiements, %s FCFA encaissés, %d mois impayés.',
                $libelle, $s['abonnes'], $s['petit_dej'], $s['paiements'],
                number_format($s['encaisse'], 0, ',', ' '), $s['impayes']
            ));
        }
    }

    /** Tarifs déjà en base (secondaire), indexés par année, mois et type. */
    private function chargerOffres(): void
    {
        $lignes = DB::table('cantine_offres')
            ->whereIn('annee_scolaire_id', [$this->ctx->anneeId('precedente'), $this->ctx->anneeId('active')])
            ->where('cycle', 'secondaire')
            ->get(['id', 'annee_scolaire_id', 'mois', 'type', 'montant']);

        foreach ($lignes as $o) {
            $this->offres[(int) $o->annee_scolaire_id][(int) $o->mois][$o->type] = ['id' => (int) $o->id, 'montant' => (float) $o->montant];
        }

        foreach (['precedente', 'active'] as $cle) {
            if (empty($this->offres[$this->ctx->anneeId($cle)])) {
                throw new \RuntimeException("Aucun tarif cantine (secondaire) pour l'année {$this->ctx->annees[$cle]['libelle']} : ReferentielSeeder doit les créer.");
            }
        }
    }

    private function annee(string $cle): void
    {
        $rng = $this->ctx->rng;
        $annee = $this->ctx->annees[$cle];
        $anneeId = $annee['id'];
        $anneeDebut = (int) substr($annee['debut'], 0, 4);
        $periodes = $this->ctx->periodes[$anneeId];
        $rentree = $periodes[0]['debut'];
        $aujourdhui = $this->ctx->aujourdhui;
        $estActive = $cle === 'active';

        // Mois de l'année scolaire déjà commencés à la date du jour (et dont le tarif existe).
        $moisOuverts = [];
        foreach (CantineOffre::MOIS_ANNEE_SCOLAIRE as $mois) {
            $an = $mois >= 9 ? $anneeDebut : $anneeDebut + 1;
            $premier = sprintf('%04d-%02d-01', $an, $mois);
            if ($premier <= $aujourdhui && isset($this->offres[$anneeId][$mois]['ticket'])) {
                $moisOuverts[] = ['mois' => $mois, 'premier' => $premier, 'dernier' => date('Y-m-t', strtotime($premier))];
            }
        }
        if (! $moisOuverts) {
            return;
        }

        // Derniers mois écoulés : là où se concentrent les retards de l'année active.
        $nbOuverts = count($moisOuverts);
        $anneeTerminee = end($moisOuverts)['dernier'] < $aujourdhui && $nbOuverts === count(CantineOffre::MOIS_ANNEE_SCOLAIRE);

        $tampon = $this->ctx->tampon('paiements');
        $s = ['abonnes' => 0, 'petit_dej' => 0, 'paiements' => 0, 'encaisse' => 0.0, 'impayes' => 0];
        $demiPensionnaires = [];

        foreach ($this->ctx->eleves as $eleveId => $e) {
            if (! isset($e['classes'][$anneeId])) {
                continue;
            }
            $classe = $this->ctx->classes[$e['classes'][$anneeId]];
            $part = $classe['ordre'] <= 10 ? self::PART_COLLEGE : self::PART_LYCEE; // Sixième (7) à Troisième (10)
            if (isset($this->anciensDemiPensionnaires[$eleveId])) {
                $part = self::FIDELITE;
            } elseif ($this->anciensDemiPensionnaires && isset($e['classes'][$this->ctx->anneeId('precedente')])) {
                $part *= self::NOUVEL_ABONNE_ANCIEN; // déjà élève l'an dernier sans manger sur place
            }
            if (! ($e['demo'] ?? false) && ! $rng->chance($part)) {
                continue;
            }
            $demiPensionnaires[$eleveId] = true;

            // Premier mois : la rentrée pour la plupart, sinon octobre ou plus tard (nouvel arrivant,
            // essai d'un trimestre à la maison...). Jamais avant l'inscription de l'élève.
            $debut = ($e['demo'] ?? false) ? 0 : (int) $rng->weighted([0 => 62, 1 => 26, 2 => 5, 4 => 5, 6 => 2]);
            $inscription = $e['inscription'] > $rentree && ($e['nouveau_en'] ?? null) === $anneeId ? $e['inscription'] : $rentree;
            while ($debut < $nbOuverts - 1 && $moisOuverts[$debut]['dernier'] < $inscription) {
                $debut++;
            }

            // Dernier mois : fin du trimestre du départ, ou arrêt volontaire après un trimestre.
            $finPossible = '9999-12-31';
            if (isset($e['depart'][$anneeId])) {
                $finPossible = $periodes[$e['depart'][$anneeId] - 1]['fin'];
            } elseif (! ($e['demo'] ?? false) && $rng->chance(0.06)) {
                $finPossible = $periodes[0]['fin'];
            }

            $mois = [];
            for ($i = $debut; $i < $nbOuverts; $i++) {
                if ($moisOuverts[$i]['premier'] > $finPossible) {
                    break;
                }
                $mois[] = $moisOuverts[$i];
            }
            if (! $mois) {
                unset($demiPensionnaires[$eleveId]);

                continue;
            }

            // Aucun paiement avant l'inscription de l'élève ni après la clôture de l'année.
            $this->plancher = max($e['inscription'], date('Y-m-d', strtotime($rentree.' -10 days')));
            $this->plafond = min($aujourdhui, $annee['fin']);

            $petitDej = $rng->chance(self::PART_PETIT_DEJEUNER);
            $s['abonnes']++;
            $s['petit_dej'] += $petitDej ? 1 : 0;

            // Profil de paiement de la famille
            $profil = $rng->weighted(['ponctuel' => 58, 'retard' => 27, 'trimestre' => 10, 'difficulte' => 5]);
            $canal = $rng->weighted(['especes' => 45, 'fedapay' => $estActive ? 35 : 20, 'momo' => $estActive ? 20 : 35]);
            $operateur = $rng->weighted(self::OPERATEURS);
            // Au guichet (Admin\CantineController), le paiement est toujours imputé au parent
            // principal de l'élève (eleves.parent_id) ; seul FedaPay enregistre le parent connecté
            // à l'application, qui peut être le second parent.
            $parents = $this->ctx->familles[$e['famille']]['parents'];
            $parentGuichet = $this->ctx->familles[$e['famille']]['principal_id'];
            $parentId = $parentGuichet;
            if (count($parents) > 1 && $rng->chance(0.2)) {
                $parentId = $parents[1]['id'];
            }

            // Mois laissés impayés
            $impayes = [];
            $n = count($mois);
            if ($profil === 'difficulte') {
                foreach (array_slice(array_keys($mois), -$rng->int(min(2, $n), min(4, $n))) as $k) {
                    $impayes[$k] = true;
                }
            } elseif ($profil === 'retard') {
                if ($estActive && ! $anneeTerminee) {
                    $impayes[$n - 1] = true; // mois en cours pas encore réglé
                    if ($n > 1 && $rng->chance(0.45)) {
                        $impayes[$n - 2] = true;
                    }
                } elseif ($rng->chance($estActive ? 0.45 : 0.15)) {
                    $impayes[$n - 1] = true; // juin jamais soldé
                }
            } elseif ($estActive && ! $anneeTerminee && $rng->chance(0.18)) {
                $impayes[$n - 1] = true; // le mois en cours sera réglé dans les prochains jours
            }

            // Famille de démonstration (parent@ccpa.test) : profil vitrine et lisible dans
            // l'application parent — mois réglés via FedaPay, le mois en cours encore à payer.
            if ($e['demo'] ?? false) {
                $profil = 'ponctuel';
                $canal = 'fedapay';
                $parentId = $parentGuichet;
                $impayes = $estActive && ! $anneeTerminee ? [$n - 1 => true] : [];
                if ($petitDej !== ($eleveId % 2 === 0)) {
                    $petitDej = ! $petitDej;
                    $s['petit_dej'] += $petitDej ? 1 : -1;
                }
            }

            $types = $petitDej ? ['ticket', 'petit_dejeuner'] : ['ticket'];
            $groupe = null; // règlement par trimestre : une date commune

            foreach ($mois as $k => $m) {
                if ($profil === 'trimestre' && ($groupe === null || $m['premier'] > $groupe['fin'])) {
                    $groupe = $this->trimestreDuMois($periodes, $m['premier']);
                    $groupe['date'] = $this->ouvrable(max($rentree, $rng->date(date('Y-m-d', strtotime($m['premier'].' -5 days')), date('Y-m-d', strtotime($m['premier'].' +12 days')))));
                }

                if (isset($impayes[$k])) {
                    // Acompte versé mais non soldé, ou rien du tout
                    if ($rng->chance(0.3)) {
                        $offre = $this->offres[$anneeId][$m['mois']]['ticket'];
                        $acompte = (float) (intdiv((int) $offre['montant'], 2000) * 1000);
                        $date = min($aujourdhui, $this->ouvrable($rng->date($m['premier'], date('Y-m-d', strtotime($m['premier'].' +20 days')))));
                        $this->paiement($tampon, $s, $eleveId, $parentGuichet, $m['mois'], 'ticket', $offre, $acompte, $date, 'especes', null, ' — acompte');
                    }
                    $s['impayes']++;

                    continue;
                }

                $date = match ($profil) {
                    'ponctuel' => $rng->date(date('Y-m-d', strtotime($m['premier'].' -6 days')), date('Y-m-d', strtotime($m['premier'].' +9 days'))),
                    'trimestre' => $groupe['date'],
                    default => $rng->date(date('Y-m-d', strtotime($m['premier'].' +5 days')), date('Y-m-d', strtotime($m['premier'].' +45 days'))),
                };
                $date = max($date, $rentree > $m['premier'] ? date('Y-m-d', strtotime($rentree.' -10 days')) : $date);
                $date = min($date, $aujourdhui);

                $mode = $profil === 'trimestre' ? ($rng->chance(0.2) ? 'cheque' : 'especes') : $canal;
                $date = $this->dateValide($date, $mode === 'fedapay' || $mode === 'momo' ? 'mobile_money' : $mode);
                $reference = $mode === 'fedapay' ? 'FEDA-CANTINE-'.$this->transactionFedaPay($date) : null;
                $payeur = $mode === 'fedapay' ? $parentId : $parentGuichet;

                foreach ($types as $type) {
                    $offre = $this->offres[$anneeId][$m['mois']][$type] ?? null;
                    if (! $offre) {
                        continue;
                    }

                    // Règlement en deux fois (soldé) : surtout pour le ticket, en espèces
                    if ($type === 'ticket' && $mode === 'especes' && $rng->chance(0.05)) {
                        $premierVersement = (float) (intdiv((int) $offre['montant'], 2000) * 1000);
                        $this->paiement($tampon, $s, $eleveId, $payeur, $m['mois'], $type, $offre, $premierVersement, $date, 'especes', null, ' — 1er versement');
                        $solde = min($aujourdhui, $this->ouvrable(date('Y-m-d', strtotime($date.' +'.$rng->int(7, 21).' days'))));
                        $this->paiement($tampon, $s, $eleveId, $payeur, $m['mois'], $type, $offre, $offre['montant'] - $premierVersement, $solde, 'especes', null, ' — solde');

                        continue;
                    }

                    $suffixe = match ($mode) {
                        'fedapay' => ' via FedaPay',
                        'momo' => ' — '.$operateur,
                        'cheque' => ' — chèque',
                        default => '',
                    };
                    $ref = match ($mode) {
                        'fedapay' => $reference,
                        'momo' => $this->referenceMobile($operateur, $date),
                        'cheque' => 'CHQ '.$rng->int(1000000, 9999999),
                        default => null,
                    };
                    $this->paiement($tampon, $s, $eleveId, $payeur, $m['mois'], $type, $offre, $offre['montant'], $date,
                        $mode === 'fedapay' || $mode === 'momo' ? 'mobile_money' : $mode, $ref, $suffixe);
                }
            }
        }

        $tampon->flush();
        $this->anciensDemiPensionnaires = $demiPensionnaires;
        $this->stats[$annee['libelle']] = $s;
    }

    private function paiement($tampon, array &$s, int $eleveId, int $parentId, int $mois, string $type, array $offre,
        float $montant, string $date, string $mode, ?string $reference, string $suffixe): void
    {
        $date = $this->dateValide($date, $mode);

        // Une transaction FedaPay (ticket + petit-déjeuner du mois) est enregistrée d'un bloc par
        // captureOrder : même horodatage pour toutes ses lignes.
        if ($reference !== null && str_starts_with($reference, 'FEDA-')) {
            if ($this->derniereTransaction[0] !== $reference) {
                $this->derniereTransaction = [$reference, $this->ctx->rng->heureOuvree($date, 6, 22)];
            }
            $horodatage = $this->derniereTransaction[1];
        } else {
            $horodatage = $this->ctx->rng->heureOuvree($date, 7, 16);
        }

        $tampon->add([
            'eleve_id' => $eleveId,
            'tranche_id' => null,
            'payable_type' => self::TYPE_PAYABLE,
            'payable_id' => $offre['id'],
            'montant' => $montant,
            'date_paiement' => $date,
            'mode_paiement' => $mode,
            'reference' => $reference,
            'parent_id' => $parentId,
            'recu_path' => null,
            'commentaire' => 'Cantine — '.CantineOffre::MOIS_LABELS[$mois].' ('.($type === 'ticket' ? 'Ticket' : 'Petit-déjeuner').')'.$suffixe,
            'created_at' => $horodatage,
            'updated_at' => $horodatage,
        ]);

        $s['paiements']++;
        $s['encaisse'] += $montant;
    }

    /** Trimestre (bornes élargies aux mois entiers) contenant un mois donné. */
    private function trimestreDuMois(array $periodes, string $premier): array
    {
        foreach ($periodes as $i => $p) {
            $suivant = $periodes[$i + 1]['debut'] ?? '9999-12-31';
            if ($premier < substr($suivant, 0, 8).'01') {
                return ['fin' => date('Y-m-t', strtotime($p['fin']))];
            }
        }

        return ['fin' => '9999-12-31'];
    }

    /** Bornes de l'élève (inscription, aujourd'hui / fin d'année) ; guichet fermé le dimanche. */
    private function dateValide(string $date, string $mode): string
    {
        $date = min(max($date, $this->plancher), $this->plafond);
        if (($mode === 'especes' || $mode === 'cheque') && date('w', strtotime($date)) === '0') {
            $lundi = date('Y-m-d', strtotime($date.' +1 day'));
            $samedi = date('Y-m-d', strtotime($date.' -1 day'));
            $date = $lundi <= $this->plafond ? $lundi : ($samedi >= $this->plancher ? $samedi : $date);
        }

        return $date;
    }

    /**
     * Identifiant de transaction FedaPay : croissant avec la date et unique, même échelle que la
     * scolarité et les uniformes (≈ 1 800 transactions par jour sur la plateforme).
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

    /** L'économat est fermé le dimanche : report au lundi. */
    private function ouvrable(string $date): string
    {
        return date('w', strtotime($date)) === '0' ? date('Y-m-d', strtotime($date.' +1 day')) : $date;
    }

    /** Identifiant de transaction tel qu'affiché par l'opérateur sur le SMS de confirmation. */
    private function referenceMobile(string $operateur, string $date): string
    {
        $rng = $this->ctx->rng;

        return match ($operateur) {
            'MTN MoMo' => (string) $rng->int(1000000000, 9999999999),
            'Moov Money' => 'MP'.date('ymd', strtotime($date)).'.'.$rng->code(4, '0123456789').'.'.$rng->code(5, 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789'),
            default => 'CLT'.$rng->code(9, '0123456789'),
        };
    }
}
