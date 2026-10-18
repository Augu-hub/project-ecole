<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Data\BeninNames;
use Database\Seeders\Demo\Data\Curriculum;
use Database\Seeders\Demo\Support\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Effectifs : familles (parents + comptes de l'application mobile), élèves et historique
 * d'inscription, simulés sur deux années comme le ferait l'établissement :
 *
 *  - phase « precedente » (2024-2025) : élèves inscrits dans les 12 classes, dont les nouveaux
 *    de l'année (ligne de réinscription « nouveau ») ;
 *  - phase « active » (2025-2026), une fois les notes 2024-2025 connues : campagne de
 *    réinscription (promus / redoublants d'après la moyenne annuelle, orientation en Seconde
 *    selon le profil scientifique, départs et bacheliers en statut « ancien »), puis nouveaux
 *    élèves pour compléter les classes.
 *
 * Un élève n'a qu'une classe courante (eleves.classe_annee_id) : comme dans l'application, la
 * réinscription la déplace, l'historique restant porté par les notes et la table reinscriptions.
 */
class EffectifsSeeder extends Seeder
{
    /** Part des élèves qui rejoignent une famille déjà présente (fratrie). */
    private const PART_FRATRIES = 0.40;

    /** Part des élèves qui quittent l'établissement en cours d'année (statut « inactif »). */
    private const PART_DEPARTS_EN_COURS = 0.006;

    /** @var array<int, int> compteur de matricules par année d'entrée (2 chiffres) */
    private array $sequences = [];

    /** @var list<int> familles pouvant accueillir un enfant de plus */
    private array $famillesOuvertes = [];

    /** @var array<string, array<string, mixed>> élèves créés pendant la phase, par matricule */
    private array $nouveaux = [];

    /** @var list<int> familles créées pendant la phase (à insérer) */
    private array $famillesACreer = [];

    public function __construct(private DemoContext $ctx) {}

    public function run(string $phase): void
    {
        // Familles pouvant encore accueillir un enfant (fratries d'une année sur l'autre)
        $this->famillesOuvertes = array_keys(array_filter(
            $this->ctx->familles,
            fn (array $f) => $f['enfants'] < 3 && ! $f['demo']
        ));

        DB::transaction(fn () => $phase === 'precedente' ? $this->anneePrecedente() : $this->anneeActive());
    }

    // ─────────────────────────────────────────────────────────────────
    // 2024-2025
    // ─────────────────────────────────────────────────────────────────

    private function anneePrecedente(): void
    {
        $rng = $this->ctx->rng;
        $anneeId = $this->ctx->anneeId('precedente');
        $famDemo = $this->familleDemo();

        foreach ($this->ctx->classes as $cle => $classe) {
            $n = $classe['effectif'] + $rng->int(-3, 2);
            $k = $classe['ordre'] - 7;

            for ($i = 0; $i < $n; $i++) {
                // Sixième : surtout des nouveaux, quelques redoublants ; ailleurs ~9 % de transferts.
                $nouveau = $k === 0 ? $rng->chance(0.88) : $rng->chance(0.09);
                $this->creerEleve(2024, $anneeId, $cle, $nouveau);
            }
        }

        // Deux enfants de la famille de démonstration (parent@ccpa.test), bons élèves
        $this->creerEleve(2024, $anneeId, 'Cinquième|', false, $famDemo, ['prenom' => 'Marie-Reine Fifamè', 'sexe' => 'F', 'apt' => 1.3, 'demo' => true]);
        $this->creerEleve(2024, $anneeId, 'Seconde|C', false, $famDemo, ['prenom' => 'Jean-Paul Sèdjro', 'sexe' => 'M', 'apt' => 1.1, 'demo' => true]);

        $this->marquerDepartsEnCours($anneeId);
        $this->enregistrer();

        // Inscriptions « nouveau » de 2024-2025 (les élèves plus anciens précèdent l'application)
        $this->reinscriptionsNouveaux($anneeId);

        $this->command?->info(sprintf('  2024-2025 : %d élèves, %d familles.', count($this->ctx->eleves), count($this->ctx->familles)));
    }

    // ─────────────────────────────────────────────────────────────────
    // 2025-2026 : campagne de réinscription puis nouveaux élèves
    // ─────────────────────────────────────────────────────────────────

    private function anneeActive(): void
    {
        $rng = $this->ctx->rng;
        $precedente = $this->ctx->anneeId('precedente');
        $active = $this->ctx->anneeId('active');
        $moyennes = $this->ctx->moyennesAnnuelles[$precedente] ?? [];

        // 1) Décision pour chaque élève présent en fin d'année 2024-2025
        $decisions = []; // id => ['type' => promu|redoublant, 'profil' => ..., 'niveau' => ...] ou null (départ)
        $orientationSeconde = [];

        foreach ($this->ctx->eleves as $id => $e) {
            if (! isset($e['classes'][$precedente]) || $e['statut'] !== 'actif') {
                continue;
            }
            $classe = $this->ctx->classes[$e['classes'][$precedente]];
            $moy = $moyennes[$id] ?? 10.0;
            $demo = $e['demo'] ?? false;

            if ($classe['niveau'] === 'Terminale') {
                // Bacheliers et départs : statut « ancien ». Les autres redoublent la Terminale.
                $decisions[$id] = ($moy < 10 && $rng->chance(0.55))
                    ? ['type' => 'redoublant', 'niveau' => 'Terminale', 'profil' => $classe['profil']]
                    : null;

                continue;
            }

            // Passage à 10 ; le conseil de classe repêche les 9,50 et une partie des 9,00-9,49.
            $promu = $demo || $moy >= 9.5 || ($moy >= 9 && $rng->chance(0.4));
            if ($promu) {
                if (! $demo && $rng->chance(0.04)) { // déménagement, changement d'établissement
                    $decisions[$id] = null;

                    continue;
                }
                $decisions[$id] = ['type' => 'promu', 'moy' => $moy];
                if ($classe['niveau'] === 'Troisième') {
                    $orientationSeconde[$id] = $e['apt'] + 0.5 * ($e['aff']['MATH'] + $e['aff']['PCT']) + $rng->gauss(0, 0.3);
                }
            } else {
                $decisions[$id] = $rng->chance(0.72)
                    ? ['type' => 'redoublant', 'niveau' => $classe['niveau'], 'profil' => $classe['profil']]
                    : null;
            }
        }

        // Orientation en Seconde : les meilleurs profils scientifiques en C, puis D, le reste en AB
        arsort($orientationSeconde);
        $rang = 0;
        $nb = max(1, count($orientationSeconde));
        foreach (array_keys($orientationSeconde) as $id) {
            $part = $rang++ / $nb;
            $decisions[$id] += ['niveau' => 'Seconde', 'profil' => $part < 0.45 ? '2nde-C' : '2nde-AB'];
        }

        foreach ($decisions as $id => &$d) {
            if ($d === null || isset($d['profil'])) {
                continue;
            }
            $e = $this->ctx->eleves[$id];
            $classe = $this->ctx->classes[$e['classes'][$precedente]];
            [$d['niveau'], $d['profil']] = $this->profilSuivant($classe, $e);
        }
        unset($d);

        // 2) Affectation dans les classes 2025-2026 (même groupe si possible, puis équilibrage)
        $cibles = [];
        foreach ($this->ctx->classes as $cle => $classe) {
            $cibles[$cle] = $classe['effectif'] + $rng->int(-2, 2);
        }
        $remplissage = array_fill_keys(array_keys($this->ctx->classes), 0);
        $classesParProfil = [];
        foreach ($this->ctx->classes as $cle => $classe) {
            $classesParProfil[$classe['niveau'].'|'.$classe['profil']][] = $cle;
        }

        $reinscrits = [];
        $partants = [];
        foreach ($rng->shuffle(array_keys($decisions)) as $id) {
            $d = $decisions[$id];
            if ($d === null) {
                $partants[] = $id;

                continue;
            }
            $e = $this->ctx->eleves[$id];
            $candidates = $classesParProfil[$d['niveau'].'|'.$d['profil']];
            $suffixeActuel = $this->ctx->classes[$e['classes'][$precedente]]['suffixe'];

            $cle = null;
            if ($e['demo'] ?? false) {
                $cle = $candidates[0];
            } elseif ($rng->chance(0.75)) {
                foreach ($candidates as $c) {
                    if ($this->ctx->classes[$c]['suffixe'] === $suffixeActuel && $remplissage[$c] < $cibles[$c]) {
                        $cle = $c;
                    }
                }
            }
            if ($cle === null) {
                usort($candidates, fn ($a, $b) => ($remplissage[$a] / $cibles[$a]) <=> ($remplissage[$b] / $cibles[$b]));
                $cle = $candidates[0];
            }

            // Classe pleine (même après équilibrage) : pas de place, l'élève ne revient pas.
            if ($remplissage[$cle] >= $cibles[$cle] + 5) {
                $partants[] = $id;

                continue;
            }

            $remplissage[$cle]++;
            $reinscrits[$id] = ['cle' => $cle, 'type' => $d['type'], 'moy' => $moyennes[$id] ?? null];
        }

        // 3) Mise à jour des élèves (comme ReinscriptionController::confirmer)
        foreach ($reinscrits as $id => $r) {
            $this->ctx->eleves[$id]['classes'][$active] = $r['cle'];
            if ($r['type'] === 'redoublant') {
                $this->ctx->eleves[$id]['redoublements']++;
            }
        }
        foreach ($partants as $id) {
            $this->ctx->eleves[$id]['statut'] = 'ancien';
        }

        $parClasse = [];
        foreach ($reinscrits as $id => $r) {
            $parClasse[$this->ctx->classeAnnees[$active][$r['cle']]][] = $id;
        }
        $majLe = '2025-09-12 17:30:00';
        foreach ($parClasse as $caId => $ids) {
            foreach (array_chunk($ids, 500) as $lot) {
                DB::table('eleves')->whereIn('id', $lot)->update(['classe_annee_id' => $caId, 'updated_at' => $majLe]);
            }
        }
        foreach (array_chunk($partants, 500) as $lot) {
            DB::table('eleves')->whereIn('id', $lot)->update(['statut' => 'ancien', 'updated_at' => '2025-09-15 10:00:00']);
        }

        // Lignes d'historique de la campagne (juillet à mi-septembre 2025)
        $decideurs = [$this->ctx->comptes['censeur'], $this->ctx->comptes['censeur2'], $this->ctx->comptes['directeur']];
        $lignes = [];
        foreach ($reinscrits as $id => $r) {
            $lycee = Curriculum::estLycee($this->ctx->classes[$r['cle']]['niveau']);
            $quand = $rng->heureOuvree($rng->date('2025-07-07', '2025-09-12'), 8, 17);
            $lignes[] = [
                'eleve_id' => $id, 'annee_scolaire_id' => $active,
                'classe_annee_precedente_id' => $this->ctx->classeAnnees[$precedente][$this->ctx->eleves[$id]['classes'][$precedente]],
                'classe_annee_id' => $this->ctx->classeAnnees[$active][$r['cle']],
                'type' => $r['type'], 'moyenne_suggestion' => $r['moy'],
                'decide_par_user_id' => $rng->chance(0.15) ? $decideurs[2] : $decideurs[$lycee ? 1 : 0],
                'created_at' => $quand, 'updated_at' => $quand,
            ];
        }
        $this->ctx->inserer('reinscriptions', $lignes);

        // 4) Nouveaux élèves pour compléter les classes (dont le benjamin de la famille de démo)
        $famDemo = $this->ctx->familles[0]['id_local'] ?? 0;
        $this->creerEleve(2025, $active, 'Sixième|', true, $famDemo, ['prenom' => 'Gabriel Mahougnon', 'sexe' => 'M', 'apt' => 0.9, 'demo' => true]);
        $remplissage['Sixième|']++;

        foreach ($this->ctx->classes as $cle => $classe) {
            for ($i = $remplissage[$cle]; $i < $cibles[$cle]; $i++) {
                $this->creerEleve(2025, $active, $cle, true);
            }
        }

        $this->marquerDepartsEnCours($active);
        $this->enregistrer();
        $this->reinscriptionsNouveaux($active);

        $actifs = count(array_filter($this->ctx->eleves, fn ($e) => isset($e['classes'][$active])));
        $this->command?->info(sprintf(
            '  2025-2026 : %d élèves (%d réinscrits dont %d redoublants, %d nouveaux), %d départs ; %d familles au total.',
            $actifs, count($reinscrits), count(array_filter($reinscrits, fn ($r) => $r['type'] === 'redoublant')),
            $actifs - count($reinscrits), count($partants), count($this->ctx->familles)
        ));
    }

    /**
     * Niveau et filière de l'année suivante pour un élève promu.
     *
     * @return array{0: string, 1: string}
     */
    private function profilSuivant(array $classe, array $e): array
    {
        $rng = $this->ctx->rng;

        return match ($classe['profil']) {
            // Seconde A → Première A ; Seconde C → Première C (profil mathématique) ou D (sciences de la vie)
            '2nde-AB' => ['Première', '1ere-A1'],
            '2nde-C' => ['Première', $e['apt'] + $e['aff']['MATH'] - $e['aff']['SVT'] + $rng->gauss(0, 0.3) < 0.75 ? '1ere-D' : '1ere-C'],
            default => match ($classe['niveau']) {
                'Sixième' => ['Cinquième', 'college1'],
                'Cinquième' => ['Quatrième', 'college2'],
                'Quatrième' => ['Troisième', 'college2'],
                'Première' => ['Terminale', 'tle-'.substr($classe['profil'], 5)],
            },
        };
    }

    // ─────────────────────────────────────────────────────────────────
    // CRÉATION EN MÉMOIRE
    // ─────────────────────────────────────────────────────────────────

    private function creerEleve(int $anneeDebut, int $anneeId, string $cle, bool $nouveau, ?int $famille = null, array $options = []): void
    {
        $rng = $this->ctx->rng;
        $classe = $this->ctx->classes[$cle];
        $k = $classe['ordre'] - 7;

        $famille ??= $this->choisirFamille();
        $fam = &$this->ctx->familles[$famille];
        $fam['enfants']++;
        if ($fam['enfants'] >= 3) {
            $this->famillesOuvertes = array_values(array_diff($this->famillesOuvertes, [$famille]));
        }

        $sexe = $options['sexe'] ?? ($rng->chance(0.51) ? 'F' : 'M');
        $prenom = $options['prenom'] ?? $this->prenomEleve($sexe, $fam['musulmane']);

        // Retard scolaire (âge) et année d'entrée dans l'établissement
        $retard = (int) $rng->weighted([0 => 70, 1 => 23, 2 => 7]);
        $avance = $retard === 0 && $rng->chance(0.05) ? 1 : 0;
        if ($nouveau) {
            $entree = $anneeDebut;
        } elseif ($k === 0) {
            $entree = $anneeDebut - 1; // redoublant de Sixième
            $retard = max(1, $retard);
        } else {
            $entree = $rng->chance(0.75) ? $anneeDebut - $k - min($retard, 1) : $anneeDebut - $rng->int(1, $k);
        }

        $age = 11 + $k + $retard - $avance;
        $naissance = $rng->date(($anneeDebut - $age - 1).'-09-01', ($anneeDebut - $age).'-08-31');

        $yy = $entree % 100;
        $this->sequences[$yy] = ($this->sequences[$yy] ?? 0) + 1;
        $matricule = sprintf('%02d-01%04d', $yy, $this->sequences[$yy]);

        $inscription = $entree >= 2024
            ? $rng->date($entree.'-07-15', $entree.'-09-30')
            : $rng->date($entree.'-08-01', $entree.'-09-25');

        $aff = [];
        foreach (array_keys(Curriculum::MATIERES) as $code) {
            $aff[$code] = $rng->gauss(0, 0.55);
        }

        $this->nouveaux[$matricule] = [
            'matricule' => $matricule,
            'nom' => $fam['nom'],
            'prenom' => $prenom,
            'sexe' => $sexe,
            'naissance' => $naissance,
            'famille' => $famille,
            'apt' => $options['apt'] ?? $rng->gauss(0, 1) + (Curriculum::BONUS_SERIE[$classe['profil']] ?? 0),
            'aff' => $aff,
            'comp' => $rng->gauss(0, 1),
            'tendance' => [],
            'classes' => [$anneeId => $cle],
            'entree' => $entree,
            'inscription' => $inscription,
            'nouveau_en' => $nouveau ? $anneeId : null,
            'statut' => 'actif',
            'depart' => [],
            'redoublements' => $retard > 0 ? 1 : 0,
            'demo' => $options['demo'] ?? false,
        ];
    }

    private function prenomEleve(string $sexe, bool $musulmane): string
    {
        $rng = $this->ctx->rng;
        $f = $sexe === 'F';

        if ($musulmane && $rng->chance(0.8)) {
            return $rng->pick($f ? BeninNames::PRENOMS_MUSULMANS_F : BeninNames::PRENOMS_MUSULMANS_M);
        }

        $prenom = $rng->pick($f ? BeninNames::PRENOMS_F : BeninNames::PRENOMS_M);

        return match (true) {
            $rng->chance(0.48) => $prenom.' '.$rng->pick($f ? BeninNames::PRENOMS_LOCAUX_F : BeninNames::PRENOMS_LOCAUX_M),
            $rng->chance(0.12) => $prenom.' '.$rng->pick($f ? BeninNames::PRENOMS_F : BeninNames::PRENOMS_M),
            default => $prenom,
        };
    }

    private function choisirFamille(): int
    {
        if ($this->famillesOuvertes && $this->ctx->rng->chance(self::PART_FRATRIES)) {
            return $this->ctx->rng->pick($this->famillesOuvertes);
        }

        return $this->nouvelleFamille();
    }

    /**
     * Famille : nom, parent principal (père, mère ou tuteur) et parfois un second parent, profil de
     * paiement (utilisé par FinanceSeeder) et moyen de paiement habituel.
     */
    private function nouvelleFamille(array $force = []): int
    {
        $rng = $this->ctx->rng;
        $id = count($this->ctx->familles);
        $nom = $force['nom'] ?? $rng->pick(BeninNames::NOMS);
        $musulmane = $force['musulmane'] ?? $rng->chance(0.07);
        $quartier = $rng->pick(array_keys(BeninNames::QUARTIERS));
        $ville = BeninNames::QUARTIERS[$quartier];
        $adresse = match ($rng->int(1, 3)) {
            1 => 'Carré '.$rng->int(12, 2450).', '.$quartier.', '.$ville,
            2 => 'Lot '.$rng->int(3, 980).', '.$quartier.', '.$ville,
            default => 'Maison '.$nom.', '.$quartier.', '.$ville,
        };

        $relation = $force['relation'] ?? $rng->weighted(['père' => 56, 'mère' => 36, 'tuteur' => 5, 'tutrice' => 3]);
        $parents = [$this->parentFictif($relation, $nom, $musulmane, $adresse, $force)];
        if (! $force && $rng->chance(0.08) && in_array($relation, ['père', 'mère'], true)) {
            $parents[] = $this->parentFictif($relation === 'père' ? 'mère' : 'père', $nom, $musulmane, $adresse);
        }

        $this->ctx->familles[$id] = [
            'id_local' => $id,
            'nom' => $nom,
            'musulmane' => $musulmane,
            'parents' => $parents,
            'enfants' => 0,
            'payeur' => $force['payeur'] ?? $rng->weighted(['exemplaire' => 55, 'normal' => 28, 'retard' => 12, 'difficulte' => 5]),
            'mode' => $force['mode'] ?? $rng->weighted(['especes' => 40, 'mobile_money' => 40, 'virement' => 8, 'cheque' => 8, 'carte' => 4]),
            'mobile' => $force['mobile'] ?? $rng->chance(0.62), // utilise l'application mobile
            'principal_id' => null,
            'inseree' => false,
            'demo' => (bool) $force,
        ];
        if (! $force) {
            $this->famillesOuvertes[] = $id;
        }
        $this->famillesACreer[] = $id;

        return $id;
    }

    private function parentFictif(string $relation, string $nomFamille, bool $musulmane, string $adresse, array $force = []): array
    {
        $rng = $this->ctx->rng;
        $homme = in_array($relation, ['père', 'tuteur'], true);

        if (isset($force['prenom'])) {
            $prenom = $force['prenom'];
        } elseif ($musulmane) {
            $prenom = $rng->pick($homme ? BeninNames::PRENOMS_MUSULMANS_M : BeninNames::PRENOMS_MUSULMANS_F);
        } else {
            $prenom = $rng->pick($homme ? BeninNames::PRENOMS_M : BeninNames::PRENOMS_F);
            if ($rng->chance(0.3)) {
                $prenom .= ' '.$rng->pick($homme ? BeninNames::PRENOMS_LOCAUX_M : BeninNames::PRENOMS_LOCAUX_F);
            }
        }

        // Une mère peut figurer sous son nom de jeune fille ; un tuteur porte souvent un autre nom.
        $nom = match (true) {
            isset($force['nom']) => $force['nom'],
            $relation === 'mère' && $rng->chance(0.4), in_array($relation, ['tuteur', 'tutrice'], true) && $rng->chance(0.6) => $rng->pick(BeninNames::NOMS),
            default => $nomFamille,
        };

        $telephone = isset($force['telephone']) ? $this->ctx->reserverTelephone($force['telephone']) : $this->ctx->telephone();

        return [
            'relation' => $relation,
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => $telephone,
            'whatsapp' => match ($rng->weighted(['meme' => 68, 'autre' => 17, 'aucun' => 15])) {
                'meme' => $telephone,
                'autre' => $this->ctx->telephone(),
                default => null,
            },
            'profession' => $rng->chance(0.9) ? $rng->pick(BeninNames::PROFESSIONS) : null,
            'adresse' => $adresse,
            'email' => isset($force['email']) ? $this->ctx->reserverEmail($force['email']) : $this->ctx->email($prenom, $nom, 'parents.ccpa.test'),
            'id' => null,
        ];
    }

    /** Famille du compte de démonstration parent@ccpa.test (téléphone 0197000101 pour l'app mobile). */
    private function familleDemo(): int
    {
        return $this->nouvelleFamille([
            'nom' => 'AKPLOGAN', 'relation' => 'mère', 'prenom' => 'Bernadette', 'telephone' => '0197000101',
            'email' => 'parent@ccpa.test', 'musulmane' => false, 'payeur' => 'normal', 'mode' => 'mobile_money', 'mobile' => true,
        ]);
    }

    private function marquerDepartsEnCours(int $anneeId): void
    {
        foreach ($this->nouveaux as &$e) {
            if (! ($e['demo'] ?? false) && $this->ctx->rng->chance(self::PART_DEPARTS_EN_COURS)) {
                $e['depart'][$anneeId] = $this->ctx->rng->int(1, 2); // a des notes sur les 1 ou 2 premiers trimestres
                $e['statut'] = 'inactif';
            }
        }
        unset($e);

        // Élèves déjà connus (réinscrits) : mêmes départs en cours d'année
        foreach ($this->ctx->eleves as &$e) {
            if (isset($e['classes'][$anneeId]) && $e['statut'] === 'actif' && ! ($e['demo'] ?? false)
                && $this->ctx->rng->chance(self::PART_DEPARTS_EN_COURS)) {
                $e['depart'][$anneeId] = $this->ctx->rng->int(1, 2);
                $e['statut'] = 'inactif';
                $e['statut_a_ecrire'] = true;
            }
        }
        unset($e);
    }

    // ─────────────────────────────────────────────────────────────────
    // ÉCRITURE EN BASE
    // ─────────────────────────────────────────────────────────────────

    private function enregistrer(): void
    {
        $this->enregistrerFamilles();

        // Élèves
        $lignes = [];
        foreach ($this->nouveaux as $e) {
            $anneeId = array_key_first($e['classes']);
            $creeLe = $e['entree'] >= 2024
                ? $this->ctx->rng->heureOuvree($e['inscription'], 8, 17)
                : '2024-08-20 '.sprintf('%02d:%02d:00', $this->ctx->rng->int(9, 16), $this->ctx->rng->int(0, 59)); // reprise initiale des fichiers
            $lignes[] = [
                'matricule' => $e['matricule'], 'nom' => $e['nom'], 'prenom' => $e['prenom'], 'sexe' => $e['sexe'],
                'date_naissance' => $e['naissance'], 'photo' => null,
                'classe_annee_id' => $this->ctx->classeAnnees[$anneeId][$e['classes'][$anneeId]],
                'parent_id' => $this->ctx->familles[$e['famille']]['principal_id'],
                'date_inscription' => $e['inscription'], 'statut' => $e['statut'],
                'created_at' => $creeLe, 'updated_at' => $creeLe,
            ];
        }
        $this->ctx->inserer('eleves', $lignes);

        $ids = DB::table('eleves')->whereIn('matricule', array_keys($this->nouveaux))->pluck('id', 'matricule');
        foreach ($this->nouveaux as $matricule => $e) {
            $this->ctx->eleves[(int) $ids[$matricule]] = $e;
        }

        // Liens élève ↔ parents (parent principal et éventuel second parent)
        $pivots = [];
        foreach ($this->nouveaux as $matricule => $e) {
            foreach ($this->ctx->familles[$e['famille']]['parents'] as $p) {
                $pivots[] = [
                    'eleve_id' => (int) $ids[$matricule], 'parent_id' => $p['id'], 'relation' => $p['relation'],
                    'created_at' => $e['inscription'].' 10:00:00', 'updated_at' => $e['inscription'].' 10:00:00',
                ];
            }
        }
        $this->ctx->inserer('eleve_parent', $pivots);

        // Départs en cours d'année d'élèves déjà en base
        $inactifs = [];
        foreach ($this->ctx->eleves as $id => &$e) {
            if (! empty($e['statut_a_ecrire'])) {
                $inactifs[] = $id;
                unset($e['statut_a_ecrire']);
            }
        }
        unset($e);
        foreach (array_chunk($inactifs, 500) as $lot) {
            DB::table('eleves')->whereIn('id', $lot)->update(['statut' => 'inactif', 'updated_at' => '2026-02-10 11:00:00']);
        }

        $this->nouveaux = [];
    }

    private function enregistrerFamilles(): void
    {
        $users = [];
        $parents = [];
        foreach ($this->famillesACreer as $f) {
            $fam = $this->ctx->familles[$f];
            // Date de création du compte : à l'inscription du premier enfant
            $premier = null;
            foreach ($this->nouveaux as $e) {
                if ($e['famille'] === $f) {
                    $premier = $e['entree'] >= 2024 ? $e['inscription'] : '2024-08-20';
                    break;
                }
            }
            $creeLe = ($premier ?? '2024-08-20').' '.sprintf('%02d:%02d:00', $this->ctx->rng->int(8, 17), $this->ctx->rng->int(0, 59));

            foreach ($fam['parents'] as $p) {
                $users[] = $this->ctx->ligneUser($p['prenom'].' '.$p['nom'], $p['email'], 'parent', $creeLe);
                $parents[$p['email']] = [
                    'nom' => $p['nom'], 'prenom' => $p['prenom'], 'telephone' => $p['telephone'], 'whatsapp' => $p['whatsapp'],
                    'profession' => $p['profession'], 'adresse' => $p['adresse'], 'created_at' => $creeLe, 'updated_at' => $creeLe,
                ];
            }
        }
        $this->ctx->inserer('users', $users);

        $userIds = [];
        foreach (array_chunk(array_keys($parents), 900) as $lot) {
            $userIds += DB::table('users')->whereIn('email', $lot)->pluck('id', 'email')->all();
        }
        $lignes = [];
        foreach ($parents as $email => $p) {
            $lignes[] = ['user_id' => $userIds[$email]] + $p;
        }
        $this->ctx->inserer('parents', $lignes);

        $parentIds = [];
        foreach (array_chunk(array_column($lignes, 'telephone'), 900) as $lot) {
            $parentIds += DB::table('parents')->whereIn('telephone', $lot)->pluck('id', 'telephone')->all();
        }

        foreach ($this->famillesACreer as $f) {
            foreach ($this->ctx->familles[$f]['parents'] as $i => $p) {
                $this->ctx->familles[$f]['parents'][$i]['id'] = (int) $parentIds[$p['telephone']];
                $this->ctx->familles[$f]['parents'][$i]['user_id'] = (int) $userIds[$p['email']];
            }
            $this->ctx->familles[$f]['principal_id'] = $this->ctx->familles[$f]['parents'][0]['id'];
            $this->ctx->familles[$f]['inseree'] = true;
        }
        $this->famillesACreer = [];
    }

    /** Ligne « nouveau » pour chaque élève arrivé dans l'année (saisie par le secrétariat). */
    private function reinscriptionsNouveaux(int $anneeId): void
    {
        $secretariat = [$this->ctx->comptes['saisisseur'], $this->ctx->comptes['saisisseur2'], $this->ctx->comptes['econome'], $this->ctx->comptes['admin']];
        $lignes = [];
        foreach ($this->ctx->eleves as $id => $e) {
            if (($e['nouveau_en'] ?? null) !== $anneeId) {
                continue;
            }
            $quand = $this->ctx->rng->heureOuvree($e['inscription'], 8, 17);
            $lignes[] = [
                'eleve_id' => $id, 'annee_scolaire_id' => $anneeId, 'classe_annee_precedente_id' => null,
                'classe_annee_id' => $this->ctx->classeAnnees[$anneeId][$e['classes'][$anneeId]],
                'type' => 'nouveau', 'moyenne_suggestion' => null,
                'decide_par_user_id' => $this->ctx->rng->pick($secretariat),
                'created_at' => $quand, 'updated_at' => $quand,
            ];
        }
        $this->ctx->inserer('reinscriptions', $lignes);
    }
}
