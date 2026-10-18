<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Data\Curriculum;
use Database\Seeders\Demo\Support\DemoContext;
use Database\Seeders\Demo\Support\Rng;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Emploi du temps de l'année ACTIVE : chaque classe reçoit ses heures hebdomadaires de cours
 * (volume du programme de sa série, voir Curriculum::PROGRAMMES), placées sur les créneaux horaires
 * créés par ReferentielSeeder, avec l'enseignant affecté à la matière dans classe_matieres.
 *
 * Organisation réaliste d'un établissement béninois : cours le matin de 7h à 12h15 en priorité,
 * après-midi (15h-18h) en complément, mercredi après-midi libre ; séances de 2 heures pour les
 * matières à gros volume (4 h → 2 + 2, 5 h → 2 + 2 + 1...), jamais deux séances de la même matière
 * le même jour ; EPS tôt le matin ou en fin d'après-midi (chaleur) sur le terrain ou le plateau
 * omnisports ; informatique en salle informatique ; une séance de PCT et de SVT au laboratoire
 * pour les classes du lycée ; les autres cours dans la salle de la classe.
 *
 * Ordonnancement glouton déterministe (matières les plus contraintes d'abord, meilleur placement
 * selon un coût : après-midi, journée chargée, matière lourde l'après-midi...), relancé avec un
 * autre ordre de départage si un bloc ne trouve pas sa place, une séance de 2 h pouvant être
 * scindée en deux heures en dernier recours. AUCUN conflit : ni classe, ni enseignant, ni salle
 * sur des créneaux qui se chevauchent (même règle que EmploiDuTempsConflitService), vérifié par
 * une requête SQL en fin de chargement.
 */
class EmploiDuTempsSeeder extends Seeder
{
    /** Nombre maximal de tentatives d'ordonnancement (ordre de départage différent à chaque fois). */
    private const TENTATIVES = 40;

    /** Matières exigeantes : placées de préférence le matin. */
    private const MATIERES_LOURDES = ['MATH', 'PCT', 'FR', 'PHILO', 'ECO', 'SVT'];

    /** Installations sportives (une classe à la fois sur chacune). */
    private const SALLES_EPS = ['Terrain de sport', 'Plateau omnisports'];

    private Rng $rng;

    /** @var array<int, array{id: int, debut: string, fin: string, matin: bool}> créneaux par id */
    private array $creneaux = [];

    /** @var array<int, list<int>> chevauchements[creneauId] => créneaux qui le chevauchent (lui compris) */
    private array $chevauchements = [];

    /** @var array<string, list<int>> jour => créneaux ouverts (ids, ordre chronologique) */
    private array $jours = [];

    /** @var array<int, int> suivant[creneauId] => créneau consécutif dans la même demi-journée */
    private array $suivant = [];

    public function __construct(private DemoContext $ctx)
    {
        // Générateur propre au module : l'emploi du temps ne décale pas la séquence aléatoire partagée.
        $this->rng = new Rng(DemoContext::GRAINE + 7);
    }

    public function run(): void
    {
        $anneeId = $this->ctx->annees['active']['id']
            ?? (int) DB::table('annee_scolaires')->where('est_active', true)->value('id');

        if (! $anneeId) {
            throw new RuntimeException('EmploiDuTempsSeeder : aucune année scolaire active.');
        }

        $dejaPlanifie = DB::table('emplois_du_temps as e')
            ->join('classe_annees as ca', 'ca.id', '=', 'e.classe_annee_id')
            ->where('ca.annee_scolaire_id', $anneeId)
            ->exists();
        if ($dejaPlanifie) {
            $this->command?->warn('  Emploi du temps déjà présent pour l\'année active : rien à faire.');

            return;
        }

        $this->chargerCreneaux();
        [$classes, $blocs] = $this->chargerProgramme($anneeId);

        if (! $blocs) {
            $this->command?->warn('  Aucune matière avec enseignant et volume horaire : emploi du temps vide.');

            return;
        }

        $meilleur = null;
        for ($tentative = 1; $tentative <= self::TENTATIVES; $tentative++) {
            $resultat = $this->planifier($blocs);
            $cle = [$resultat['heures_manquantes'], $resultat['scissions'], $resultat['cout']];
            if ($meilleur === null || $cle < $meilleur['cle']) {
                $meilleur = $resultat + ['cle' => $cle, 'tentative' => $tentative];
            }
            if ($resultat['heures_manquantes'] === 0 && $resultat['scissions'] === 0) {
                break;
            }
        }

        DB::transaction(function () use ($meilleur, $classes, $anneeId) {
            $this->inserer($meilleur['seances'], $classes, $anneeId);
            $this->verifierAucunConflit($anneeId);
        });

        $this->statistiques($meilleur, $classes, $blocs);
    }

    // ─────────────────────────────────────────────────────────────────
    // DONNÉES D'ENTRÉE
    // ─────────────────────────────────────────────────────────────────

    private function chargerCreneaux(): void
    {
        $lignes = DB::table('creneaux_horaires')->orderBy('ordre')->orderBy('heure_debut')->get();
        if ($lignes->isEmpty()) {
            throw new RuntimeException('EmploiDuTempsSeeder : aucun créneau horaire (ReferentielSeeder doit passer avant).');
        }

        $parOrdre = [];
        foreach ($lignes as $c) {
            $debut = substr((string) $c->heure_debut, 0, 8);
            $fin = substr((string) $c->heure_fin, 0, 8);
            $this->creneaux[(int) $c->id] = ['id' => (int) $c->id, 'debut' => $debut, 'fin' => $fin, 'matin' => $debut < '13:00:00'];
            $parOrdre[(int) $c->ordre] = (int) $c->id;
        }

        // Chevauchement réel d'horaires (règle de EmploiDuTempsConflitService), pas simple égalité.
        foreach ($this->creneaux as $a) {
            foreach ($this->creneaux as $b) {
                if ($a['debut'] < $b['fin'] && $a['fin'] > $b['debut']) {
                    $this->chevauchements[$a['id']][] = $b['id'];
                }
            }
        }

        // Créneaux ouverts par jour (mercredi après-midi libre) ; à défaut, tous les créneaux du lundi au vendredi.
        foreach (Curriculum::JOURS as $jour => $ordres) {
            $ids = array_values(array_filter(array_map(fn ($o) => $parOrdre[$o] ?? null, $ordres)));
            $this->jours[$jour] = $ids ?: array_values($parOrdre);
        }

        // Séance de 2 h : deux créneaux qui se suivent dans la même demi-journée (récréation de 15 min tolérée).
        $tries = array_values($this->creneaux);
        usort($tries, fn ($a, $b) => strcmp($a['debut'], $b['debut']));
        for ($i = 0; $i < count($tries) - 1; $i++) {
            [$a, $b] = [$tries[$i], $tries[$i + 1]];
            $pause = (strtotime('1970-01-01 '.$b['debut'].' UTC') - strtotime('1970-01-01 '.$a['fin'].' UTC')) / 60;
            if ($a['matin'] === $b['matin'] && $pause >= 0 && $pause <= 20) {
                $this->suivant[$a['id']] = $b['id'];
            }
        }
    }

    /**
     * Matières de chaque classe de l'année active (enseignant lu en base, volume horaire du
     * programme de la série), découpées en blocs de 2 h et 1 h.
     *
     * @return array{0: array<int, array{cle: string, salle: string, lycee: bool, heures: int}>, 1: list<array<string, mixed>>}
     */
    private function chargerProgramme(int $anneeId): array
    {
        $codes = array_flip($this->ctx->matieres);
        $nomsVersCodes = [];
        foreach (Curriculum::MATIERES as $code => [$nom]) {
            $nomsVersCodes[$nom] = $code;
        }

        $lignes = DB::table('classe_matieres as cm')
            ->join('classe_annees as ca', 'ca.id', '=', 'cm.classe_annee_id')
            ->join('classes as c', 'c.id', '=', 'ca.classe_id')
            ->join('niveaux as n', 'n.id', '=', 'c.niveau_id')
            ->join('matieres as m', 'm.id', '=', 'cm.matiere_id')
            ->where('ca.annee_scolaire_id', $anneeId)
            ->whereNotNull('cm.enseignant_id')
            ->orderBy('n.ordre')->orderBy('c.suffixe')->orderBy('cm.matiere_id')
            ->get(['cm.classe_annee_id', 'cm.matiere_id', 'cm.enseignant_id', 'n.nom as niveau', 'c.suffixe', 'm.nom_matiere']);

        $classes = [];
        $blocs = [];
        $numeroSalle = 100;

        foreach ($lignes as $l) {
            $caId = (int) $l->classe_annee_id;
            $cle = $l->niveau.'|'.$l->suffixe;
            $code = $codes[(int) $l->matiere_id] ?? $nomsVersCodes[$l->nom_matiere] ?? null;
            if ($code === null) {
                continue;
            }

            if (! isset($classes[$caId])) {
                $numeroSalle++;
                $classes[$caId] = [
                    'cle' => $cle,
                    'salle' => $this->ctx->classes[$cle]['salle'] ?? 'Salle '.$numeroSalle,
                    'lycee' => Curriculum::estLycee($l->niveau),
                    'heures' => 0,
                ];
            }

            $heures = $this->heures($caId, $cle, $code);
            if ($heures <= 0) {
                continue; // conduite : notée, jamais enseignée
            }
            $classes[$caId]['heures'] += $heures;

            $tailles = array_merge(array_fill(0, intdiv($heures, 2), 2), $heures % 2 ? [1] : []);
            foreach ($tailles as $i => $taille) {
                $salles = match (true) {
                    $code === 'EPS' => self::SALLES_EPS,
                    $code === 'INFO' => ['Salle informatique'],
                    $i === 0 && $taille === 2 && $classes[$caId]['lycee'] && $code === 'PCT' => ['Laboratoire de PCT'],
                    $i === 0 && $taille === 2 && $classes[$caId]['lycee'] && $code === 'SVT' => ['Laboratoire de SVT'],
                    default => [$classes[$caId]['salle']],
                };

                $blocs[] = [
                    'ca' => $caId, 'matiere_id' => (int) $l->matiere_id, 'code' => $code,
                    'enseignant' => (int) $l->enseignant_id, 'taille' => $taille, 'salles' => $salles,
                ];
            }
        }

        return [$classes, $blocs];
    }

    /** Volume horaire hebdomadaire : programme mémorisé par PersonnelSeeder, sinon recalculé depuis le curriculum. */
    private function heures(int $caId, string $cle, string $code): int
    {
        if (isset($this->ctx->programmes[$caId][$code]['heures'])) {
            return (int) $this->ctx->programmes[$caId][$code]['heures'];
        }

        [$niveau, $suffixe] = explode('|', $cle, 2);
        $classe = Curriculum::NIVEAUX[$niveau][2][$suffixe] ?? null;
        if ($classe === null) {
            return 0;
        }

        return (int) (Curriculum::programmeDeClasse($classe[0], $classe[1])[$code][1] ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────
    // ORDONNANCEMENT
    // ─────────────────────────────────────────────────────────────────

    /**
     * Une tentative d'ordonnancement gloutonne.
     *
     * @param  list<array<string, mixed>>  $blocs
     * @return array{seances: list<array<string, mixed>>, heures_manquantes: int, scissions: int, cout: float}
     */
    private function planifier(array $blocs): array
    {
        // Charge de chaque enseignant : les plus chargés sont les plus contraints.
        $charge = [];
        foreach ($blocs as $b) {
            $charge[$b['enseignant']] = ($charge[$b['enseignant']] ?? 0) + $b['taille'];
        }

        // Ordre : installations partagées (EPS, labo, informatique), puis séances de 2 h, enseignants chargés.
        $file = [];
        foreach ($blocs as $b) {
            $partage = $b['code'] === 'EPS' ? 3 : (count($b['salles']) === 1 && ! str_starts_with($b['salles'][0], 'Salle ') ? 2 : 0);
            if ($b['salles'][0] === 'Salle informatique') {
                $partage = 1;
            }
            $b['priorite'] = $partage * 1000 + $b['taille'] * 100 + $charge[$b['enseignant']] * 2 + $this->rng->float() * 6;
            $file[] = $b;
        }
        usort($file, fn ($a, $b) => $b['priorite'] <=> $a['priorite']);

        $occupation = ['classe' => [], 'enseignant' => [], 'salle' => []];
        $matiereDuJour = [];   // [ca][jour][matiere] => true
        $heuresDuJour = [];    // [ca][jour] => heures
        $heuresProfJour = [];  // [enseignant][jour] => heures
        $seances = [];
        $manquantes = 0;
        $scissions = 0;
        $cout = 0.0;

        while ($file) {
            $bloc = array_shift($file);
            $choix = $this->meilleurPlacement($bloc, $occupation, $matiereDuJour, $heuresDuJour, $heuresProfJour, true)
                ?? ($bloc['taille'] === 1 ? $this->meilleurPlacement($bloc, $occupation, $matiereDuJour, $heuresDuJour, $heuresProfJour, false) : null);

            if ($choix === null) {
                if ($bloc['taille'] === 2) {
                    // Dernier recours : deux heures séparées.
                    $scissions++;
                    $un = ['taille' => 1] + $bloc;
                    array_unshift($file, $un, $un);
                } else {
                    $manquantes++;
                }

                continue;
            }

            [$jour, $creneauxBloc, $salle, $penalite] = $choix;
            $cout += $penalite;
            foreach ($creneauxBloc as $cid) {
                $occupation['classe'][$bloc['ca']][$jour][$cid] = true;
                $occupation['enseignant'][$bloc['enseignant']][$jour][$cid] = true;
                $occupation['salle'][$salle][$jour][$cid] = true;
                $seances[] = [
                    'ca' => $bloc['ca'], 'matiere_id' => $bloc['matiere_id'], 'enseignant' => $bloc['enseignant'],
                    'jour' => $jour, 'creneau' => $cid, 'salle' => $salle,
                ];
            }
            $matiereDuJour[$bloc['ca']][$jour][$bloc['matiere_id']] = true;
            $heuresDuJour[$bloc['ca']][$jour] = ($heuresDuJour[$bloc['ca']][$jour] ?? 0) + count($creneauxBloc);
            $heuresProfJour[$bloc['enseignant']][$jour] = ($heuresProfJour[$bloc['enseignant']][$jour] ?? 0) + count($creneauxBloc);
        }

        return ['seances' => $seances, 'heures_manquantes' => $manquantes, 'scissions' => $scissions, 'cout' => round($cout, 2)];
    }

    /**
     * Meilleur (jour, créneaux, salle) pour un bloc, ou null si aucun placement sans conflit.
     *
     * @return array{0: string, 1: list<int>, 2: string, 3: float}|null
     */
    private function meilleurPlacement(array $bloc, array $occupation, array $matiereDuJour, array $heuresDuJour, array $heuresProfJour, bool $strict): ?array
    {
        $meilleur = null;
        $lourde = in_array($bloc['code'], self::MATIERES_LOURDES, true);

        foreach ($this->jours as $jour => $ouverts) {
            if ($strict && isset($matiereDuJour[$bloc['ca']][$jour][$bloc['matiere_id']])) {
                continue; // une seule séance d'une matière par jour
            }

            foreach ($ouverts as $debut) {
                $creneauxBloc = [$debut];
                if ($bloc['taille'] === 2) {
                    $second = $this->suivant[$debut] ?? null;
                    if ($second === null || ! in_array($second, $ouverts, true)) {
                        continue;
                    }
                    $creneauxBloc[] = $second;
                }

                $heureDebut = $this->creneaux[$debut]['debut'];
                if ($bloc['code'] === 'EPS' && $heureDebut >= '09:00:00' && $heureDebut < '15:00:00') {
                    continue; // pas de sport en milieu de journée (chaleur)
                }

                if (! $this->libre($occupation['classe'][$bloc['ca']][$jour] ?? [], $creneauxBloc)
                    || ! $this->libre($occupation['enseignant'][$bloc['enseignant']][$jour] ?? [], $creneauxBloc)) {
                    continue;
                }

                $salle = null;
                foreach ($bloc['salles'] as $candidate) {
                    if ($this->libre($occupation['salle'][$candidate][$jour] ?? [], $creneauxBloc)) {
                        $salle = $candidate;
                        break;
                    }
                }
                if ($salle === null) {
                    continue;
                }

                $penalite = $this->penalite($bloc, $lourde, $jour, $creneauxBloc, $heuresDuJour, $heuresProfJour, $occupation);
                if ($meilleur === null || $penalite < $meilleur[3]) {
                    $meilleur = [$jour, $creneauxBloc, $salle, $penalite];
                }
            }
        }

        return $meilleur;
    }

    /** Vrai si aucun des créneaux (ni un créneau qui les chevauche) n'est occupé. */
    private function libre(array $occupes, array $creneauxBloc): bool
    {
        if (! $occupes) {
            return true;
        }
        foreach ($creneauxBloc as $cid) {
            foreach ($this->chevauchements[$cid] as $autre) {
                if (isset($occupes[$autre])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function penalite(array $bloc, bool $lourde, string $jour, array $creneauxBloc, array $heuresDuJour, array $heuresProfJour, array $occupation): float
    {
        $p = 0.0;

        foreach ($creneauxBloc as $cid) {
            $debut = $this->creneaux[$cid]['debut'];
            if (! $this->creneaux[$cid]['matin']) {
                // Après-midi : en complément ; 17h-18h en dernier.
                $p += $debut >= '17:00:00' ? 7.0 : ($debut >= '16:00:00' ? 5.0 : 4.0);
                $p += $lourde ? 2.0 : 0.0;
                // Après-midi compact : commencer à 15h plutôt que laisser un trou.
                $precedent = array_search($cid, $this->suivant, true);
                if ($precedent !== false && ! in_array($precedent, $creneauxBloc, true)
                    && ! isset($occupation['classe'][$bloc['ca']][$jour][$precedent])) {
                    $p += 1.5;
                }
            }
        }

        if ($bloc['code'] === 'EPS') {
            $debut = $this->creneaux[$creneauxBloc[0]]['debut'];
            // 7h ou fin d'après-midi : idéal ; 8h-10h acceptable.
            $p += ($debut < '08:00:00' || $debut >= '16:00:00') ? -3.0 : ($debut >= '15:00:00' ? -2.0 : 1.0);
        }

        // Équilibre de la semaine : éviter les journées surchargées pour la classe et l'enseignant.
        $p += 0.8 * ($heuresDuJour[$bloc['ca']][$jour] ?? 0);
        $chargeProf = $heuresProfJour[$bloc['enseignant']][$jour] ?? 0;
        $p += $chargeProf >= 4 ? 4.0 : 0.3 * $chargeProf;

        return $p + $this->rng->float() * 0.6;
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉCRITURE ET CONTRÔLES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Saisie par le censeur pendant la semaine de prérentrée, classe par classe.
     */
    private function inserer(array $seances, array $classes, int $anneeId): void
    {
        $ordreJours = array_flip(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']);
        usort($seances, fn ($a, $b) => [$a['ca'], $ordreJours[$a['jour']], $this->creneaux[$a['creneau']]['debut']]
            <=> [$b['ca'], $ordreJours[$b['jour']], $this->creneaux[$b['creneau']]['debut']]);

        $rentree = $this->ctx->annees['active']['debut']
            ?? (string) DB::table('annee_scolaires')->where('id', $anneeId)->value('date_debut');
        $limite = $this->ctx->aujourdhui ?? date('Y-m-d');

        $lignes = [];
        $horodatage = [];
        foreach ($seances as $s) {
            if (! isset($horodatage[$s['ca']])) {
                $jourSaisie = $this->rng->date(date('Y-m-d', strtotime($rentree.' -6 days')), date('Y-m-d', strtotime($rentree.' +4 days')));
                $jourSaisie = min($jourSaisie, $limite);
                $horodatage[$s['ca']] = strtotime($this->rng->heureOuvree($jourSaisie, 8, 15).' UTC');
            }
            $horodatage[$s['ca']] += $this->rng->int(15, 70);
            $le = gmdate('Y-m-d H:i:s', $horodatage[$s['ca']]);

            $lignes[] = [
                'classe_annee_id' => $s['ca'],
                'matiere_id' => $s['matiere_id'],
                'jour_semaine' => $s['jour'],
                'creneau_horaire_id' => $s['creneau'],
                'salle' => $s['salle'],
                'created_at' => $le,
                'updated_at' => $le,
            ];
        }

        $this->ctx->inserer('emplois_du_temps', $lignes);
    }

    /**
     * Contrôle SQL indépendant de l'ordonnanceur : deux cours le même jour sur des créneaux qui se
     * chevauchent ne partagent ni la classe, ni l'enseignant (classe_matieres), ni la salle.
     */
    private function verifierAucunConflit(int $anneeId): void
    {
        $conflits = DB::table('emplois_du_temps as a')
            ->join('emplois_du_temps as b', function ($j) {
                $j->on('b.jour_semaine', '=', 'a.jour_semaine')->on('b.id', '>', 'a.id');
            })
            ->join('creneaux_horaires as ha', 'ha.id', '=', 'a.creneau_horaire_id')
            ->join('creneaux_horaires as hb', function ($j) {
                $j->on('hb.id', '=', 'b.creneau_horaire_id')
                    ->on('ha.heure_debut', '<', 'hb.heure_fin')
                    ->on('ha.heure_fin', '>', 'hb.heure_debut');
            })
            ->leftJoin('classe_matieres as ma', function ($j) {
                $j->on('ma.classe_annee_id', '=', 'a.classe_annee_id')->on('ma.matiere_id', '=', 'a.matiere_id');
            })
            ->leftJoin('classe_matieres as mb', function ($j) {
                $j->on('mb.classe_annee_id', '=', 'b.classe_annee_id')->on('mb.matiere_id', '=', 'b.matiere_id');
            })
            ->where(function ($q) {
                $q->whereColumn('a.classe_annee_id', 'b.classe_annee_id')
                    ->orWhereColumn('ma.enseignant_id', 'mb.enseignant_id')
                    ->orWhereColumn('a.salle', 'b.salle');
            })
            ->selectRaw('a.id as a_id, b.id as b_id, a.jour_semaine, a.classe_annee_id as classe_a, b.classe_annee_id as classe_b, ma.enseignant_id as ens_a, mb.enseignant_id as ens_b, a.salle as salle_a, b.salle as salle_b')
            ->limit(5)
            ->get();

        if ($conflits->isNotEmpty()) {
            throw new RuntimeException('EmploiDuTempsSeeder : conflits détectés dans l\'emploi du temps : '.json_encode($conflits->all(), JSON_UNESCAPED_UNICODE));
        }

        // Chaque cours a bien un enseignant assigné (sinon l'écran admin ne pourrait pas le créer).
        $sansEnseignant = DB::table('emplois_du_temps as e')
            ->join('classe_annees as ca', 'ca.id', '=', 'e.classe_annee_id')
            ->leftJoin('classe_matieres as cm', function ($j) {
                $j->on('cm.classe_annee_id', '=', 'e.classe_annee_id')->on('cm.matiere_id', '=', 'e.matiere_id');
            })
            ->where('ca.annee_scolaire_id', $anneeId)
            ->whereNull('cm.enseignant_id')
            ->count();

        if ($sansEnseignant > 0) {
            throw new RuntimeException("EmploiDuTempsSeeder : {$sansEnseignant} cours sans enseignant assigné.");
        }
    }

    private function statistiques(array $resultat, array $classes, array $blocs): void
    {
        $requis = array_sum(array_column($blocs, 'taille'));
        $places = count($resultat['seances']);
        $apresMidi = count(array_filter($resultat['seances'], fn ($s) => ! $this->creneaux[$s['creneau']]['matin']));

        $parProf = [];
        foreach ($resultat['seances'] as $s) {
            $parProf[$s['enseignant']] = ($parProf[$s['enseignant']] ?? 0) + 1;
        }

        $this->command?->info(sprintf(
            '  Emploi du temps : %d classes, %d heures de cours placées sur %d requises (couverture %.1f %%), %d enseignants (%d à %d h/semaine), %.0f %% des cours l\'après-midi, 0 conflit (tentative %d%s).',
            count($classes), $places, $requis, $requis ? 100 * $places / $requis : 100,
            count($parProf), $parProf ? min($parProf) : 0, $parProf ? max($parProf) : 0,
            $places ? 100 * $apresMidi / $places : 0, $resultat['tentative'],
            $resultat['scissions'] ? ', '.$resultat['scissions'].' séance(s) de 2 h scindée(s)' : ''
        ));

        if ($resultat['heures_manquantes'] > 0) {
            $this->command?->warn(sprintf('  %d heure(s) de cours n\'ont pas trouvé de créneau sans conflit.', $resultat['heures_manquantes']));
        }
    }
}
