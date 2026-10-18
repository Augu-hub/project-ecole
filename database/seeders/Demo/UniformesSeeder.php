<?php

namespace Database\Seeders\Demo;

use App\Models\UniformeVente;
use Database\Seeders\Demo\Support\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Uniformes : catalogue de la boutique de l'économat (articles par type et taille, prix en FCFA,
 * stock informatif), ventes de la rentrée et de l'année sur les deux années scolaires, et leurs
 * paiements polymorphes (payable_type = UniformeVente, tranche_id = null).
 *
 * Les lignes reproduisent exactement ce qu'écrivent l'application et l'API :
 * - Admin\UniformeVenteController::vendre() : une vente par article (quantité, prix unitaire figé au
 *   prix catalogue du jour, montant total = prix × quantité), avec paiement immédiat optionnel au
 *   guichet ; payer() : versements ultérieurs, partiels ou soldant la vente ;
 * - Api\UniformeController::captureOrder() : panier réglé depuis l'application parent via FedaPay,
 *   un paiement intégral par article, même référence « FEDA-UNIFORMES-<transaction> ».
 *
 * Règles respectées :
 * - uniformes réservés au cycle secondaire (toutes les classes du CCPA) ;
 * - statut d'une vente calculé comme UniformeVente::statut : jamais de trop-perçu (somme des
 *   paiements ≤ montant total), d'où « payé », « partiel » ou « impayé » ;
 * - aucune vente ni aucun paiement daté après « aujourd'hui », aucun paiement avant sa vente ;
 * - paiement au nom du parent principal de l'élève (eleves.parent_id) au guichet, sans référence ;
 *   via FedaPay, au nom du parent connecté (parfois le second parent), référence FEDA-UNIFORMES ;
 * - kit complet pour la plupart des nouveaux élèves, renouvellement partiel pour une partie des
 *   anciens (l'élève grandit : la taille suit l'âge), cravate obligatoire à l'entrée au lycée,
 *   quelques remplacements en cours d'année ;
 * - hausse de 500 FCFA de certains articles à la rentrée 2025 : les ventes 2024-2025 gardent
 *   l'ancien prix (prix_unitaire est un instantané du catalogue au moment de la vente) ;
 * - stock informatif cohérent avec l'écran catalogue : quelques articles en rupture (≤ 0).
 */
class UniformesSeeder extends Seeder
{
    private const TYPE_PAYABLE = UniformeVente::class;

    /** Tailles dans l'ordre croissant (la taille d'un élève est un indice dans cette liste). */
    private const TAILLES = ['10-12 ans', '12-14 ans', 'S', 'M', 'L', 'XL'];

    /**
     * Catalogue : type => [taille => prix 2025-2026 en FCFA], rôle de l'article dans le kit et hausse
     * appliquée à la rentrée 2025 (les ventes 2024-2025 se font au prix diminué de cette hausse).
     */
    private const CATALOGUE = [
        'Chemise garçon' => ['role' => 'haut_m', 'hausse' => 500, 'tailles' => ['10-12 ans' => 4500, '12-14 ans' => 4500, 'S' => 5000, 'M' => 5000, 'L' => 5500, 'XL' => 5500]],
        'Chemisier fille' => ['role' => 'haut_f', 'hausse' => 500, 'tailles' => ['10-12 ans' => 4500, '12-14 ans' => 4500, 'S' => 5000, 'M' => 5000, 'L' => 5500, 'XL' => 5500]],
        'Pantalon garçon' => ['role' => 'bas_m', 'hausse' => 500, 'tailles' => ['10-12 ans' => 5500, '12-14 ans' => 5500, 'S' => 6000, 'M' => 6000, 'L' => 6500, 'XL' => 6500]],
        'Jupe plissée fille' => ['role' => 'bas_f', 'hausse' => 500, 'tailles' => ['10-12 ans' => 5000, '12-14 ans' => 5000, 'S' => 5500, 'M' => 5500, 'L' => 6000, 'XL' => 6000]],
        'Tenue d\'EPS (tee-shirt + short)' => ['role' => 'eps', 'hausse' => 500, 'tailles' => ['10-12 ans' => 5500, '12-14 ans' => 5500, 'S' => 6000, 'M' => 6000, 'L' => 6500, 'XL' => 6500]],
        'Survêtement CCPA' => ['role' => 'survetement', 'hausse' => 0, 'tailles' => ['12-14 ans' => 11500, 'S' => 12500, 'M' => 12500, 'L' => 13000, 'XL' => 13000]],
        'Polo brodé CCPA' => ['role' => 'polo', 'hausse' => 0, 'tailles' => ['S' => 4000, 'M' => 4000, 'L' => 4000, 'XL' => 4500]],
        'Cravate CCPA (lycée)' => ['role' => 'cravate', 'hausse' => 0, 'tailles' => ['Unique' => 2000]],
        'Écusson brodé CCPA' => ['role' => 'ecusson', 'hausse' => 0, 'tailles' => ['Unique' => 500]],
        'Ceinture CCPA' => ['role' => 'ceinture', 'hausse' => 0, 'tailles' => ['Unique' => 1500]],
    ];

