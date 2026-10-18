<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Data\BeninNames;
use Database\Seeders\Demo\Data\Curriculum;
use Database\Seeders\Demo\Support\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Vie scolaire : certificats de scolarité, communiqués, messagerie parents ↔ personnel, journal des
 * connexions à l'application mobile et historique des imports, sur les deux années scolaires.
 *
 * Règles respectées (voir contrôleurs, services et tests de chaque module) :
 * - certificats : numéro « CCPA-CERT-XXXXXXXX » (8 caractères [A-Z0-9], comme
 *   CertificatScolariteController::genererNumeroUnique) unique, donc vérifiable sur
 *   /certificats/verifier/{numero} ; émis pour un élève ACTIF à la date d'émission, sur la classe de
 *   l'année concernée, par un rôle autorisé (admin, censeur, directeur, économe) ;
 * - communiqués : types de l'enum, toujours rattachés à au moins une classe de l'année (le formulaire
 *   l'impose et l'API parent ne montre que les communiqués des classes des enfants), date
 *   d'expiration postérieure à la publication ; un événement programmé peut être publié à l'avance ;
 * - messagerie : une conversation par couple (parent, membre du personnel ayant accès à la
 *   messagerie : admin, directeur, censeur, économe, préfet, surveillant), parents utilisant
 *   l'application ; messages insérés dans l'ordre chronologique (l'application lit le dernier
 *   message par id) ; un message est lu dès que l'autre partie a répondu, seuls les plus récents
 *   restent non lus ;
 * - connexions : uniquement des connexions RÉUSSIES de parents à l'application mobile (c'est ce que
 *   journalise Api\AuthController::login ; un échec n'écrit rien), avec parent_id, jamais avant la
 *   création du compte ni après « aujourd'hui », insérées dans l'ordre chronologique (le tableau de
 *   bord lit les dernières par id) ; un parent qui écrit dans la messagerie s'est connecté avant ;
 * - volumes déduits des effectifs, des familles et du personnel réellement chargés.
 *
 * Les documents élèves (table fichiers) ne sont pas générés : chaque ligne pointe vers un fichier
 * du disque « documents_eleves » qui n'existerait pas (téléchargement en erreur).
 */
class VieScolaireSeeder extends Seeder
{
    private const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    private const MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    private const TRIMESTRES = ['premier trimestre', 'deuxième trimestre', 'troisième trimestre'];

    private const ALPHABET_CERTIFICAT = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /** Part des élèves d'une année qui demandent au moins un certificat (hors départs). */
    private const PART_CERTIFICATS = 0.12;

    /** Conversations par parent inscrit (≈ 0,4). */
    private const CONVERSATIONS_PAR_PARENT = 0.4;

    /** Connexions par mois et par parent utilisateur de l'application, selon l'année. */
    private const CONNEXIONS_PAR_MOIS = ['precedente' => 0.7, 'active' => 1.1];

    /** Jours fériés fixes (MM-JJ) : ni certificat ni communiqué rédigé ces jours-là. */
    private const FERIES = ['01-01', '01-10', '05-01', '08-01', '08-15', '11-01', '12-25'];

    /** Comptes du personnel féminins (formules de politesse). */
    private const COMPTES_FEMININS = ['econome', 'censeur2', 'surveillant3', 'surveillant6'];

    /** Date (Y-m-d) au-delà de laquelle rien n'est daté : min(aujourd'hui, fin de l'année active). */
    private string $plafond;

    /** Horodatage maximal (Y-m-d H:i:s) des messages et connexions. */
    private string $limite;

    /** @var array<int, array{cle: string, role: string, civ: string, nom: string}> personnel joignable, par user id */
    private array $staff = [];

    /** @var array<int, list<int>> élèves de chaque famille */
    private array $enfants = [];

    /** @var array<string, string> code matière par matiere_id */
    private array $codesMatieres = [];

    /** @var array<int, string> date de création du compte, par user id */
    private array $comptesCreesLe = [];

    /** @var array<int, string> premier message envoyé par le parent (par parent_id) */
    private array $premierMessage = [];

    private array $stats = [];

    public function __construct(private DemoContext $ctx) {}

    public function run(): void
    {
        $fin = $this->ctx->annees['active']['fin'];
        $this->plafond = min($this->ctx->aujourdhui, $fin);
        $this->limite = $this->plafond === $this->ctx->aujourdhui
            ? min(now()->format('Y-m-d H:i:s'), $this->plafond.' 23:59:59')
            : $this->plafond.' 18:30:00';

        foreach ($this->ctx->eleves as $id => $e) {
            $this->enfants[$e['famille']][] = $id;
        }
        $this->codesMatieres = array_flip($this->ctx->matieres);

        DB::transaction(function () {
            $this->certificats();
            $this->communiques();
            $this->messagerie();
            $this->connexions();
            $this->imports();
        });

        $s = $this->stats;
        $this->command?->info(sprintf(
            '  Certificats : %d (2024-2025) + %d (2025-2026). Communiqués : %d (%d rattachements de classes).',
            $s['certificats']['precedente'] ?? 0, $s['certificats']['active'] ?? 0, $s['communiques'], $s['communique_classes']
        ));
        $this->command?->info(sprintf(
            '  Messagerie : %d conversations, %d messages (%d non lus). Connexions à l\'application : %d (%d parents). Imports : %d.',
            $s['conversations'], $s['messages'], $s['non_lus'], $s['connexions'], $s['parents_connectes'], $s['imports']
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // CERTIFICATS DE SCOLARITÉ
    // ─────────────────────────────────────────────────────────────────

    private function certificats(): void
    {
        $rng = $this->ctx->rng;
        $c = $this->ctx->comptes;
        $economat = $this->ctx->personnel['econome'] ?? [$c['admin']];
        $numeros = [];
        $lignes = [];

        foreach (['precedente', 'active'] as $cleAnnee) {
            $annee = $this->ctx->annees[$cleAnnee];
            $anneeId = $annee['id'];
            $periodes = $this->ctx->periodes[$anneeId];
            $y = (int) substr($annee['debut'], 0, 4);
            $ouverture = $this->plus($periodes[0]['debut'], 3);
            $this->stats['certificats'][$cleAnnee] = 0;

            foreach ($this->ctx->eleves as $id => $e) {
                if (! isset($e['classes'][$anneeId])) {
                    continue;
                }
                $cleClasse = $e['classes'][$anneeId];
                $classe = $this->ctx->classes[$cleClasse];
                $niveau = $classe['niveau'];
                $lycee = Curriculum::estLycee($niveau);
                $demandes = []; // [date, motif]

                if (isset($e['depart'][$anneeId])) {
                    // Départ en cours d'année : certificat pour la nouvelle école, avant la fin du trimestre de départ
                    $finTrimestre = $periodes[$e['depart'][$anneeId] - 1]['fin'];
                    if ($rng->chance(0.6)) {
                        $demandes[] = [$this->plus($finTrimestre, -$rng->int(0, 12)), $rng->pick([
                            'Changement d\'établissement (déménagement)', 'Inscription dans un autre établissement',
                            'Mutation professionnelle du parent',
                        ])];
                    }
                } else {
                    if ($rng->chance(self::PART_CERTIFICATS)) {
                        $date = $rng->date($ouverture, $annee['fin']);
                        $demandes[] = [$date, $this->motifCertificat($date, $niveau)];
                    }
                    // Élève qui ne revient pas l'année suivante : certificat demandé pendant l'été
                    if ($cleAnnee === 'precedente' && ! isset($e['classes'][$this->ctx->anneeId('active')])) {
                        $terminale = $niveau === 'Terminale';
                        if ($rng->chance($terminale ? 0.35 : 0.55)) {
                            $demandes[] = [$rng->date(($y + 1).'-07-01', ($y + 1).'-08-29'), $terminale
                                ? $rng->pick(['Préinscription à l\'université d\'Abomey-Calavi', 'Dossier de bourse d\'études supérieures', 'Inscription en classe préparatoire', 'Demande de visa d\'études'])
                                : $rng->pick(['Inscription dans un autre établissement', 'Changement d\'établissement (déménagement)', 'Mutation professionnelle du parent'])];
                        }
                    }
                    // Duplicata (original égaré)
                    if ($demandes && $rng->chance(0.04)) {
                        $demandes[] = [$this->plus($demandes[0][0], $rng->int(10, 60)), 'Duplicata (original égaré)'];
                    }
                }

                foreach ($demandes as [$date, $motif]) {
                    $date = $this->ouvrableAvant($date);
                    if ($date > $this->plafond || $date < $ouverture) {
                        continue;
                    }
                    do {
                        $numero = 'CCPA-CERT-'.$rng->code(8, self::ALPHABET_CERTIFICAT);
                    } while (isset($numeros[$numero]));
                    $numeros[$numero] = true;

                    $auteur = match ($rng->weighted(['censeur' => 40, 'economat' => 32, 'admin' => 18, 'directeur' => 10])) {
                        'censeur' => $c[$lycee ? 'censeur2' : 'censeur'] ?? $c['censeur'],
                        'economat' => $rng->pick($economat),
                        'admin' => $c['admin'],
                        default => $c['directeur'],
                    };
                    $quand = min($rng->heureOuvree($date, 8, 16), $this->limite);
                    $lignes[] = [
                        'eleve_id' => $id, 'classe_annee_id' => $this->ctx->classeAnnees[$anneeId][$cleClasse],
                        'numero_unique' => $numero, 'motif' => $motif, 'genere_par_user_id' => $auteur,
                        'created_at' => $quand, 'updated_at' => $quand,
                    ];
                    $this->stats['certificats'][$cleAnnee]++;
                }
            }
        }

        usort($lignes, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
        $this->ctx->inserer('certificats_scolarite', $lignes);
    }

    private function motifCertificat(string $date, string $niveau): ?string
    {
        $rng = $this->ctx->rng;
        $mois = (int) substr($date, 5, 2);

        if (in_array($mois, [12, 1, 2], true) && $niveau === 'Troisième' && $rng->chance(0.6)) {
            return 'Constitution du dossier du BEPC';
        }
        if (in_array($mois, [12, 1, 2], true) && $niveau === 'Terminale' && $rng->chance(0.6)) {
            return 'Constitution du dossier du baccalauréat';
        }

        return $rng->weighted([
            '' => 18,
            'Dossier d\'allocations familiales (CNSS)' => 16,
            'Constitution du dossier de bourse' => 10,
            'Demande de passeport' => 9,
            'Prise en charge par l\'employeur du parent' => 9,
            'Demande de visa' => 5,
            'Établissement de la carte d\'identité' => 6,
            'Assurance santé (mutuelle du parent)' => 6,
            'Inscription à un concours' => 4,
            'Abonnement de transport scolaire' => 3,
            'Dossier administratif' => 6,
        ]) ?: null;
    }

    // ─────────────────────────────────────────────────────────────────
    // COMMUNIQUÉS
    // ─────────────────────────────────────────────────────────────────

    private function communiques(): void
    {
        $rng = $this->ctx->rng;
        $tous = [];
        foreach (['precedente', 'active'] as $cle) {
            foreach ($this->communiquesDeLAnnee($cle) as $com) {
                // Une alerte (intempéries) est rédigée le matin même, avant l'arrivée des élèves
                $urgence = $com['urgence'] ?? false;
                $creeLe = $urgence ? $com['pub'] : $this->ouvrableAvant($this->plus($com['pub'], -$rng->int(0, 2)));
                if ($creeLe > $this->plafond) {
                    continue; // pas encore rédigé
                }
                $com['created_at'] = min($urgence ? $rng->heureOuvree($creeLe, 5, 6) : $rng->heureOuvree($creeLe, 8, 17), $this->limite);
                $com['annee'] = $cle;
                $tous[] = $com;
            }
        }
        usort($tous, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        $pivots = [];
        foreach ($tous as $com) {
            $id = DB::table('communiques')->insertGetId([
                'titre' => $com['titre'], 'contenu' => $com['contenu'], 'type' => $com['type'],
                'date_publication' => $com['pub'], 'date_expiration' => $com['exp'],
                'actif' => $com['actif'] ?? true, 'created_by' => $this->ctx->comptes[$com['auteur']] ?? $this->ctx->comptes['admin'],
                'created_at' => $com['created_at'], 'updated_at' => $com['created_at'],
            ]);
            foreach ($this->cibles($this->ctx->anneeId($com['annee']), $com['cible']) as $caId) {
                $pivots[] = ['communique_id' => $id, 'classe_annee_id' => $caId];
            }
        }
        $this->ctx->inserer('communique_classe_annee', $pivots);

        $this->stats['communiques'] = count($tous);
        $this->stats['communique_classes'] = count($pivots);
    }

    /**
     * Classes de l'année visées par un communiqué.
     *
     * @return list<int>
     */
    private function cibles(int $anneeId, string $cible): array
    {
        $niveaux = match ($cible) {
            'college' => ['Sixième', 'Cinquième', 'Quatrième', 'Troisième'],
            'lycee' => ['Seconde', 'Première', 'Terminale'],
            'examens' => ['Troisième', 'Terminale'],
            'entrants' => ['Sixième', 'Seconde'],
            'tous' => null,
            default => [$cible],
        };
        $ids = [];
        foreach ($this->ctx->classeAnnees[$anneeId] as $cle => $caId) {
            if ($niveaux === null || in_array($this->ctx->classes[$cle]['niveau'], $niveaux, true)) {
                $ids[] = $caId;
            }
        }

        return $ids;
    }

    /**
     * Calendrier des communiqués d'une année, daté d'après les trimestres réellement chargés.
     *
     * @return list<array{titre: string, contenu: string, type: string, pub: string, exp: ?string, cible: string, auteur: string, actif?: bool}>
     */
    private function communiquesDeLAnnee(string $cle): array
    {
        $rng = $this->ctx->rng;
        $annee = $this->ctx->annees[$cle];
        $libelle = $annee['libelle'];
        $y = (int) substr($annee['debut'], 0, 4);
        $suivante = ($y + 1).'-'.($y + 2);
        [$t1, $t2, $t3] = $this->ctx->periodes[$annee['id']];
        $rentree = $t1['debut'];
        $liste = [];

        // Rentrée
        $liste['rentree'] = [
            'titre' => "Rentrée scolaire {$libelle}",
            'contenu' => "Chers parents,\n\nLa rentrée des classes de l'année scolaire {$libelle} est fixée au {$this->fr($rentree, true)} à 7 h 00 pour tous les élèves, de la Sixième à la Terminale. Les élèves se présenteront en tenue réglementaire, munis de leurs fournitures et du reçu du premier versement de la scolarité.\n\n"
                ."Les listes de classes seront affichées au portail principal à partir du {$this->fr($this->plus($rentree, -3))}. Les nouveaux élèves sont attendus avec leurs parents le jour de la rentrée à 8 h 00 dans la salle polyvalente pour une séance d'accueil.\n\n"
                ."Nous rappelons que le portail ferme à 7 h 05. Bonne rentrée à tous !\n\nLa Direction",
            'type' => 'general', 'pub' => $this->plus($rentree, -14), 'exp' => $this->plus($rentree, 7), 'cible' => 'tous', 'auteur' => 'directeur',
        ];

        $messe = $this->plus($rentree, 4);
        $liste['messe'] = [
            'titre' => 'Messe du Saint-Esprit et ouverture de l\'année',
            'contenu' => "La communauté éducative du Complexe Catholique Père Aupiais se réunira le {$this->fr($messe)} à 8 h 00 pour la messe du Saint-Esprit, qui confie la nouvelle année scolaire au Seigneur. Elle sera animée par la chorale des élèves.\n\n"
                .'Les parents qui le souhaitent sont cordialement invités. Les cours reprendront normalement à 10 h 15.',
            'type' => 'evenement', 'pub' => $this->plus($rentree, 1), 'exp' => $messe, 'cible' => 'tous', 'auteur' => 'directeur',
        ];

        // Visite médicale (année active)
        $visite = $this->prochain($y.'-10-14', 2);
        $liste['visite'] = [
            'titre' => 'Visite médicale des élèves de Sixième et de Seconde',
            'contenu' => "Une visite médicale systématique des élèves de Sixième et de Seconde aura lieu le {$this->fr($visite)} à l'infirmerie, avec l'appui d'un médecin du centre de santé de la commune : contrôle de la vue, de la tension, du poids et mise à jour du carnet de vaccination.\n\n"
                .'Merci de remettre à votre enfant son carnet de santé. Les résultats vous seront communiqués de manière confidentielle en cas de besoin.',
            'type' => 'general', 'pub' => $this->plus($visite, -7), 'exp' => $visite, 'cible' => 'entrants', 'auteur' => 'prefet',
        ];

        // Fortes pluies (année précédente) : communiqué urgent, désactivé une fois passé
        $pluie = $this->prochain($y.'-10-16', 4);
        $liste['pluies'] = [
            'titre' => 'Suspension des cours ce '.$this->fr($pluie).' (fortes pluies)',
            'contenu' => "En raison des pluies diluviennes de la nuit et de l'inondation des voies d'accès à l'établissement, les cours sont suspendus ce {$this->fr($pluie)}. Les élèves déjà présents sont pris en charge par la surveillance jusqu'à l'arrivée de leurs parents.\n\n"
                ."Les cours reprendront normalement demain, {$this->fr($this->plus($pluie, 1))}, à 7 h 00. Les devoirs prévus ce jour sont reportés.",
            'type' => 'urgent', 'pub' => $pluie, 'exp' => $this->plus($pluie, 1), 'cible' => 'tous', 'auteur' => 'prefet', 'actif' => false, 'urgence' => true,
        ];

        $reunion = $this->prochain($y.'-10-11', 6);
        $liste['reunion'] = [
            'titre' => 'Rencontre parents-professeurs du premier trimestre',
            'contenu' => "Chers parents,\n\nNous avons le plaisir de vous convier à la première rencontre parents-professeurs de l'année, le {$this->fr($reunion)} de 9 h 00 à 12 h 00, dans les salles de classe de vos enfants.\n\n"
                ."Au programme : présentation des équipes pédagogiques, règlement intérieur, calendrier des devoirs et des compositions, suivi des notes dans l'application CCPA Parents. L'assemblée générale de l'Association des Parents d'Élèves (APE) se tiendra ensuite à 11 h 00 dans la salle polyvalente.\n\n"
                .'Votre présence est vivement souhaitée.',
            'type' => 'evenement', 'pub' => $this->plus($reunion, -10), 'exp' => $reunion, 'cible' => 'tous', 'auteur' => 'censeur',
        ];

        // Rappel de l'échéance de la 2e tranche (lu dans la grille de scolarité réellement chargée)
        $echeance = DB::table('tranches')->join('scolarites', 'scolarites.id', '=', 'tranches.scolarite_id')
            ->whereIn('scolarites.classe_annee_id', array_values($this->ctx->classeAnnees[$annee['id']]))
            ->where('tranches.ordre', 2)->min('tranches.date_echeance');
        if ($echeance) {
            $echeance = substr((string) $echeance, 0, 10);
            $liste['tranche'] = [
                'titre' => 'Rappel : échéance de la 2e tranche de scolarité',
                'contenu' => "L'économat rappelle aux parents que la 2e tranche de la scolarité est exigible au plus tard le {$this->fr($echeance, true)}. Le montant de chaque tranche est indiqué dans l'application CCPA Parents, rubrique Scolarité.\n\n"
                    ."Le paiement peut se faire à la caisse de l'économat (du lundi au vendredi de 7 h 30 à 16 h 00, le samedi de 8 h 00 à 12 h 00) ou directement depuis l'application par MTN MoMo, Moov Money ou Celtiis Cash. Un reçu est délivré pour chaque versement.\n\n"
                    ."Les familles qui rencontrent des difficultés sont invitées à prendre rendez-vous avec l'économe avant l'échéance.",
                'type' => 'urgent', 'pub' => $this->plus($echeance, -10), 'exp' => $echeance, 'cible' => 'tous', 'auteur' => 'econome',
            ];
        }

        $compos = $this->prochain($this->plus($t1['fin'], -18), 1);
        $liste['compos'] = [
            'titre' => 'Compositions du premier trimestre',
            'contenu' => "Les compositions du premier trimestre se dérouleront du {$this->fr($compos)} au {$this->fr($this->plus($compos, 4))} pour toutes les classes. Le calendrier des épreuves est affiché dans chaque salle.\n\n"
                ."Les élèves doivent être présents 15 minutes avant le début de chaque épreuve, munis de leur carte scolaire et de leur matériel. Aucun téléphone portable n'est autorisé dans les salles. Toute absence non justifiée par un certificat médical entraîne la note zéro.\n\nLe Censorat",
            'type' => 'academique', 'pub' => $this->plus($compos, -10), 'exp' => $this->plus($compos, 4), 'cible' => 'tous', 'auteur' => 'censeur',
        ];

        $noel = $t1['fin'];
        $liste['noel'] = [
            'titre' => 'Fête de Noël et congés du premier trimestre',
            'contenu' => "La fête de Noël du Complexe aura lieu le {$this->fr($noel)} : messe d'action de grâce à 8 h 00, puis spectacle des élèves (crèche vivante, chants et saynètes) dans la cour principale. Les parents sont les bienvenus.\n\n"
                ."Les congés de Noël courent du {$this->fr($this->plus($noel, 1))} au {$this->fr($this->plus($t2['debut'], -1), true)} inclus. Reprise des cours le {$this->fr($t2['debut'])} à 7 h 00.\n\n"
                .'Joyeux Noël et bonne fête de fin d\'année à toutes les familles !',
            'type' => 'evenement', 'pub' => $this->plus($noel, -7), 'exp' => $noel, 'cible' => 'tous', 'auteur' => 'directeur',
        ];

        $bulletins = $this->plus($t2['debut'], 3);
        $liste['bulletins'] = [
            'titre' => 'Bulletins du premier trimestre',
            'contenu' => "Les bulletins du premier trimestre sont disponibles dans l'application CCPA Parents (rubrique Notes) et seront remis aux élèves en classe le {$this->fr($this->plus($bulletins, 1))}.\n\n"
                ."Les parents des élèves dont la moyenne est inférieure à 10 sont invités à prendre rendez-vous avec le professeur principal. Félicitations aux élèves inscrits au tableau d'honneur !",
            'type' => 'academique', 'pub' => $bulletins, 'exp' => $this->plus($bulletins, 21), 'cible' => 'tous', 'auteur' => 'censeur',
        ];

        // 10 janvier : fête des religions traditionnelles, chômée (si elle tombe en semaine)
        $vodun = ($y + 1).'-01-10';
        if (! in_array($this->dow($vodun), [0, 6], true)) {
            $liste['vodun'] = [
                'titre' => 'Jour férié du '.$this->fr($vodun),
                'contenu' => "Le {$this->fr($vodun)}, fête nationale des religions traditionnelles, est férié et chômé : il n'y aura pas cours.\n\nReprise des cours le {$this->fr($this->ouvrable($this->plus($vodun, 1)))} à 7 h 00.",
                'type' => 'general', 'pub' => $this->plus($vodun, -4), 'exp' => $vodun, 'cible' => 'tous', 'auteur' => 'censeur',
            ];
        }

        $examens = $this->prochain(($y + 1).'-01-17', 6);
        $liste['examens'] = [
            'titre' => 'Rencontre avec les parents des classes d\'examen',
            'contenu' => "Les parents des élèves de Troisième et de Terminale sont conviés le {$this->fr($examens)} à 9 h 00 en salle polyvalente : bilan du premier trimestre, préparation du BEPC et du baccalauréat (cours de renforcement du samedi, examens blancs), constitution des dossiers d'examen.\n\n"
                .'La présence d\'au moins un parent par élève est indispensable.',
            'type' => 'evenement', 'pub' => $this->plus($examens, -10), 'exp' => $examens, 'cible' => 'examens', 'auteur' => 'censeur',
        ];

        $debutTournoi = $this->prochain($this->plus($t2['debut'], 1), 3);
        $finale = $this->prochain($this->plus($debutTournoi, 17), 6);
        $liste['tournoi'] = [
            'titre' => 'Tournoi inter-classes « Coupe Père Aupiais »',
            'contenu' => "Le tournoi inter-classes de football (garçons) et de handball (filles) débute le {$this->fr($debutTournoi)}. Les matchs se jouent le mercredi après-midi sur le terrain du Complexe ; la finale aura lieu le {$this->fr($finale)} à 15 h 00, suivie de la remise des trophées.\n\n"
                .'Venez nombreux encourager les équipes !',
            'type' => 'evenement', 'pub' => $this->plus($debutTournoi, -5), 'exp' => $finale, 'cible' => 'tous', 'auteur' => 'prefet',
        ];

        $culture = $this->prochain(($y + 1).'-02-07', 6);
        $theme = $cle === 'precedente' ? 'Nos langues, notre richesse' : 'Bénin, terre de culture et de foi';
        $liste['culture'] = [
            'titre' => 'Journée culturelle du Complexe',
            'contenu' => "La journée culturelle se tiendra le {$this->fr($culture)} à partir de 9 h 00 sur le thème « {$theme} ». Au programme : défilé en tenues traditionnelles, danses (zinli, tchinkoumè, agbadja, tèkè), contes et poèmes en fon, en goun et en yoruba, expositions des clubs et stands de dégustation (amiwo, akassa, wassa-wassa, atchonmon).\n\n"
                .'Les élèves viennent en tenue traditionnelle. Entrée libre pour les parents.',
            'type' => 'evenement', 'pub' => $this->plus($culture, -14), 'exp' => $culture, 'cible' => 'tous', 'auteur' => 'censeur2',
        ];

        $bepc = $this->prochain(($y + 1).'-03-02', 1);
        $liste['bepc'] = [
            'titre' => 'Examen blanc du BEPC',
            'contenu' => "L'examen blanc du BEPC des élèves de Troisième se déroulera du {$this->fr($bepc)} au {$this->fr($this->plus($bepc, 3))}, dans les conditions de l'examen officiel (convocations, numéros de table, surveillance croisée). Les épreuves commencent à 7 h 30.\n\n"
                .'Les élèves doivent se munir de leur carte scolaire et de leur matériel de géométrie. Les résultats seront présentés aux parents à la fin du trimestre.',
            'type' => 'academique', 'pub' => $this->plus($bepc, -14), 'exp' => $this->plus($bepc, 3), 'cible' => 'Troisième', 'auteur' => 'censeur',
        ];

        $bac = $this->prochain(($y + 1).'-03-09', 1);
        $series = [];
        foreach ($this->ctx->classes as $classe) {
            if ($classe['niveau'] === 'Terminale') {
                $series[] = $classe['suffixe'];
            }
        }
        $liste['bac'] = [
            'titre' => 'Baccalauréat blanc des classes de Terminale',
            'contenu' => 'Le baccalauréat blanc des classes de Terminale (séries '.implode(', ', $series).") se déroulera du {$this->fr($bac)} au {$this->fr($this->plus($bac, 4))}. Les épreuves écrites commencent chaque jour à 7 h 30 ; les épreuves d'EPS sont organisées la semaine précédente.\n\n"
                .'Les copies seront corrigées dans les conditions de l\'examen et commentées en classe. Aucune absence ne sera tolérée sans justificatif.',
            'type' => 'academique', 'pub' => $this->plus($bac, -14), 'exp' => $this->plus($bac, 4), 'cible' => 'Terminale', 'auteur' => 'censeur2',
        ];

        $retraite = $this->plus($this->prochain(($y + 1).'-03-14', 6), -1);
        $theme = $cle === 'precedente' ? 'Lève-toi et marche' : 'Choisis donc la vie';
        $liste['retraite'] = [
            'titre' => 'Retraite spirituelle des classes d\'examen',
            'contenu' => "Dans le cadre du Carême, les élèves de Troisième et de Terminale vivront une retraite spirituelle le {$this->fr($retraite)} et le {$this->fr($this->plus($retraite, 1))} au centre spirituel diocésain, animée par l'aumônier du Complexe, sur le thème « {$theme} ».\n\n"
                ."Départ en car à 7 h 00 depuis l'établissement, retour le {$this->fr($this->plus($retraite, 1))} vers 16 h 00. Participation : 5 000 FCFA (transport et repas), à régler à l'économat ou par Mobile Money. Les élèves d'autres confessions en sont dispensés sur demande écrite des parents.",
            'type' => 'evenement', 'pub' => $this->plus($retraite, -14), 'exp' => $this->plus($retraite, 1), 'cible' => 'examens', 'auteur' => 'directeur',
        ];

        $debutConseils = $this->prochain($this->plus($t2['fin'], -6), 1);
        $planning = [];
        $classesConseil = array_keys($this->ctx->classeAnnees[$annee['id']]);
        foreach (array_chunk($classesConseil, (int) ceil(count($classesConseil) / 5)) as $j => $lot) {
            $planning[] = '- '.ucfirst($this->fr($this->plus($debutConseils, $j))).' : '.implode(', ', array_map(fn ($k) => $this->nomClasse($k), $lot));
        }
        $liste['conseils'] = [
            'titre' => 'Conseils de classe du deuxième trimestre',
            'contenu' => "Les conseils de classe du deuxième trimestre se tiendront à partir de 15 h 00 selon le calendrier suivant :\n".implode("\n", $planning)."\n\n"
                ."Les délégués de classe et les représentants des parents d'élèves y sont conviés. Les bulletins seront disponibles dans l'application CCPA Parents dès la fin des conseils.",
            'type' => 'academique', 'pub' => $this->plus($debutConseils, -10), 'exp' => $this->plus($debutConseils, 4), 'cible' => 'tous', 'auteur' => 'censeur',
        ];

        $paques = $this->paques($y + 1);
        $pendantConges = $paques > $t2['fin'] && $paques < $t3['debut'];
        $liste['conges'] = [
            'titre' => 'Congés de fin de deuxième trimestre',
            'contenu' => "Les cours du deuxième trimestre prendront fin le {$this->fr($t2['fin'])}. Les congés courent du {$this->fr($this->plus($t2['fin'], 1))} au {$this->fr($this->plus($t3['debut'], -1), true)} inclus ; reprise des cours le {$this->fr($t3['debut'])} à 7 h 00.\n\n"
                .($pendantConges ? "Toute la communauté éducative souhaite une sainte et joyeuse fête de Pâques ({$this->fr($paques)}) à toutes les familles.\n\n" : '')
                .'Bonnes vacances à tous.',
            'type' => 'general', 'pub' => $this->plus($t2['fin'], -7), 'exp' => $t3['debut'], 'cible' => 'tous', 'auteur' => 'censeur',
        ];
        if (! $pendantConges) {
            $lundi = $this->plus($paques, 1);
            $liste['paques'] = [
                'titre' => 'Semaine sainte et fête de Pâques',
                'contenu' => "Le chemin de croix des élèves aura lieu le Vendredi saint, {$this->fr($this->plus($paques, -2))}, à 9 h 00 dans la cour du Complexe ; les cours de l'après-midi sont suspendus. Le lundi de Pâques ({$this->fr($lundi)}) est férié : pas de cours, reprise le {$this->fr($this->plus($lundi, 1))}.\n\n"
                    .'Sainte et joyeuse fête de Pâques à toutes les familles !',
                'type' => 'evenement', 'pub' => $this->plus($paques, -9), 'exp' => $lundi, 'cible' => 'tous', 'auteur' => 'directeur',
            ];
        }

        $kermesse = $this->prochain(($y + 1).'-05-23', 6);
        $liste['kermesse'] = [
            'titre' => 'Kermesse de fin d\'année',
            'contenu' => "La grande kermesse annuelle du Complexe aura lieu le {$this->fr($kermesse)} de 10 h 00 à 18 h 00 dans la cour principale : jeux, tombola, stands de restauration tenus par les classes, concours de danse et match de gala enseignants contre élèves.\n\n"
                .'Les bénéfices financeront l\'équipement de la bibliothèque. Les tickets de tombola (500 FCFA) sont disponibles auprès des professeurs principaux.',
            'type' => 'evenement', 'pub' => $this->plus($kermesse, -14), 'exp' => $kermesse, 'cible' => 'tous', 'auteur' => 'prefet',
        ];

        $resultats = $this->plus($t3['fin'], 6);
        $remise = $this->prochain($this->plus($resultats, 1), 6);
        $ouvertureReinscriptions = $this->prochain(($y + 1).'-07-07', 1);
        $liste['resultats'] = [
            'titre' => "Résultats de fin d'année et réinscriptions {$suivante}",
            'contenu' => "Les décisions des conseils de classe de fin d'année (admission en classe supérieure, redoublement) sont disponibles dans l'application CCPA Parents. Les bulletins annuels seront remis aux parents le {$this->fr($remise)} de 8 h 00 à 12 h 00 ; aucun bulletin ne sera remis à un élève non accompagné.\n\n"
                ."La campagne de réinscription pour l'année {$suivante} est ouverte du {$this->fr($ouvertureReinscriptions)} au {$this->fr(($y + 1).'-09-12', true)}. Frais de dossier : "
                .$this->fcfa(Curriculum::FRAIS_DOSSIER['college']).' FCFA (collège) et '.$this->fcfa(Curriculum::FRAIS_DOSSIER['lycee'])." FCFA (lycée), payables à l'économat ou par Mobile Money (MTN MoMo, Moov Money, Celtiis Cash). Passé ce délai, les places disponibles seront attribuées aux nouveaux élèves.\n\n"
                .'Bonnes vacances à tous !',
            'type' => 'academique', 'pub' => $resultats, 'exp' => $cle === 'precedente' ? ($y + 1).'-09-12' : null, 'cible' => 'tous', 'auteur' => 'directeur',
        ];

        // Chaque année n'a pas exactement le même calendrier
        $retenus = $cle === 'precedente'
            ? array_diff(array_keys($liste), ['messe', 'visite', 'tranche', 'examens', 'tournoi', 'bulletins'])
            : array_diff(array_keys($liste), ['pluies', 'kermesse']);

        return array_values(array_intersect_key($liste, array_flip($retenus)));
    }

    // ─────────────────────────────────────────────────────────────────
    // MESSAGERIE
    // ─────────────────────────────────────────────────────────────────

    private function messagerie(): void
    {
        $rng = $this->ctx->rng;
        $this->chargerPersonnel();

        $comptes = [];
        foreach ($this->ctx->familles as $fam) {
            foreach ($fam['parents'] as $p) {
                if (! empty($p['user_id'])) {
                    $comptes[] = $p['user_id'];
                }
            }
        }
        foreach (array_chunk($comptes, 900) as $lot) {
            $this->comptesCreesLe += DB::table('users')->whereIn('id', $lot)->pluck('created_at', 'id')->map(fn ($d) => (string) $d)->all();
        }

        // Parents qui utilisent l'application mobile (le second parent une fois sur deux)
        $candidats = [];
        $demo = null;
        foreach ($this->ctx->familles as $f => $fam) {
            if (! $fam['mobile'] || empty($this->enfants[$f]) || empty($fam['parents'][0]['id'])) {
                continue;
            }
            if ($fam['demo'] ?? false) {
                $demo = $f;

                continue;
            }
            foreach ($fam['parents'] as $i => $p) {
                if ($i === 0 || $rng->chance(0.5)) {
                    $candidats[] = [$f, $i];
                }
            }
        }

        $cible = max(10, (int) round(DB::table('parents')->count() * self::CONVERSATIONS_PAR_PARENT));
        $conversations = []; // "parent-admin" => ['parent_id', 'admin_id', 'messages' => [[ts, who, texte]]]

        // Famille de démonstration (parent@ccpa.test) : plusieurs échanges, dont un récent non lu
        if ($demo !== null) {
            foreach (['absence', 'bulletin', 'note'] as $sujet) {
                $this->ajouterEpisode($conversations, $demo, 0, $sujet);
            }
            $this->ajouterEpisode($conversations, $demo, 0, null, true);
        }

        $candidats = $rng->shuffle($candidats);
        // Quelques échanges en cours (derniers jours) : messages encore non lus
        foreach (array_slice($candidats, 0, max(3, (int) round($cible * 0.07))) as [$f, $i]) {
            $this->ajouterEpisode($conversations, $f, $i, null, true);
        }
        foreach ($candidats as [$f, $i]) {
            if (count($conversations) >= $cible) {
                break;
            }
            $adminId = $this->ajouterEpisode($conversations, $f, $i);
            // Autres échanges : le plus souvent avec le même interlocuteur, parfois un autre service
            for ($k = 0; $k < 2 && $adminId !== null && $rng->chance($k === 0 ? 0.55 : 0.35); $k++) {
                $this->ajouterEpisode($conversations, $f, $i, null, false, $rng->chance(0.7) ? $adminId : null);
            }
        }

        // Écriture : conversations par date du premier message, messages dans l'ordre chronologique
        foreach ($conversations as &$conv) {
            usort($conv['messages'], fn ($a, $b) => $a[0] <=> $b[0]);
        }
        unset($conv);
        uasort($conversations, fn ($a, $b) => $a['messages'][0][0] <=> $b['messages'][0][0]);

        $limite = strtotime($this->limite.' UTC');
        $messages = [];
        $nonLus = 0;
        foreach ($conversations as $conv) {
            $creeLe = gmdate('Y-m-d H:i:s', $conv['messages'][0][0]);
            $id = DB::table('conversations')->insertGetId([
                'parent_id' => $conv['parent_id'], 'admin_id' => $conv['admin_id'], 'created_at' => $creeLe, 'updated_at' => $creeLe,
            ]);
            $n = count($conv['messages']);
            // Seule la fin de l'échange (derniers messages sans réponse, de moins de quatre jours) peut
            // rester non lue, et alors en bloc : un message lu implique que les précédents le sont.
            $finNonLue = $conv['messages'][$n - 1][0] >= $limite - 4 * 86400 && $rng->chance(0.8);
            foreach ($conv['messages'] as $k => [$ts, $qui, $texte]) {
                // Lu si l'autre partie a répondu ensuite, ou s'il date de plus de quelques jours
                $repondu = false;
                for ($j = $k + 1; $j < $n; $j++) {
                    if ($conv['messages'][$j][1] !== $qui) {
                        $repondu = true;
                        break;
                    }
                }
                $lu = $repondu || ! $finNonLue || $ts < $limite - 4 * 86400;
                $nonLus += $lu ? 0 : 1;
                $messages[] = [
                    'conversation_id' => $id,
                    'sender_id' => $qui === 'p' ? $conv['parent_user_id'] : $conv['admin_id'],
                    'sender_type' => $qui === 'p' ? 'parent' : 'admin',
                    'body' => $texte, 'is_read' => $lu, 'created_at' => gmdate('Y-m-d H:i:s', $ts),
                ];
                if ($qui === 'p') {
                    $premier = $this->premierMessage[$conv['parent_id']] ?? null;
                    $this->premierMessage[$conv['parent_id']] = $premier === null ? gmdate('Y-m-d H:i:s', $ts) : min($premier, gmdate('Y-m-d H:i:s', $ts));
                }
            }
        }
        usort($messages, fn ($a, $b) => [$a['created_at'], $a['conversation_id']] <=> [$b['created_at'], $b['conversation_id']]);
        $this->ctx->inserer('messages', $messages);

        $this->stats['conversations'] = count($conversations);
        $this->stats['messages'] = count($messages);
        $this->stats['non_lus'] = $nonLus;
    }

    /** Personnel ayant accès à la messagerie (routes « messagerie » : admin, directeur, censeur, économe, préfet, surveillant). */
    private function chargerPersonnel(): void
    {
        $noms = DB::table('users')->whereIn('id', array_values($this->ctx->comptes))->pluck('name', 'id');
        foreach ($this->ctx->comptes as $cle => $userId) {
            $role = DB::table('users')->where('id', $userId)->value('role');
            if (! in_array($role, ['admin', 'directeur', 'censeur', 'econome', 'prefet', 'surveillant'], true)) {
                continue;
            }
            $f = in_array($cle, self::COMPTES_FEMININS, true);
            $nom = (string) ($noms[$userId] ?? '');
            $civ = match (true) {
                $role === 'directeur' => 'mon Père',
                $role === 'censeur' => $f ? 'Madame la Censeure' : 'Monsieur le Censeur',
                $role === 'econome' && $cle === 'comptable' => 'Monsieur le Comptable',
                $role === 'econome' => $f ? 'Madame l\'Économe' : 'Monsieur l\'Économe',
                $role === 'prefet' && str_starts_with($nom, 'Frère ') => 'Frère '.explode(' ', $nom)[1],
                $role === 'prefet' => 'Monsieur le Préfet',
                $role === 'surveillant' => $f ? 'Madame la Surveillante' : 'Monsieur le Surveillant',
                default => $f ? 'Madame' : 'Monsieur',
            };
            $this->staff[$userId] = ['cle' => $cle, 'role' => $role, 'civ' => $civ, 'nom' => $nom];
        }
    }

    private function staffPour(string $role, bool $lycee): int
    {
        $rng = $this->ctx->rng;
        $c = $this->ctx->comptes;
        $parRole = fn (string $r) => array_keys(array_filter($this->staff, fn ($s) => $s['role'] === $r));

        return match ($role) {
            'censeur' => isset($c['censeur2'], $this->staff[$c['censeur2']]) && $lycee ? $c['censeur2'] : $c['censeur'],
            'econome' => isset($c['econome']) && ($rng->chance(0.7) || ! isset($c['comptable'])) ? $c['econome'] : ($c['comptable'] ?? $rng->pick($parRole('econome'))),
            'surveillant' => $rng->pick($parRole('surveillant') ?: $parRole('prefet')),
            'prefet' => $c['prefet'] ?? $rng->pick($parRole('prefet')),
            'directeur' => $c['directeur'],
            default => $c['admin'],
        };
    }

    /**
     * Ajoute un échange (un sujet, une date) entre un parent et un membre du personnel, dans la
     * conversation existante du couple s'il y en a une.
     */
    private function ajouterEpisode(array &$conversations, int $f, int $pi, ?string $sujetImpose = null, bool $recent = false, ?int $adminImpose = null): ?int
    {
        for ($essai = 0; $essai < 8; $essai++) {
            $episode = $this->episode($f, $pi, $sujetImpose, $recent, $adminImpose);
            if ($episode === null) {
                continue;
            }
            $parent = $this->ctx->familles[$f]['parents'][$pi];
            $cle = $parent['id'].'-'.$episode['admin_id'];
            $existante = $conversations[$cle] ?? ['parent_id' => $parent['id'], 'parent_user_id' => $parent['user_id'], 'admin_id' => $episode['admin_id'], 'messages' => [], 'sujets' => []];
            // Jamais deux fois le même sujet, ni deux échanges rapprochés, dans une conversation
            if (in_array($episode['sujet'], $existante['sujets'], true)) {
                continue;
            }
            $debut = $episode['messages'][0][0];
            $fin = end($episode['messages'])[0];
            foreach ($existante['messages'] as [$ts]) {
                if ($ts >= $debut - 10 * 86400 && $ts <= $fin + 10 * 86400) {
                    continue 2;
                }
            }
            array_push($existante['messages'], ...$episode['messages']);
            $existante['sujets'][] = $episode['sujet'];
            $conversations[$cle] = $existante;

            return $episode['admin_id'];
        }

        return null;
    }

    /** Interlocuteur(s) possibles de chaque sujet. */
    private const ROLES_SUJETS = [
        'absence' => ['surveillant', 'prefet'], 'deuil' => ['surveillant', 'prefet'], 'retard' => ['surveillant'], 'sortie' => ['surveillant'],
        'discipline' => ['prefet'], 'paiement' => ['econome'], 'recu' => ['econome'], 'cantine' => ['econome'], 'reinscription' => ['econome'],
        'note' => ['censeur'], 'rdv_prof' => ['censeur'], 'bulletin' => ['censeur'], 'orientation' => ['censeur'],
        'compte' => ['admin'], 'audience' => ['directeur'],
    ];

    /**
     * Construit un échange : année, enfant, sujet, interlocuteur, date et dialogue horodaté.
     *
     * @return array{admin_id: int, messages: list<array{0: int, 1: string, 2: string}>}|null
     */
    private function episode(int $f, int $pi, ?string $sujetImpose, bool $recent, ?int $adminImpose = null): ?array
    {
        $rng = $this->ctx->rng;
        $fam = $this->ctx->familles[$f];
        $parent = $fam['parents'][$pi];
        $active = $this->ctx->anneeId('active');
        $precedente = $this->ctx->anneeId('precedente');

        // Année et enfant (inscrit cette année-là et resté jusqu'au bout)
        $cleAnnee = $recent ? 'active' : ($rng->chance(0.62) ? 'active' : 'precedente');
        $anneeId = $cleAnnee === 'active' ? $active : $precedente;
        $inscrits = array_values(array_filter($this->enfants[$f] ?? [], fn ($id) => isset($this->ctx->eleves[$id]['classes'][$anneeId]) && ! isset($this->ctx->eleves[$id]['depart'][$anneeId])));
        if (! $inscrits) {
            return null;
        }
        $eleveId = $rng->pick($inscrits);
        $e = $this->ctx->eleves[$eleveId];
        $cleClasse = $e['classes'][$anneeId];
        $classe = $this->ctx->classes[$cleClasse];
        $caId = $this->ctx->classeAnnees[$anneeId][$cleClasse];
        $annee = $this->ctx->annees[$cleAnnee];
        $periodes = $this->ctx->periodes[$anneeId];
        $y = (int) substr($annee['debut'], 0, 4);
        $lycee = Curriculum::estLycee($classe['niveau']);

        // Sujet
        $enCours = $this->plafond >= $periodes[0]['debut'] && $this->plafond <= $periodes[2]['fin'];
        if ($sujetImpose !== null) {
            $sujet = $sujetImpose;
        } else {
            $poids = [
                'absence' => 20, 'deuil' => 4, 'retard' => 7, 'sortie' => 7,
                'discipline' => ($e['comp'] ?? 0) < -0.3 ? 12 : 2,
                'paiement' => match ($fam['payeur']) { 'retard', 'difficulte' => 18, 'normal' => 4, default => 0 },
                'recu' => $fam['mode'] === 'mobile_money' ? 12 : 4, 'cantine' => 4,
                'note' => 10, 'rdv_prof' => 7, 'bulletin' => 7,
                'orientation' => $classe['niveau'] === 'Troisième' ? 14 : 0,
                'reinscription' => $classe['niveau'] === 'Terminale' ? 0 : 4,
                'compte' => 4, 'audience' => 3,
            ];
            if ($recent && ! $enCours) {
                // Vacances : seuls les sujets administratifs ont du sens
                $poids = array_intersect_key($poids, array_flip(['reinscription', 'compte', 'audience', 'recu', 'paiement']));
            }
            if ($adminImpose !== null) {
                // Nouvel échange avec le même interlocuteur : sujets de son ressort uniquement
                $roleImpose = $this->staff[$adminImpose]['role'] ?? null;
                $poids = array_filter($poids, fn ($p, $s) => in_array($roleImpose, self::ROLES_SUJETS[$s], true), ARRAY_FILTER_USE_BOTH);
            }
            $poids = array_filter($poids);
            if (! $poids) {
                return null;
            }
            $sujet = (string) $rng->weighted($poids);
        }

        $prenom = explode(' ', $e['prenom'])[0];
        $fille = $e['sexe'] === 'F';
        $mere = in_array($parent['relation'], ['mère', 'tutrice'], true);
        $v = [
            '{enfant}' => $prenom, '{classe}' => $this->nomClasse($cleClasse), '{civ}' => $mere ? 'Madame' : 'Monsieur',
            '{Il}' => $fille ? 'Elle' : 'Il', '{il}' => $fille ? 'elle' : 'il', '{e}' => $fille ? 'e' : '', '{le}' => $fille ? 'la' : 'le',
            '{pe}' => $mere ? 'e' : '',
        ];

        // Date du premier message (jour) selon le sujet
        $jourCours = function () use ($rng, $periodes, $recent): ?string {
            if ($recent) {
                $debut = $this->plus($this->plafond, -6);

                return $this->ouvrable($rng->date($debut, $this->plafond), true);
            }
            $p = $rng->pick($periodes);

            return $this->ouvrable($rng->date($this->plus($p['debut'], 2), $this->plus($p['fin'], -1)), true);
        };
        $jour = null;
        $role = null;

        switch ($sujet) {
            case 'note':
            case 'rdv_prof':
                $q = DB::table('notes')->where('eleve_id', $eleveId)->where('classe_annee_id', $caId)
                    ->where('type_note_id', $this->ctx->typesNotes['DEV'] ?? 0)
                    ->where('created_at', '<=', $this->limite);
                $note = $sujet === 'note'
                    ? $q->where('valeur', '<', 11)->orderBy('id')->get(['matiere_id', 'valeur', 'enseignant_id', 'created_at'])->all()
                    : $q->whereIn('matiere_id', array_intersect_key($this->ctx->matieres, array_flip(['MATH', 'PCT', 'FR', 'ANG', 'SVT', 'PHILO'])))->orderBy('id')->get(['matiere_id', 'valeur', 'enseignant_id', 'created_at'])->all();
                if (! $note) {
                    return null;
                }
                $note = $rng->pick($note);
                $code = $this->codesMatieres[(int) $note->matiere_id] ?? null;
                $prof = $this->ctx->enseignants[(int) $note->enseignant_id] ?? null;
                if ($code === null || $prof === null) {
                    return null;
                }
                $jour = $this->ouvrable($this->plus(substr((string) $note->created_at, 0, 10), $rng->int(1, 4)), true);
                $v += [
                    '{matiere}' => $this->nomMatiere($code), '{note}' => $this->decimal((float) $note->valeur),
                    '{dateNote}' => $this->fr(substr((string) $note->created_at, 0, 10)),
                    '{prof}' => explode(' ', $prof['prenom'])[0].' '.$prof['nom'],
                ];
                $role = 'censeur';
                break;

            case 'bulletin':
                $k = $rng->int(0, 1);
                $moy = $this->ctx->moyennesPeriodes[$anneeId][$eleveId][$periodes[$k]['id']] ?? null;
                if ($moy === null) {
                    return null;
                }
                $jour = $this->ouvrable($this->plus($periodes[$k]['fin'], $k === 0 ? $rng->int(1, 4) : $rng->int(3, 6)), false);
                $v += [
                    '{trimestre}' => self::TRIMESTRES[$k], '{moy}' => $this->decimal($moy),
                    '{appreciation}' => match (true) {
                        $moy >= 14 => 'Félicitations du conseil de classe !',
                        $moy >= 12 => 'Inscription au tableau d\'honneur, bravo !',
                        $moy >= 10 => 'Le conseil de classe l\'encourage à poursuivre ses efforts.',
                        default => 'Le conseil de classe l\'invite à redoubler d\'efforts ; le professeur principal souhaite vous rencontrer.',
                    },
                    '{reaction}' => $moy >= 12 ? 'Nous sommes très fiers d\''.($fille ? 'elle' : 'lui').'.' : 'Nous allons l\'accompagner davantage au prochain trimestre.',
                ];
                $role = 'censeur';
                break;

            case 'orientation':
                if ($classe['niveau'] !== 'Troisième') {
                    return null;
                }
                $moyMath = DB::table('notes')->where('eleve_id', $eleveId)->where('classe_annee_id', $caId)
                    ->where('matiere_id', $this->ctx->matieres['MATH'] ?? 0)->avg('valeur');
                if ($moyMath === null) {
                    return null;
                }
                $moyMath = (float) $moyMath;
                $jour = $this->ouvrable($rng->date(($y + 1).'-02-02', ($y + 1).'-05-29'), true);
                $v += [
                    '{moyMath}' => $this->decimal(round($moyMath * 2) / 2),
                    '{avis}' => match (true) {
                        $moyMath >= 12 => 'Avec ce niveau, la série C est tout à fait envisageable, à condition de maintenir les efforts jusqu\'au BEPC.',
                        $moyMath >= 10 => 'C\'est un peu juste. Un travail régulier d\'ici la fin de l\'année et les cours de renforcement du samedi peuvent faire la différence ; nous en reparlerons au conseil de classe.',
                        default => 'Pour le moment, la série A semble mieux correspondre à son profil ; ses résultats en français et en langues sont un atout. Nous en reparlerons au conseil de classe.',
                    },
                ];
                $role = 'censeur';
                break;

            case 'paiement':
                $tranche = DB::table('tranches')->join('scolarites', 'scolarites.id', '=', 'tranches.scolarite_id')
                    ->where('scolarites.classe_annee_id', $caId)->whereIn('tranches.ordre', [2, 3])
                    ->get(['tranches.libelle', 'tranches.montant', 'tranches.date_echeance'])->all();
                if (! $tranche) {
                    return null;
                }
                $tranche = $rng->pick($tranche);
                $echeance = substr((string) $tranche->date_echeance, 0, 10);
                $jour = $recent ? $jourCours() : $this->ouvrable($this->plus($echeance, $rng->int(-6, 2)), true);
                if ($echeance < $this->plus($jour, -5)) {
                    return null; // on ne demande pas un délai pour une échéance passée depuis longtemps
                }
                $delai = $this->ouvrable($this->plus(max($jour, $echeance), $rng->int(10, 21)), false);
                $v += [
                    '{tranche}' => $tranche->libelle, '{echeance}' => $this->fr($echeance), '{delai}' => $this->fr($delai),
                    '{acompte}' => $this->fcfa(max(5000, round((float) $tranche->montant / 2 / 5000) * 5000)),
                ];
                $role = 'econome';
                break;

            case 'recu':
                $paiement = DB::table('paiements')->where('eleve_id', $eleveId)->where('mode_paiement', 'mobile_money')
                    ->whereNotNull('reference')->where('reference', 'not like', 'FEDA-%')
                    ->whereBetween('date_paiement', [$annee['debut'], $this->plafond])
                    ->get(['montant', 'reference', 'date_paiement'])->all();
                if (! $paiement) {
                    return null;
                }
                $paiement = $rng->pick($paiement);
                $ref = (string) $paiement->reference;
                $jour = $this->plus(substr((string) $paiement->date_paiement, 0, 10), $rng->int(0, 1));
                $v += [
                    '{montant}' => $this->fcfa((float) $paiement->montant), '{ref}' => $ref,
                    '{operateur}' => match (true) { str_starts_with($ref, 'MP') => 'Moov Money', str_starts_with($ref, 'CLT') => 'Celtiis Cash', default => 'MTN MoMo' },
                    '{datePaiement}' => $this->fr(substr((string) $paiement->date_paiement, 0, 10)),
                ];
                $role = 'econome';
                break;

            case 'cantine':
                $jour = $jourCours();
                if ($jour === null) {
                    return null;
                }
                $mois = (int) substr($this->plus($jour, 30), 5, 2);
                $tarifs = DB::table('cantine_offres')->where('annee_scolaire_id', $anneeId)->where('mois', $mois)->pluck('montant', 'type');
                if (! isset($tarifs['ticket'])) {
                    return null;
                }
                $v += [
                    '{tarif}' => $this->fcfa((float) $tarifs['ticket']),
                    '{petitdej}' => isset($tarifs['petit_dejeuner']) ? ', et le petit-déjeuner à '.$this->fcfa((float) $tarifs['petit_dejeuner']).' FCFA par mois' : '',
                ];
                $role = 'econome';
                break;

            case 'reinscription':
                if ($classe['niveau'] === 'Terminale') {
                    return null;
                }
                $jour = $recent ? $jourCours() : $this->ouvrable($rng->date(($y + 1).'-07-07', ($y + 1).'-09-05'), false);
                $niveauSuivant = $classe['ordre'] + 1;
                $v += [
                    '{suivante}' => ($y + 1).'-'.($y + 2),
                    '{frais}' => $this->fcfa(Curriculum::FRAIS_DOSSIER[$niveauSuivant >= 11 ? 'lycee' : 'college']),
                ];
                $role = 'econome';
                break;

            case 'compte':
                $freres = array_values(array_filter($inscrits, fn ($id) => $id !== $eleveId));
                $v['{autre}'] = $freres ? explode(' ', $this->ctx->eleves[$freres[0]]['prenom'])[0] : '';
                $sujet = $freres ? 'compte_enfant' : 'compte_numero';
                $jour = $jourCours();
                $role = 'admin';
                break;

            case 'discipline':
            case 'audience':
                $jour = $jourCours();
                if ($jour === null) {
                    return null;
                }
                // Audience demandée par le parent : rendez-vous fixé après la réponse du directeur
                if ($sujet === 'audience') {
                    $jour = $this->ouvrable($jour, false);
                }
                $rdv = $this->ouvrable($this->plus($jour, $sujet === 'audience' ? $rng->int(2, 5) : $rng->int(1, 4)), false);
                [$h, $min] = $rng->pick([[8, 0], [9, 30], [10, 30], [11, 0], [15, 30], [16, 30]]);
                $v += ['{rdv}' => $this->fr($rdv), '{heure}' => sprintf('%d h %02d', $h, $min)];
                $v['@rdv'] = strtotime(sprintf('%s %02d:%02d:00 UTC', $rdv, $h, $min));
                if ($sujet === 'discipline') {
                    $code = $rng->pick(array_keys(array_diff_key($this->ctx->programmes[$caId] ?? ['FR' => 1], ['COND' => 1, 'EPS' => 1])));
                    $v['{matiere}'] = $this->nomMatiere($code);
                }
                $role = $sujet === 'discipline' ? 'prefet' : 'directeur';
                break;

            default: // absence, deuil, retard, sortie : un jour de classe (ni samedi ni férié)
                $jour = $this->ouvrable($jourCours(), false);
                $role = match ($sujet) {
                    'absence', 'deuil' => $rng->chance(0.6) ? 'surveillant' : 'prefet',
                    default => 'surveillant',
                };
                $retour = $this->ouvrable($this->plus($jour, $rng->int(3, 5)), false);
                [$hSortie, $mSortie] = $rng->pick([[10, 15], [11, 15], [15, 0]]);
                $v += [
                    '{maladie}' => $rng->pick(['une crise de paludisme', 'un palu', 'une angine', 'une forte grippe', 'une gastro-entérite']),
                    '{demain}' => $this->fr($this->ouvrable($this->plus($jour, 1), false)), '{retour}' => $this->fr($retour),
                    '{ville}' => $rng->pick(['Abomey', 'Ouidah', 'Porto-Novo', 'Lokossa', 'Bohicon', 'Allada', 'Dassa-Zoumè', 'Savalou', 'Comè']),
                    '{heureSortie}' => sprintf('%d h %02d', $hSortie, $mSortie),
                    '{motifSortie}' => $rng->pick(['un rendez-vous chez le dentiste', 'un rendez-vous au CNHU', 'le renouvellement de son passeport', 'un rendez-vous chez l\'ophtalmologue', 'une cérémonie familiale']),
                ];
                if ($sujet === 'absence' || $sujet === 'sortie') {
                    // Absence annoncée / sortie demandée le matin même, avant le début des cours
                    $v['@matin'] = true;
                    $v['@sortie'] = strtotime(sprintf('%s %02d:%02d:00 UTC', $jour, $hSortie, $mSortie));
                }
        }

        if ($recent && $jour !== null && $jour < $this->plus($this->plafond, -10)) {
            return null; // un échange « en cours » date des tout derniers jours
        }

        if ($jour === null || $jour < $annee['debut'] || $jour > $this->plafond) {
            return null;
        }
        $adminId = $adminImpose ?? $this->staffPour($role, $lycee);
        if (! isset($this->staff[$adminId])) {
            return null;
        }
        $v['{staffCiv}'] = $this->staff[$adminId]['civ'];

        // Formules de fin d'échange, une fois sur deux
        $dialogue = $this->dialogue($sujet);
        if ($rng->chance(0.5)) {
            $dialogue[] = end($dialogue)[0] === 's'
                ? ['p', ['Merci beaucoup {staffCiv}.', 'Merci, bonne journée.', 'D\'accord, merci.', 'Merci pour votre réactivité.', 'Que Dieu vous bénisse.']]
                : ['s', ['Je vous en prie {civ}. Bonne journée.', 'Avec plaisir {civ}.', 'Je vous en prie, bonne fin de journée.']];
        }

        $messages = $this->horodater($jour, $dialogue, $v);
        // Le parent n'écrit pas avant d'avoir son compte ; rien après « maintenant »
        $compte = $this->comptesCreesLe[$parent['user_id']] ?? '0000-00-00';
        $limite = strtotime($this->limite.' UTC');
        if (gmdate('Y-m-d H:i:s', $messages[0][0]) <= $compte) {
            return null;
        }
        $messages = array_values(array_filter($messages, fn ($m) => $m[0] <= $limite));
        if ($recent && count($messages) > 1) {
            // Échange en cours : la dernière question n'a pas encore reçu de réponse
            $messages = array_slice($messages, 0, $this->ctx->rng->int(1, count($messages) - 1));
        }
        if (count($messages) < ($recent ? 1 : 2)) {
            return null;
        }

        return ['admin_id' => $adminId, 'sujet' => $sujet, 'messages' => $messages];
    }

    /**
     * Dialogue d'un sujet : liste de [p|s, texte (ou variantes), délai éventuel].
     * Délai : entier = jours plus tard ; 'rdv' = après le rendez-vous fixé.
     */
    private function dialogue(string $sujet): array
    {
        return match ($sujet) {
            'absence' => [
                ['p', ["Bonjour {staffCiv}, je vous informe que {enfant} ({classe}) ne pourra pas venir en cours aujourd'hui. {Il} a beaucoup de fièvre depuis hier soir, je l'emmène au centre de santé ce matin.",
                    "Bonjour {staffCiv}. {enfant}, en {classe}, est malade depuis cette nuit ({il} vomit et a de la fièvre). {Il} restera à la maison aujourd'hui, nous allons à la clinique."]],
                ['s', "Bonjour {civ}, merci de nous avoir prévenus. L'absence est notée. Prompt rétablissement à {enfant}."],
                ['s', "À son retour, {il} devra présenter un justificatif (ordonnance ou certificat médical) au bureau de la surveillance."],
                ['p', ["D'accord, merci beaucoup.", "Merci, c'est noté.", "Merci {staffCiv}, bonne journée."]],
                ['p', "Bonjour, {enfant} reprend les cours {lendemain}. Le médecin a diagnostiqué {maladie}. {Il} apportera le certificat médical.", 2],
                ['s', "Bien reçu, l'absence sera justifiée. Qu'{il} passe au bureau de la surveillance avant d'entrer en classe."],
                ['p', "Merci, bonne journée."],
            ],
            'deuil' => [
                ['p', "Bonjour {staffCiv}, {enfant} ({classe}) sera absent{e} du {demain} au {retour} : nous nous rendons à {ville} pour les obsèques de son grand-père."],
                ['s', "Bonjour {civ}, c'est noté. Toutes nos condoléances à la famille."],
                ['s', "À son retour, merci de faire remplir le billet d'absence au bureau de la surveillance. {Il} pourra récupérer les cours auprès du délégué de classe."],
                ['p', ["Merci {staffCiv}, nous n'y manquerons pas.", "Merci beaucoup pour votre compréhension."]],
            ],
            'retard' => [
                ['s', "Bonjour {civ}, je me permets de vous signaler que {enfant} ({classe}) est arrivé{e} en retard à trois reprises cette semaine, après la fermeture du portail à 7 h 05."],
                ['p', ["Bonsoir {staffCiv}, merci de m'avoir informé{pe}. C'est le zémidjan qui {le} dépose qui arrive en retard, je vais changer d'organisation.",
                    "Bonsoir {staffCiv}, désolé{pe}. Avec les embouteillages sur la route de l'aéroport, nous partons pourtant tôt. Je vais {le} faire partir plus tôt."]],
                ['s', "Merci de votre compréhension. Au prochain retard, {enfant} sera retenu{e} en permanence le samedi matin, conformément au règlement intérieur."],
                ['p', "Compris, cela ne se reproduira plus."],
                ['s', "Très bien, merci {civ}."],
            ],
            'sortie' => [
                ['p', "Bonjour {staffCiv}, je passerai chercher {enfant} ({classe}) aujourd'hui à {heureSortie} pour {motifSortie}."],
                ['s', "Bonjour {civ}, c'est noté. Présentez-vous au bureau de la surveillance pour signer le registre des sorties ; {enfant} y sera appelé{e}."],
                ['p', "Merci, je serai là à l'heure.", 'm'],
                ['p', "Je suis au portail.", 'h'],
                ['s', "{enfant} arrive, {il} vous attend à la loge.", 'm'],
            ],
            'discipline' => [
                ['s', "Bonjour {civ}, je souhaiterais vous rencontrer au sujet du comportement de {enfant} ({classe}) : bavardages répétés et téléphone portable confisqué pendant le cours {de_matiere}. Seriez-vous disponible le {rdv} à {heure} ?"],
                ['p', "Bonjour {staffCiv}, je suis vraiment désolé{pe} d'apprendre cela. Je serai présent{pe} le {rdv} à {heure}."],
                ['s', "Merci. Je vous recevrai à mon bureau, au bâtiment administratif. Le téléphone vous sera remis en main propre, conformément au règlement intérieur."],
                ['p', "D'accord. Je vais lui parler sérieusement ce soir."],
                ['p', "Merci pour l'entretien d'aujourd'hui. Nous suivrons {enfant} de près à la maison.", 'rdv'],
                ['s', "Merci à vous {civ}. Nous comptons sur votre soutien."],
            ],
            'paiement' => [
                ['p', "Bonjour {staffCiv}, je n'ai pas encore pu régler la {tranche} de la scolarité de {enfant} ({classe}), qui arrive à échéance le {echeance}. J'attends un règlement de mon côté. Est-il possible d'avoir un délai jusqu'au {delai} ?"],
                ['s', "Bonjour {civ}, merci de nous avoir prévenus. Un délai vous est accordé jusqu'au {delai}."],
                ['s', "Vous pouvez payer à la caisse de l'économat ou directement dans l'application CCPA Parents, par MTN MoMo, Moov Money ou Celtiis Cash. Un versement partiel est aussi accepté."],
                ['p', ["Merci beaucoup {staffCiv}, que Dieu vous bénisse.", "Merci pour votre compréhension."]],
                ['p', "Bonjour, je viens de faire un premier versement de {acompte} FCFA par Mobile Money. Le reste suivra avant le {delai}.", 4],
                ['s', "Bien reçu {civ}, merci. Le reçu est disponible dans l'application."],
            ],
            'recu' => [
                ['p', "Bonjour {staffCiv}, j'ai payé {montant} FCFA par {operateur} le {datePaiement} pour {enfant} ({classe}), mais je ne vois pas encore le paiement dans l'application."],
                ['s', "Bonjour {civ}, pouvez-vous m'indiquer la référence de la transaction (elle figure sur le SMS de confirmation) ?"],
                ['p', "Voici la référence : {ref}"],
                ['s', "Merci. Le paiement est bien enregistré au nom de {enfant} ; le reçu est maintenant disponible dans l'application, rubrique Paiements."],
                ['p', ["Je le vois, merci beaucoup !", "C'est bon, merci {staffCiv}."]],
            ],
            'cantine' => [
                ['p', "Bonjour {staffCiv}, je voudrais inscrire {enfant} ({classe}) à la cantine à partir du mois prochain. Quel est le tarif ?"],
                ['s', "Bonjour {civ}, le ticket de cantine est à {tarif} FCFA par mois{petitdej}. Le paiement se fait au mois, à l'économat ou dans l'application CCPA Parents."],
                ['p', "D'accord. Les repas sont servis à quelle heure ?"],
                ['s', "Le déjeuner est servi de 12 h 15 à 13 h 15 au réfectoire, sous la surveillance d'un éducateur."],
                ['p', "Merci, je ferai le paiement par MoMo en début de mois."],
            ],
            'note' => [
                ['p', "Bonjour {staffCiv}, {enfant} ({classe}) a obtenu {note}/20 au dernier devoir {de_matiere} (note publiée le {dateNote}). {Il} pense qu'il y a une erreur, car {il} avait bien révisé. Est-il possible de vérifier ?"],
                ['s', "Bonjour {civ}, je transmets votre demande à {prof}, son professeur {de_matiere}, et je reviens vers vous."],
                ['s', "Après vérification avec {prof}, la note de {note}/20 correspond bien à la copie. Le professeur propose de reprendre la correction avec {enfant} à la fin du prochain cours.", 1],
                ['p', ["Merci {staffCiv} pour votre diligence. Je vais l'encourager à travailler davantage.", "D'accord, merci pour la vérification."]],
            ],
            'rdv_prof' => [
                ['p', "Bonjour {staffCiv}, les résultats de {enfant} ({classe}) en {matiere} ont beaucoup baissé ce trimestre. Je souhaiterais rencontrer son professeur."],
                ['s', "Bonjour {civ}, {prof} reçoit les parents sur rendez-vous. Je lui transmets votre demande."],
                ['s', "{prof} peut vous recevoir après-demain à 16 h 00 en salle des professeurs. Cela vous convient-il ?", 1],
                ['p', "Oui, c'est parfait. Merci beaucoup."],
                ['s', "C'est noté. Pensez à vous présenter à l'accueil à votre arrivée."],
            ],
            'bulletin' => [
                ['p', "Bonsoir {staffCiv}, le bulletin du {trimestre} de {enfant} n'apparaît pas encore dans l'application. Est-ce normal ?"],
                ['s', "Bonjour {civ}, les bulletins sont publiés à l'issue des conseils de classe. Celui de la classe de {classe} sera disponible d'ici la fin de la semaine."],
                ['p', "D'accord, merci."],
                ['s', "Bonjour {civ}, le bulletin de {enfant} est désormais disponible. {Il} obtient {moy}/20 de moyenne au {trimestre}. {appreciation}", 2],
                ['p', "Merci beaucoup ! {reaction}"],
            ],
            'orientation' => [
                ['p', "Bonjour {staffCiv}, {enfant} est en Troisième. Nous nous interrogeons sur son orientation en Seconde : série C ou série A ? Quels sont les critères ?"],
                ['s', "Bonjour {civ}, l'orientation en Seconde C est proposée aux élèves qui ont de bons résultats en mathématiques et en PCT (en général au moins 12 de moyenne dans ces deux matières). Le conseil de classe du troisième trimestre fera une proposition en tenant compte du vœu de la famille."],
                ['p', "Merci. Sa moyenne en mathématiques tourne autour de {moyMath}. La série C est-elle envisageable ?"],
                ['s', '{avis}'],
                ['p', "Merci pour ces conseils {staffCiv}."],
            ],
            'reinscription' => [
                ['p', "Bonjour {staffCiv}, comment se passe la réinscription de {enfant} pour l'année {suivante} ?"],
                ['s', "Bonjour {civ}, la réinscription se fait à l'économat du lundi au vendredi de 8 h 00 à 13 h 00 pendant les vacances. Les frais de dossier sont de {frais} FCFA, payables aussi par Mobile Money depuis l'application."],
                ['p', "Faut-il apporter des documents ?"],
                ['s', "Le bulletin annuel, deux photos d'identité et le reçu des frais de dossier. Les listes de fournitures seront affichées au portail fin août."],
                ['p', "Merci, je passerai la semaine prochaine."],
            ],
            'compte_enfant' => [
                ['p', "Bonjour, dans l'application je ne vois que {autre}. Mon autre enfant, {enfant}, en {classe}, n'apparaît pas."],
                ['s', "Bonjour {civ}, merci pour le signalement. Je vérifie le rattachement de {enfant} à votre compte."],
                ['s', "C'est corrigé : {enfant} est bien rattaché{e} à votre compte. Déconnectez-vous puis reconnectez-vous pour actualiser l'application."],
                ['p', "Ça marche, je vois bien les deux enfants maintenant. Merci !"],
            ],
            'compte_numero' => [
                ['p', "Bonjour, je vais changer de numéro de téléphone. Comment faire pour garder l'accès à l'application ?"],
                ['s', "Bonjour {civ}, envoyez-nous ici votre nouveau numéro ; nous le mettrons à jour dans votre fiche. Vous vous connecterez ensuite avec ce numéro et votre mot de passe habituel."],
                ['p', "D'accord. Je vous l'envoie dès que j'ai la nouvelle puce."],
                ['s', "Très bien. En attendant, votre numéro actuel reste valable."],
            ],
            'audience' => [
                ['p', "Bonjour mon Père, je sollicite une audience au sujet de la scolarité de {enfant} ({classe}). Je souhaiterais vous exposer la situation de vive voix."],
                ['s', "Bonjour {civ}, je vous recevrai volontiers le {rdv} à {heure} à mon bureau. Que le Seigneur vous garde."],
                ['p', "Merci mon Père, j'y serai."],
                ['p', "Merci pour votre écoute, mon Père.", 'rdv'],
                ['s', "Ce fut un plaisir. Nous restons attentifs à {enfant}. Bonne journée."],
            ],
        };
    }

    /**
     * Horodatage d'un dialogue : le parent écrit tôt le matin, à midi ou le soir ; le personnel
     * répond aux heures de bureau.
     *
     * @return list<array{0: int, 1: string, 2: string}>
     */
    private function horodater(string $jour, array $dialogue, array $v): array
    {
        $rng = $this->ctx->rng;
        $t = match (true) {
            $dialogue[0][0] !== 'p' => strtotime($rng->heureOuvree($jour, 8, 16).' UTC'),
            isset($v['@matin']) => strtotime($jour.' 06:00:00 UTC') + $rng->int(0, 89) * 60 + $rng->int(0, 59), // 6 h 00 - 7 h 30
            default => $this->heureParent($jour),
        };
        $precedent = null;
        $out = [];
        if (isset($v['{matiere}'])) {
            $v['{de_matiere}'] = (preg_match('/^[AEIOUÉÈHaeiouéèh]/u', $v['{matiere}']) ? 'd\'' : 'de ').$v['{matiere}'];
        }

        foreach ($dialogue as $ligne) {
            [$qui, $texte] = $ligne;
            $delai = $ligne[2] ?? null;
            if (is_array($texte)) {
                $texte = $rng->pick($texte);
            }

            if ($precedent !== null) {
                if ($delai === 'rdv' && isset($v['@rdv'])) {
                    // Après l'entretien (environ une demi-heure) : quelques minutes à quelques heures plus tard
                    $t = max($t + 3600, $v['@rdv'] + $rng->int(45, 300) * 60);
                    $t = $qui === 's' ? $this->reponseStaff($t) : $this->reponseParent($t);
                } elseif ($delai === 'h') {
                    // Arrivée au portail à l'heure de sortie annoncée
                    $t = isset($v['@sortie']) ? max($t + 300, $v['@sortie'] - $rng->int(0, 10) * 60) : $t + $rng->int(90, 240) * 60;
                } elseif ($delai === 'm') {
                    $t += $rng->int(2, 12) * 60; // quelques minutes plus tard
                } elseif (is_int($delai)) {
                    $jourSuivant = $this->ouvrable($this->plus(gmdate('Y-m-d', $t), $delai), $qui === 'p');
                    $t = max($t + 3600, $qui === 'p' ? $this->heureParent($jourSuivant) : strtotime($rng->heureOuvree($jourSuivant, 8, 16).' UTC'));
                } elseif ($qui === $precedent) {
                    $t += $rng->int(1, 12) * 60;
                } else {
                    $t = $qui === 's' ? $this->reponseStaff($t) : $this->reponseParent($t);
                }
            }

            $texte = strtr($texte, array_filter($v, fn ($k) => $k[0] === '{', ARRAY_FILTER_USE_KEY));
            if (str_contains($texte, '{lendemain}')) {
                $jourMessage = gmdate('Y-m-d', $t);
                $reprise = $this->ouvrable($this->plus($jourMessage, 1), false);
                $texte = str_replace('{lendemain}', $reprise === $this->plus($jourMessage, 1) ? 'demain' : 'le '.$this->fr($reprise), $texte);
            }
            // « Bonjour » ou « Bonsoir » selon l'heure d'envoi
            if (preg_match('/^(Bonjour|Bonsoir)\b/u', $texte)) {
                $texte = ((int) gmdate('G', $t) >= 18 ? 'Bonsoir' : 'Bonjour').substr($texte, 7);
            }
            $out[] = [$t, $qui, $texte];
            $precedent = $qui;
        }

        return $out;
    }

    private function heureParent(string $jour): int
    {
        $rng = $this->ctx->rng;
        $h = (int) $rng->weighted([6 => 10, 7 => 8, 8 => 3, 9 => 3, 10 => 3, 11 => 3, 12 => 8, 13 => 8, 14 => 3, 15 => 3, 16 => 3, 17 => 4, 18 => 6, 19 => 10, 20 => 12, 21 => 10, 22 => 3]);

        return strtotime(sprintf('%s %02d:%02d:%02d UTC', $jour, $h, $rng->int(0, 59), $rng->int(0, 59)));
    }

    /** Réponse du personnel : quelques minutes à quelques heures plus tard, aux heures de bureau. */
    private function reponseStaff(int $t): int
    {
        $rng = $this->ctx->rng;
        $t += $rng->int(4, 150) * 60;
        $jour = gmdate('Y-m-d', $t);
        $minutes = (int) gmdate('G', $t) * 60 + (int) gmdate('i', $t);
        $dow = $this->dow($jour);
        $ouvert = $this->ferie($jour) ? null : ($dow >= 1 && $dow <= 5 ? [450, 1080] : ($dow === 6 ? [480, 720] : null));

        if ($ouvert !== null && $minutes < $ouvert[0]) {
            return strtotime($jour.' UTC') + ($ouvert[0] + $rng->int(0, 60)) * 60 + $rng->int(0, 59);
        }
        if ($ouvert === null || $minutes > $ouvert[1]) {
            $jour = $this->ouvrable($this->plus($jour, 1), true);

            return strtotime($jour.' UTC') + (450 + $rng->int(5, 120)) * 60 + $rng->int(0, 59);
        }

        return $t;
    }

    /** Réponse du parent : quelques minutes à quelques heures plus tard, entre 6 h et 22 h 30. */
    private function reponseParent(int $t): int
    {
        $rng = $this->ctx->rng;
        $t += ($rng->chance(0.6) ? $rng->int(2, 40) : $rng->int(40, 300)) * 60;
        $h = (int) gmdate('G', $t);
        if ($h >= 23 || $h < 6) {
            $jour = gmdate('Y-m-d', $h >= 23 ? $t + 86400 : $t);

            return strtotime($jour.' UTC') + (6 * 60 + 15 + $rng->int(0, 120)) * 60 + $rng->int(0, 59);
        }

        return $t;
    }

    // ─────────────────────────────────────────────────────────────────
    // JOURNAL DES CONNEXIONS (application mobile parents)
    // ─────────────────────────────────────────────────────────────────

    private function connexions(): void
    {
        $rng = $this->ctx->rng;
        $limite = strtotime($this->limite.' UTC');
        $active = $this->ctx->anneeId('active');
        $precedente = $this->ctx->anneeId('precedente');

        // Poids de chaque jour : rentrée, sorties des bulletins et échéances de paiement attirent les connexions
        $calendriers = [];
        foreach (['precedente', 'active'] as $cle) {
            $annee = $this->ctx->annees[$cle];
            $periodes = $this->ctx->periodes[$annee['id']];
            $debut = $this->plus($annee['debut'], -20);
            $fin = min($annee['fin'], $this->plafond);
            $jours = [];
            $cumul = 0.0;
            for ($d = $debut; $d <= $fin; $d = $this->plus($d, 1)) {
                $w = 1.0;
                $mois = (int) substr($d, 5, 2);
                if ($mois === 8 || ($mois === 7 && (int) substr($d, 8, 2) > 15)) {
                    $w = 0.35;
                }
                if ($d < $periodes[0]['debut']) {
                    $w = 1.6; // inscriptions, réinscriptions et premiers paiements
                }
                foreach ($periodes as $p) {
                    if ($d > $p['fin'] && $d <= $this->plus($p['fin'], 12)) {
                        $w = 3.0; // publication des bulletins
                    }
                }
                foreach (['10-15', '01-15', '04-15'] as $echeance) {
                    $e = (substr($echeance, 0, 2) === '10' ? substr($annee['debut'], 0, 4) : substr($annee['fin'], 0, 4)).'-'.$echeance;
                    if ($d >= $this->plus($e, -5) && $d <= $e) {
                        $w = max($w, 2.0);
                    }
                }
                if (in_array($this->dow($d), [0, 6], true)) {
                    $w *= 1.15;
                }
                $cumul += $w;
                $jours[] = [$d, $cumul];
            }
            $calendriers[$cle] = $jours;
        }

        $lignes = [];
        $parents = 0;
        foreach ($this->ctx->familles as $f => $fam) {
            if (! $fam['mobile'] || empty($fam['parents'][0]['id'])) {
                continue;
            }
            $annees = [];
            foreach ($this->enfants[$f] ?? [] as $id) {
                foreach ([$precedente => 'precedente', $active => 'active'] as $anneeId => $cle) {
                    if (isset($this->ctx->eleves[$id]['classes'][$anneeId])) {
                        $annees[$cle] = true;
                    }
                }
            }
            if (! $annees) {
                continue;
            }

            foreach ($fam['parents'] as $i => $p) {
                $aEcrit = isset($this->premierMessage[$p['id']]);
                if ($i > 0 && ! $aEcrit && ! $rng->chance(0.45)) {
                    continue;
                }
                $creeLe = $this->comptesCreesLe[$p['user_id']] ?? ($this->ctx->annees['precedente']['debut'].' 08:00:00');
                $activite = ($fam['demo'] ?? false) ? 1.6 : exp($rng->gauss(-0.1, 0.55));
                $plage = $rng->pick(BeninNames::PLAGES_IP);
                $wifi = $rng->pick(BeninNames::PLAGES_IP).'.'.$rng->int(0, 255).'.'.$rng->int(2, 254);
                $ua = $rng->pick(BeninNames::USER_AGENTS);
                $ua2 = $rng->pick(BeninNames::USER_AGENTS);
                $dates = [];

                foreach (array_keys($annees) as $cle) {
                    $cal = $calendriers[$cle];
                    if (! $cal) {
                        continue;
                    }
                    $nbJours = count($cal);
                    $attendu = self::CONNEXIONS_PAR_MOIS[$cle] * $activite * $nbJours / 30.4;
                    $n = max(0, (int) round($attendu + $rng->gauss(0, sqrt(max(1.0, $attendu)) * 0.5)));
                    $total = end($cal)[1];
                    for ($k = 0; $k < $n; $k++) {
                        $jour = $this->tirerJour($cal, $rng->float() * $total);
                        $ts = $this->heureConnexion($jour);
                        if ($ts > $limite || gmdate('Y-m-d H:i:s', $ts) <= $creeLe) {
                            continue;
                        }
                        $dates[] = $ts;
                    }
                }

                // Connexion juste avant le premier message envoyé depuis l'application
                if ($aEcrit) {
                    $premier = strtotime($this->premierMessage[$p['id']].' UTC');
                    if (! $dates || min($dates) > $premier) {
                        $dates[] = max(strtotime($creeLe.' UTC') + 600, $premier - $rng->int(5, 4320) * 60);
                    }
                }
                // Compte de démonstration : connexion récente
                if ($fam['demo'] ?? false) {
                    $dates[] = $limite - $rng->int(30, 600) * 60;
                }
                if (! $dates) {
                    continue;
                }
                $parents++;

                foreach ($dates as $ts) {
                    $ip = match (true) {
                        $rng->chance(0.2) => $wifi,
                        $rng->chance(0.88) => $plage.'.'.$rng->int(0, 255).'.'.$rng->int(1, 254),
                        default => $rng->pick(BeninNames::PLAGES_IP).'.'.$rng->int(0, 255).'.'.$rng->int(1, 254),
                    };
                    $lignes[] = [
                        'user_id' => $p['user_id'], 'parent_id' => $p['id'], 'ip_address' => $ip,
                        'user_agent' => $rng->chance(0.9) ? $ua : $ua2, 'created_at' => gmdate('Y-m-d H:i:s', $ts),
                    ];
                }
            }
        }

        usort($lignes, fn ($a, $b) => [$a['created_at'], $a['parent_id']] <=> [$b['created_at'], $b['parent_id']]);
        $this->ctx->inserer('connexion_logs', $lignes);

        $this->stats['connexions'] = count($lignes);
        $this->stats['parents_connectes'] = $parents;
    }

    /** Recherche dichotomique du jour correspondant à un tirage dans les poids cumulés. */
    private function tirerJour(array $cal, float $x): string
    {
        $bas = 0;
        $haut = count($cal) - 1;
        while ($bas < $haut) {
            $milieu = intdiv($bas + $haut, 2);
            if ($cal[$milieu][1] < $x) {
                $bas = $milieu + 1;
            } else {
                $haut = $milieu;
            }
        }

        return $cal[$bas][0];
    }

    private function heureConnexion(string $jour): int
    {
        $rng = $this->ctx->rng;
        $h = (int) $rng->weighted([0 => 1, 5 => 1, 6 => 5, 7 => 6, 8 => 3, 9 => 3, 10 => 3, 11 => 3, 12 => 7, 13 => 7, 14 => 4, 15 => 3, 16 => 3, 17 => 4, 18 => 7, 19 => 10, 20 => 12, 21 => 10, 22 => 5, 23 => 2]);

        return strtotime(sprintf('%s %02d:%02d:%02d UTC', $jour, $h, $rng->int(0, 59), $rng->int(0, 59)));
    }

    // ─────────────────────────────────────────────────────────────────
    // HISTORIQUE DES IMPORTS
    // ─────────────────────────────────────────────────────────────────

    /** Reprise initiale des registres (août 2024) puis imports de listes de nouveaux élèves. */
    private function imports(): void
    {
        $c = $this->ctx->comptes;
        $repriseEleves = DB::table('eleves')->where('created_at', 'like', '2024-08-20%')->count();
        $repriseParents = DB::table('parents')->where('created_at', 'like', '2024-08-20%')->count();
        $nouveaux = DB::table('reinscriptions')->where('annee_scolaire_id', $this->ctx->anneeId('active'))->where('type', 'nouveau')->count();
        $saisie = $c['saisisseur'] ?? $c['admin'];
        $pre = $this->ctx->annees['precedente'];
        $act = $this->ctx->annees['active'];
        $finT1 = fn (array $a) => $this->ouvrable($this->plus($this->ctx->periodes[$a['id']][0]['fin'], 3), false);

        // La reprise initiale des registres est datée du 20 août 2024 dans EffectifsSeeder
        $lignes = [
            ['parents', 'parents_registre_'.$pre['libelle'].'.xlsx', 'partiel', sprintf('%d parents importés ; 6 lignes ignorées (numéro de téléphone déjà utilisé ou vide).', $repriseParents), $c['admin'], '2024-08-20 09:12:40'],
            ['eleves', 'registre_eleves_'.$pre['libelle'].'.xlsx', 'échec', 'Colonne « Date de naissance » introuvable : vérifiez que le fichier suit le modèle d\'import.', $c['admin'], '2024-08-20 09:41:05'],
            ['eleves', 'registre_eleves_'.$pre['libelle'].'_v2.xlsx', 'succès', sprintf('%d élèves importés et rattachés à leur classe.', $repriseEleves), $c['admin'], '2024-08-20 10:03:18'],
            ['notes', 'notes_T1_6e_mathematiques.xlsx', 'succès', 'Notes du premier trimestre importées depuis la fiche de saisie.', $saisie, $finT1($pre).' 10:25:51'],
            ['eleves', 'nouveaux_eleves_'.$act['libelle'].'.xlsx', 'partiel', sprintf('%d élèves importés ; 3 lignes ignorées (matricule déjà existant).', max(0, $nouveaux - 3)), $saisie, $this->ouvrable($this->plus($this->ctx->periodes[$act['id']][0]['debut'], 1), false).' 11:48:02'],
            ['notes', 'notes_T1_tle_D_svt.xlsx', 'succès', 'Notes du premier trimestre importées depuis la fiche de saisie.', $saisie, $finT1($act).' 15:07:33'],
        ];

        $rows = [];
        foreach ($lignes as [$type, $fichier, $statut, $message, $userId, $quand]) {
            if ($quand > $this->limite) {
                continue;
            }
            $rows[] = [
                'type_import' => $type, 'fichier_source' => $fichier, 'statut' => $statut, 'message' => $message,
                'user_id' => $userId, 'created_at' => $quand, 'updated_at' => $quand,
            ];
        }
        $this->ctx->inserer('import_logs', $rows);
        $this->stats['imports'] = count($rows);
    }

    // ─────────────────────────────────────────────────────────────────
    // OUTILS
    // ─────────────────────────────────────────────────────────────────

    private function nomClasse(string $cle): string
    {
        $classe = $this->ctx->classes[$cle];

        return trim($classe['niveau'].' '.$classe['suffixe']);
    }

    private function nomMatiere(string $code): string
    {
        $nom = Curriculum::MATIERES[$code][0] ?? $code;

        // Dans une phrase : « de mathématiques », « d'anglais », mais « de PCT », « d'EPS »
        return mb_strtoupper($nom) === $nom ? $nom : mb_strtolower($nom);
    }

    private function decimal(float $valeur): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($valeur, 2, '.', ''), '0'), '.'));
    }

    private function fcfa(float|int $montant): string
    {
        return number_format((float) $montant, 0, ',', ' ');
    }

    /** « lundi 3 février » (avec l'année si demandé). */
    private function fr(string $date, bool $annee = false): string
    {
        $t = strtotime($date.' 12:00:00 UTC');
        $j = (int) gmdate('j', $t);
        $texte = self::JOURS[(int) gmdate('w', $t)].' '.($j === 1 ? '1er' : $j).' '.self::MOIS[(int) gmdate('n', $t)];

        return $annee ? $texte.' '.gmdate('Y', $t) : $texte;
    }

    private function plus(string $date, int $jours): string
    {
        return gmdate('Y-m-d', strtotime($date.' 12:00:00 UTC') + 86400 * $jours);
    }

    private function dow(string $date): int
    {
        return (int) gmdate('w', strtotime($date.' 12:00:00 UTC'));
    }

    /** Premier jour de la semaine voulu (0 = dimanche ... 6 = samedi) à partir de la date. */
    private function prochain(string $date, int $jourSemaine): string
    {
        while ($this->dow($date) !== $jourSemaine) {
            $date = $this->plus($date, 1);
        }

        return $date;
    }

    /** Jour ouvré : le dimanche, un jour férié (et le samedi sauf si autorisé) sont reportés au jour ouvré suivant. */
    private function ouvrable(string $date, bool $samedi = false): string
    {
        while ($this->dow($date) === 0 || ($this->dow($date) === 6 && ! $samedi) || $this->ferie($date)) {
            $date = $this->plus($date, 1);
        }

        return $date;
    }

    private function ferie(string $date): bool
    {
        return in_array(substr($date, 5), self::FERIES, true);
    }

    /** Jour ouvré antérieur ou égal (ni week-end ni jour férié). */
    private function ouvrableAvant(string $date): string
    {
        while (in_array($this->dow($date), [0, 6], true) || in_array(substr($date, 5), self::FERIES, true)) {
            $date = $this->plus($date, -1);
        }

        return $date;
    }

    /** Dimanche de Pâques (calendrier grégorien, algorithme de Meeus/Butcher). */
    private function paques(int $an): string
    {
        $a = $an % 19;
        $b = intdiv($an, 100);
        $c = $an % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mois = intdiv($h + $l - 7 * $m + 114, 31);
        $jour = (($h + $l - 7 * $m + 114) % 31) + 1;

        return sprintf('%04d-%02d-%02d', $an, $mois, $jour);
    }
}