    /** Part des nouveaux élèves qui achètent leur tenue à l'école (les autres passent par un tailleur). */
    private const PART_NOUVEAUX = 0.88;

    /** Part des anciens élèves qui renouvellent une partie de leur tenue à la rentrée. */
    private const PART_RENOUVELLEMENT = 0.36;

    /** Part des élèves entrant en Seconde qui achètent la cravate du lycée. */
    private const PART_CRAVATE_SECONDE = 0.9;

    /** Part des élèves qui remplacent un article en cours d'année (perte, déchirure). */
    private const PART_REMPLACEMENT = 0.05;

    /** @var array<string, array<string, array{id: int, prix: float, hausse: int, role: string}>> articles[type][taille] */
    private array $articles = [];

    /** @var array<string, string> rôle => type */
    private array $roles = [];

    /**
     * Ventes en mémoire, dans l'ordre d'insertion, chacune avec ses paiements prévus.
     *
     * @var list<array{ligne: array<string, mixed>, paiements: list<array<string, mixed>>}>
     */
    private array $ventes = [];

    /** @var array<int, int> quantités vendues par article */
    private array $vendus = [];

    /** @var array<int, int> dernier indice de taille de chaque élève (un élève ne rapetisse pas) */
    private array $taillesEleves = [];

    /** @var array<int, bool> identifiants de transaction FedaPay déjà attribués */
    private array $transactionsFedaPay = [];

    private array $stats = [];

    public function __construct(private DemoContext $ctx) {}

    public function run(): void
    {
        DB::transaction(function () {
            $this->catalogue();

            foreach (['precedente', 'active'] as $cle) {
                $this->annee($cle);
            }

            $this->enregistrer();
            $this->stocks();
        });

        $this->command?->info(sprintf('  Uniformes : %d articles au catalogue (%d types).', array_sum(array_map('count', $this->articles)), count($this->articles)));
        foreach ($this->stats as $libelle => $s) {
            $this->command?->info(sprintf(
                '  Uniformes %s : %d élèves équipés, %d ventes (%d articles), %s FCFA facturés, %s FCFA encaissés en %d paiements ; %d payées, %d partielles, %d impayées.',
                $libelle, count($s['eleves']), $s['ventes'], $s['articles'],
                number_format($s['facture'], 0, ',', ' '), number_format($s['encaisse'], 0, ',', ' '), $s['paiements'],
                $s['paye'], $s['partiel'], $s['impaye']
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // CATALOGUE
    // ─────────────────────────────────────────────────────────────────

    /** Catalogue créé avant la rentrée 2024, prix revus en août 2025 (stock fixé en fin de seeder). */
    private function catalogue(): void
    {
        $creeLe = '2024-07-22 10:15:00';
        $revuLe = '2025-08-18 09:40:00';

        $lignes = [];
        foreach (self::CATALOGUE as $type => $article) {
            $this->roles[$article['role']] = $type;
            foreach ($article['tailles'] as $taille => $prix) {
                $lignes[] = [
                    'type' => $type, 'taille' => $taille, 'prix' => $prix, 'quantite_stock' => 0,
                    'created_at' => $creeLe, 'updated_at' => $article['hausse'] > 0 ? $revuLe : $creeLe,
                ];
            }
        }
        $this->ctx->inserer('uniformes', $lignes);

        foreach (DB::table('uniformes')->get(['id', 'type', 'taille', 'prix']) as $u) {
            $this->articles[$u->type][$u->taille] = [
                'id' => (int) $u->id,
                'prix' => (float) $u->prix,
                'hausse' => self::CATALOGUE[$u->type]['hausse'],
                'role' => self::CATALOGUE[$u->type]['role'],
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // VENTES D'UNE ANNÉE
    // ─────────────────────────────────────────────────────────────────

    private function annee(string $cle): void
    {
        $rng = $this->ctx->rng;
        $annee = $this->ctx->annees[$cle];
        $anneeId = $annee['id'];
        $anneeDebut = (int) substr($annee['debut'], 0, 4);
        $periodes = $this->ctx->periodes[$anneeId];
        $rentree = $periodes[0]['debut'];
        $precedenteId = $cle === 'active' ? $this->ctx->anneeId('precedente') : null;

        $this->stats[$annee['libelle']] = [
            'eleves' => [], 'ventes' => 0, 'articles' => 0, 'facture' => 0.0, 'encaisse' => 0.0,
            'paiements' => 0, 'paye' => 0, 'partiel' => 0, 'impaye' => 0,
        ];
        $s = &$this->stats[$annee['libelle']];

        foreach ($this->ctx->eleves as $eleveId => $e) {
            if (! isset($e['classes'][$anneeId])) {
                continue;
            }
            $classe = $this->ctx->classes[$e['classes'][$anneeId]];
            $lycee = $classe['ordre'] >= 11; // Seconde (11) à Terminale (13)
            $nouveau = ($e['nouveau_en'] ?? null) === $anneeId;
            $demo = $e['demo'] ?? false;
            $taille = $this->taillesEleves[$eleveId] = max(
                $this->taillesEleves[$eleveId] ?? 0,
                $this->indiceTaille($e, $anneeDebut, $lycee)
            );

            // 1) Rentrée : kit complet (nouveaux) ou renouvellement partiel (anciens)
            $panier = [];
            if ($nouveau) {
                if ($demo || $rng->chance(self::PART_NOUVEAUX)) {
                    $panier = $this->kitComplet($e['sexe'], $taille, $lycee);
                }
                // Famille de démonstration : le benjamin prend aussi le survêtement (réglé en partie, voir commande()).
                if ($demo && ! in_array('survetement', array_map(fn ($ligne) => $ligne[0]['role'], $panier), true)) {
                    $panier[] = [$this->article('survetement', $taille), 1];
                }
            } else {
                $entreeLycee = $classe['niveau'] === 'Seconde' && $precedenteId !== null
                    && isset($e['classes'][$precedenteId]) && $this->ctx->classes[$e['classes'][$precedenteId]]['niveau'] === 'Troisième';
                if ($demo || $rng->chance(self::PART_RENOUVELLEMENT)) {
                    $panier = $this->renouvellement($e['sexe'], $taille);
                }
                if ($entreeLycee && $rng->chance(self::PART_CRAVATE_SECONDE)) {
                    $panier[] = [$this->article('cravate', 0), 1];
                }
            }

            if ($panier) {
                // Nouveaux : après l'inscription, entre fin août et les deux premières semaines de cours.
                $debut = date('Y-m-d', strtotime($rentree.' -24 days'));
                if ($nouveau && $e['inscription'] > $debut) {
                    $debut = $e['inscription'];
                }
                $fin = date('Y-m-d', strtotime(max($rentree, $debut).($nouveau ? ' +14 days' : ' +30 days')));
                $this->commande($e, $eleveId, $cle, $panier, $rng->date($debut, $fin), $s);
            }

            // 2) Remplacement en cours d'année (2e trimestre), pas pour un élève déjà parti
            if (! isset($e['depart'][$anneeId]) && $rng->chance(self::PART_REMPLACEMENT)) {
                $role = $rng->weighted(['haut' => 55, 'bas' => 30, 'eps' => 15]);
                $article = match ($role) {
                    'haut' => $this->article($e['sexe'] === 'F' ? 'haut_f' : 'haut_m', $taille),
                    'bas' => $this->article($e['sexe'] === 'F' ? 'bas_f' : 'bas_m', $taille),
                    default => $this->article('eps', $taille),
                };
                $panier = [[$article, 1]];
                if ($role === 'haut') {
                    $panier[] = [$this->article('ecusson', 0), 1];
                }
                $this->commande($e, $eleveId, $cle, $panier, $rng->date($periodes[1]['debut'], $periodes[1]['fin']), $s);
            }
        }
        unset($s);
    }

    /**
     * Indice de taille d'après l'âge à la rentrée (avec la variabilité de la croissance).
     */
    private function indiceTaille(array $e, int $anneeDebut, bool $lycee): int
    {
        $age = $anneeDebut - (int) substr($e['naissance'], 0, 4);
        $base = match (true) {
            $age <= 11 => 0,
            $age <= 13 => 1,
            $age === 14 => 2,
            $age <= 16 => 3,
            default => 4,
        };
        $ecart = $this->ctx->rng->gauss($lycee && $e['sexe'] === 'M' ? 0.3 : 0.0, 0.6);

        return max(0, min(count(self::TAILLES) - 1, $base + (int) round($ecart)));
    }

    /** Article d'un rôle donné dans la taille la plus proche disponible. */
    private function article(string $role, int $indiceTaille): array
    {
        $type = $this->roles[$role];
        $tailles = $this->articles[$type];
        if (isset($tailles['Unique'])) {
            return $tailles['Unique'] + ['type' => $type, 'taille' => 'Unique'];
        }

        for ($ecart = 0; $ecart < count(self::TAILLES); $ecart++) {
            foreach ([$indiceTaille + $ecart, $indiceTaille - $ecart] as $i) {
                $taille = self::TAILLES[$i] ?? null;
                if ($taille !== null && isset($tailles[$taille])) {
                    return $tailles[$taille] + ['type' => $type, 'taille' => $taille];
                }
            }
        }

        throw new \LogicException("Aucune taille pour {$type}.");
    }

    /** @return list<array{0: array, 1: int}> */
    private function kitComplet(string $sexe, int $taille, bool $lycee): array
    {
        $rng = $this->ctx->rng;
        $f = $sexe === 'F';

        $hauts = (int) $rng->weighted([2 => 70, 3 => 20, 1 => 10]);
        $panier = [[$this->article($f ? 'haut_f' : 'haut_m', $taille), $hauts]];
        if ($rng->chance(0.88)) {
            $panier[] = [$this->article($f ? 'bas_f' : 'bas_m', $taille), (int) $rng->weighted([2 => 60, 1 => 36, 3 => 4])];
        }
        if ($rng->chance(0.92)) {
            $panier[] = [$this->article('ecusson', 0), $hauts];
        }
        if ($rng->chance(0.86)) {
            $panier[] = [$this->article('eps', $taille), 1];
        }
        if ($lycee && $rng->chance(0.95)) {
            $panier[] = [$this->article('cravate', 0), 1];
        }
        if ($rng->chance($f ? 0.12 : 0.55)) {
            $panier[] = [$this->article('ceinture', 0), 1];
        }
        if ($rng->chance(0.14)) {
            $panier[] = [$this->article('survetement', $taille), 1];
        }
        if ($rng->chance(0.18)) {
            $panier[] = [$this->article('polo', $taille), 1];
        }

        return $panier;
    }

    /** @return list<array{0: array, 1: int}> */
    private function renouvellement(string $sexe, int $taille): array
    {
        $rng = $this->ctx->rng;
        $f = $sexe === 'F';
        $panier = [];

        $hauts = $rng->chance(0.75) ? (int) $rng->weighted([1 => 45, 2 => 55]) : 0;
        if ($hauts) {
            $panier[] = [$this->article($f ? 'haut_f' : 'haut_m', $taille), $hauts];
            $panier[] = [$this->article('ecusson', 0), $hauts];
        }
        if ($rng->chance(0.55) || ! $panier) {
            $panier[] = [$this->article($f ? 'bas_f' : 'bas_m', $taille), (int) $rng->weighted([1 => 70, 2 => 30])];
        }
        if ($rng->chance(0.3)) {
            $panier[] = [$this->article('eps', $taille), 1];
        }
        if ($rng->chance(0.06)) {
            $panier[] = [$this->article('survetement', $taille), 1];
        }

        return $panier;
    }

    // ─────────────────────────────────────────────────────────────────
    // COMMANDE : VENTES ET PAIEMENTS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Une commande (un passage à la boutique ou un panier FedaPay) : une vente par article et ses
     * paiements, selon le profil de paiement de la famille.
     *
     * @param  list<array{0: array, 1: int}>  $panier
     */
    private function commande(array $e, int $eleveId, string $cle, array $panier, string $date, array &$s): void
    {
        $rng = $this->ctx->rng;
        $aujourdhui = $this->ctx->aujourdhui;
        $famille = $this->ctx->familles[$e['famille']];
        $demo = $e['demo'] ?? false;

        // Canal : application (FedaPay, surtout en 2025-2026) ou guichet de l'économat.
        $fedapay = ($famille['mobile'] ?? false) && $rng->chance($cle === 'active' ? 0.4 : 0.15);
        // Famille de démonstration : l'acompte sur le survêtement du benjamin ne peut être saisi qu'au
        // guichet (FedaPay règle toujours l'intégralité du panier, voir captureOrder()).
        $acompteDemo = $demo && $cle === 'active'
            && in_array('survetement', array_map(fn ($ligne) => $ligne[0]['role'], $panier), true);
        if ($acompteDemo) {
            $fedapay = false;
        }
        if (! $fedapay) {
            $date = $this->ouvrable($date);
        }
        if ($date > $aujourdhui) {
            return;
        }

        // Payeur : au guichet, toujours eleves.parent_id (vendre()/payer()) ; via l'application,
        // le parent connecté, parfois le second parent de la famille.
        $parentId = $famille['principal_id'];
        if ($fedapay && count($famille['parents']) > 1 && $rng->chance(0.2)) {
            $parentId = $famille['parents'][1]['id'];
        }

        $profil = $fedapay ? 'comptant' : $this->profilPaiement($famille['payeur'] ?? 'normal', $cle);
        $mode = match (true) {
            $fedapay => 'fedapay',
            ($famille['mode'] ?? null) === 'mobile_money' && $rng->chance(0.8) => 'momo',
            ($famille['mode'] ?? null) === 'cheque' && $rng->chance(0.5) => 'cheque',
            default => $rng->chance(0.85) ? 'especes' : 'momo',
        };
        $reference = $fedapay ? 'FEDA-UNIFORMES-'.$this->transactionFedaPay($date) : null;
        $heureVente = $fedapay ? $this->ctx->rng->heureOuvree($date, 6, 22) : $this->ctx->rng->heureOuvree($date, 7, 16);

        // Famille de démonstration : achats réglés comptant, sauf l'acompte en cours sur le
        // survêtement du benjamin, qui montre une vente « partielle » dans l'historique de l'app.
        if ($demo) {
            $profil = 'comptant';
        }

        foreach ($panier as [$article, $quantite]) {
            $prix = $cle === 'precedente' ? $article['prix'] - $article['hausse'] : $article['prix'];
            $total = $prix * $quantite;
            $commentaire = 'Uniforme — '.$article['type'].' ('.$article['taille'].')';

            $vente = [
                'ligne' => [
                    'uniforme_id' => $article['id'],
                    'eleve_id' => $eleveId,
                    'quantite' => $quantite,
                    'prix_unitaire' => $prix,
                    'montant_total' => $total,
                    'date_vente' => $date,
                    'created_at' => $heureVente,
                    'updated_at' => $heureVente,
                ],
                'paiements' => [],
            ];

            $versements = $acompteDemo && $article['role'] === 'survetement'
                ? [[(float) intdiv((int) $total, 2000) * 1000, $date]]
                : $this->versements($profil, $total, $date);

            foreach ($versements as $i => [$montant, $quand]) {
                if ($quand > $aujourdhui || $montant <= 0) {
                    continue;
                }
                $comptant = $profil === 'comptant';
                $modePaiement = $comptant ? $mode : ($rng->chance(0.75) ? 'especes' : 'momo');
                if (in_array($modePaiement, ['especes', 'cheque', 'momo'], true)) {
                    $quand = min($aujourdhui, $this->ouvrable($quand));
                }
                // Au guichet, vendre()/payer() n'enregistrent ni référence ni précision de canal :
                // seul captureOrder() (FedaPay) renseigne la référence et suffixe le commentaire.
                [$modeBase, $ref, $suffixe] = match ($modePaiement) {
                    'fedapay' => ['mobile_money', $reference, ' via FedaPay'],
                    'momo' => ['mobile_money', null, ''],
                    'cheque' => ['cheque', null, ''],
                    default => ['especes', null, ''],
                };
                $horodatage = $comptant && $i === 0 ? $heureVente : $rng->heureOuvree($quand, $modePaiement === 'fedapay' ? 6 : 7, $modePaiement === 'fedapay' ? 22 : 16);

                $vente['paiements'][] = [
                    'eleve_id' => $eleveId,
                    'tranche_id' => null,
                    'payable_type' => self::TYPE_PAYABLE,
                    'montant' => $montant,
                    'date_paiement' => $quand,
                    'mode_paiement' => $modeBase,
                    'reference' => $ref,
                    'parent_id' => $parentId,
                    'recu_path' => null,
                    'commentaire' => $commentaire.$suffixe,
                    'created_at' => $horodatage,
                    'updated_at' => $horodatage,
                ];
            }

            $paye = array_sum(array_column($vente['paiements'], 'montant'));
            $s[$paye <= 0 ? 'impaye' : ($paye >= $total ? 'paye' : 'partiel')]++;
            $s['ventes']++;
            $s['articles'] += $quantite;
            $s['facture'] += $total;
            $s['encaisse'] += $paye;
            $s['paiements'] += count($vente['paiements']);
            $s['eleves'][$eleveId] = true;

            $this->vendus[$article['id']] = ($this->vendus[$article['id']] ?? 0) + $quantite;
            $this->ventes[] = $vente;
        }
    }

    /** Profil de paiement d'une commande au guichet, d'après celui de la famille. */
    private function profilPaiement(string $payeur, string $cle): string
    {
        $poids = match ($payeur) {
            'exemplaire' => ['comptant' => 86, 'credit' => 14, 'acompte' => 0, 'impaye' => 0],
            'retard' => ['comptant' => 45, 'credit' => 40, 'acompte' => 10, 'impaye' => 5],
            'difficulte' => ['comptant' => 25, 'credit' => 40, 'acompte' => 20, 'impaye' => 15],
            default => ['comptant' => 72, 'credit' => 25, 'acompte' => 2, 'impaye' => 1],
        };
        if ($cle === 'precedente') {
            // L'économat a relancé les familles : peu de restes à payer sur l'année archivée.
            $poids['credit'] += ($poids['acompte'] + $poids['impaye']) / 2;
            $poids['acompte'] /= 2;
            $poids['impaye'] /= 2;
        }

        return (string) $this->ctx->rng->weighted(array_filter($poids));
    }

    /**
     * Versements prévus pour une vente : [montant, date]. Ceux qui tombent après « aujourd'hui »
     * ne sont pas enregistrés : la vente reste alors partielle ou impayée.
     *
     * @return list<array{0: float, 1: string}>
     */
    private function versements(string $profil, float $total, string $date): array
    {
        $rng = $this->ctx->rng;
        $plus = fn (string $d, int $min, int $max) => date('Y-m-d', strtotime($d.' +'.$rng->int($min, $max).' days'));
        $acompte = fn () => (float) max(500, min($total - 500, round($total * $rng->between(0.35, 0.65) / 500) * 500));

        return match ($profil) {
            'comptant' => [[$total, $date]],
            // Vente à crédit soldée plus tard, en une fois ou en deux versements
            'credit' => $total >= 4000 && $rng->chance(0.5)
                ? (function () use ($acompte, $total, $date, $plus) {
                    $premier = $acompte();
                    $d1 = $plus($date, 3, 30);

                    return [[$premier, $d1], [$total - $premier, $plus($d1, 10, 45)]];
                })()
                : [[$total, $plus($date, 5, 40)]],
            // Acompte versé, solde jamais réglé
            'acompte' => $total >= 1000 ? [[$acompte(), $plus($date, 0, 25)]] : [],
            default => [],
        };
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉCRITURE EN BASE
    // ─────────────────────────────────────────────────────────────────

    private function enregistrer(): void
    {
        // Ventes dans l'ordre chronologique (les identifiants suivent les dates de vente)
        usort($this->ventes, fn ($a, $b) => $a['ligne']['created_at'] <=> $b['ligne']['created_at']);

        $avant = (int) DB::table('uniforme_ventes')->max('id');
        $this->ctx->inserer('uniforme_ventes', array_column($this->ventes, 'ligne'));
        $ids = DB::table('uniforme_ventes')->where('id', '>', $avant)->orderBy('id')->pluck('id')->all();
        if (count($ids) !== count($this->ventes)) {
            throw new \RuntimeException('Uniformes : identifiants des ventes insérées introuvables.');
        }

        $paiements = [];
        foreach ($this->ventes as $i => $vente) {
            foreach ($vente['paiements'] as $p) {
                $paiements[] = ['payable_id' => (int) $ids[$i]] + $p;
            }
        }
        usort($paiements, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        $tampon = $this->ctx->tampon('paiements');
        foreach ($paiements as $p) {
            $tampon->add($p);
        }
        $tampon->flush();
    }

    /**
     * Stock informatif restant : réapprovisionnements successifs moins les ventes, avec quelques
     * articles en rupture (zéro ou négatif, la vente n'étant jamais bloquée).
     */
    private function stocks(): void
    {
        $rng = $this->ctx->rng;
        $vendus = array_filter($this->vendus, fn ($q) => $q >= 10);
        $ruptures = $vendus ? array_slice($rng->shuffle(array_keys($vendus)), 0, 3) : [];
        $reapproLe = '2026-08-25 11:00:00';

        foreach ($this->articles as $tailles) {
            foreach ($tailles as $article) {
                $id = $article['id'];
                $stock = in_array($id, $ruptures, true)
                    ? $rng->int(-3, 0)
                    : ($article['role'] === 'ecusson' ? $rng->int(40, 150) : $rng->int(3, 35));
                DB::table('uniformes')->where('id', $id)->update([
                    'quantite_stock' => $stock,
                    'updated_at' => min($this->ctx->aujourdhui.' 08:00:00', isset($this->vendus[$id]) ? $reapproLe : '2025-08-18 09:40:00'),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // UTILITAIRES
    // ─────────────────────────────────────────────────────────────────

    /** L'économat est fermé le dimanche : report au lundi. */
    private function ouvrable(string $date): string
    {
        return date('w', strtotime($date)) === '0' ? date('Y-m-d', strtotime($date.' +1 day')) : $date;
    }

    /**
     * Identifiant de transaction FedaPay : croissant avec la date et unique, même échelle que celle
     * des paiements de scolarité (FinanceSeeder : ≈ 1 800 transactions par jour sur la plateforme).
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
}
