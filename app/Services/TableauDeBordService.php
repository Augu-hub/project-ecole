<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\CantineOffre;
use App\Models\ConnexionLog;
use App\Models\Eleve;
use App\Models\FraisDossier;
use App\Models\Paiement;
use App\Models\Periode;
use App\Models\Tranche;
use App\Models\TypeNote;
use App\Models\UniformeVente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chiffres, historiques et activité récente du tableau de bord de l'administration.
 *
 * Chaque méthode exécute un nombre fixe de requêtes, quel que soit le volume de données : les
 * totaux sont calculés par la base (COUNT / SUM / GROUP BY), les listes sont limitées, et aucune
 * table n'est chargée en mémoire. Le SQL écrit à la main reste commun à MySQL et à SQLite
 * (pas de fonction de date propre à l'un ou à l'autre).
 */
class TableauDeBordService
{
    /** Nombre d'années scolaires affichées dans l'historique. */
    public const ANNEES_AFFICHEES = 6;

    /** Nombre de lignes de chaque liste d'activité récente. */
    public const LIGNES_ACTIVITE = 5;

    /** Nombre de mois ajoutés au plus, avant et après l'année, pour les paiements faits hors de ses dates. */
    private const MOIS_HORS_ANNEE = 12;

    private const MODES_DE_PAIEMENT = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile money',
        'virement' => 'Virement',
        'carte' => 'Carte',
        'cheque' => 'Chèque',
    ];

    /**
     * @param  int  $fenetreNotes  Nombre de notes, parmi les dernières créées, examinées pour retrouver
     *                             les saisies récentes : la recherche ne parcourt jamais toute la table.
     */
    public function __construct(private int $fenetreNotes = 5000) {}

    // ─────────────────────────────────────────────────────────────────
    // CHIFFRES CLÉS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Effectifs de l'année : total, élèves actifs, garçons, filles, sexe non renseigné et élèves
     * ajoutés depuis la date donnée. Une seule requête ; tout est à zéro sans année active.
     *
     * @return array{total: int, actifs: int, garcons: int, filles: int, sexeNonRenseigne: int, nouveaux: int}
     */
    public function effectifs(?int $anneeScolaireId, \DateTimeInterface $nouveauxDepuis): array
    {
        $ligne = Eleve::query()->pourAnnee($anneeScolaireId)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as actifs")
            ->selectRaw("SUM(CASE WHEN sexe = 'M' THEN 1 ELSE 0 END) as garcons")
            ->selectRaw("SUM(CASE WHEN sexe = 'F' THEN 1 ELSE 0 END) as filles")
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as nouveaux', [$nouveauxDepuis])
            ->first();

        $total = (int) $ligne->total;
        $garcons = (int) $ligne->garcons;
        $filles = (int) $ligne->filles;

        return [
            'total' => $total,
            'actifs' => (int) $ligne->actifs,
            'garcons' => $garcons,
            'filles' => $filles,
            'sexeNonRenseigne' => $total - $garcons - $filles,
            'nouveaux' => (int) $ligne->nouveaux,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // HISTORIQUE PAR ANNÉE SCOLAIRE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Une ligne par année scolaire, de la plus récente à la plus ancienne : élèves inscrits, classes
     * ouvertes, notes saisies (avec le détail par période) et, si demandé, encaissements.
     *
     * - Élèves inscrits : élèves aujourd'hui dans une classe de l'année, plus ceux qui en sont partis
     *   par réinscription (« elevesPartis ») — la réinscription déplace l'élève dans sa nouvelle
     *   classe, il ne doit pas disparaître de l'effectif de l'année qu'il a quittée.
     * - Notes : rattachées à l'année par leur période (une période appartient à une seule année).
     * - Encaissements : même règle que Paiement::pourAnnee() (ce qui est payé, pas la classe actuelle).
     *
     * L'année active figure toujours dans le résultat.
     *
     * @return array{lignes: Collection<int, array<string, mixed>>, anneesPlusAnciennes: bool}
     */
    public function historiqueParAnnee(?AnneeScolaire $anneeActive, bool $avecEncaissements): array
    {
        // Une année de plus que l'affichage, pour savoir s'il en existe de plus anciennes
        $annees = AnneeScolaire::query()
            ->orderByDesc('date_debut')->orderByDesc('id')
            ->limit(self::ANNEES_AFFICHEES + 1)
            ->get();

        $anneesPlusAnciennes = $annees->count() > self::ANNEES_AFFICHEES;
        $annees = $annees->take(self::ANNEES_AFFICHEES);

        if ($anneeActive && ! $annees->contains('id', $anneeActive->id)) {
            $annees = $annees->take(self::ANNEES_AFFICHEES - 1)->push($anneeActive);
            $anneesPlusAnciennes = true;
        }

        $ids = $annees->pluck('id')->all();

        if (! $ids) {
            return ['lignes' => collect(), 'anneesPlusAnciennes' => false];
        }

        $classes = DB::table('classe_annees')
            ->whereIn('annee_scolaire_id', $ids)
            ->selectRaw('annee_scolaire_id, COUNT(*) as total')
            ->groupBy('annee_scolaire_id')
            ->pluck('total', 'annee_scolaire_id');

        $elevesActuels = DB::table('eleves')
            ->join('classe_annees', 'classe_annees.id', '=', 'eleves.classe_annee_id')
            ->whereIn('classe_annees.annee_scolaire_id', $ids)
            ->selectRaw('classe_annees.annee_scolaire_id as annee_scolaire_id, COUNT(*) as total')
            ->groupBy('classe_annees.annee_scolaire_id')
            ->pluck('total', 'annee_scolaire_id');

        // Élèves réinscrits depuis dans une autre année : ils ont bien été inscrits dans celle-ci
        $elevesPartis = DB::table('reinscriptions')
            ->join('classe_annees as quittee', 'quittee.id', '=', 'reinscriptions.classe_annee_precedente_id')
            ->join('eleves', 'eleves.id', '=', 'reinscriptions.eleve_id')
            ->join('classe_annees as actuelle', 'actuelle.id', '=', 'eleves.classe_annee_id')
            ->whereIn('quittee.annee_scolaire_id', $ids)
            ->whereColumn('actuelle.annee_scolaire_id', '<>', 'quittee.annee_scolaire_id')
            ->selectRaw('quittee.annee_scolaire_id as annee_scolaire_id, COUNT(DISTINCT reinscriptions.eleve_id) as total')
            ->groupBy('quittee.annee_scolaire_id')
            ->pluck('total', 'annee_scolaire_id');

        $periodes = Periode::query()
            ->whereIn('annee_scolaire_id', $ids)
            ->orderBy('date_debut')->orderBy('id')
            ->get(['id', 'annee_scolaire_id', 'nom']);

        $notesParPeriode = $periodes->isEmpty() ? collect() : DB::table('notes')
            ->whereIn('periode_id', $periodes->pluck('id'))
            ->selectRaw('periode_id, COUNT(*) as total')
            ->groupBy('periode_id')
            ->pluck('total', 'periode_id');

        $encaissements = $avecEncaissements ? $this->encaissementsParAnnee($ids) : collect();

        // toBase() : les lignes sont de simples tableaux, plus des modèles
        $lignes = $annees->toBase()->sortBy([['date_debut', 'desc'], ['id', 'desc']])->values()
            ->map(function (AnneeScolaire $annee) use ($anneeActive, $classes, $elevesActuels, $elevesPartis, $periodes, $notesParPeriode, $encaissements, $avecEncaissements) {
                $periodesDeLAnnee = $periodes->where('annee_scolaire_id', $annee->id)->values()
                    ->map(fn (Periode $periode) => [
                        'id' => $periode->id,
                        'nom' => $periode->nom,
                        'notes' => (int) ($notesParPeriode[$periode->id] ?? 0),
                    ]);

                $partis = (int) ($elevesPartis[$annee->id] ?? 0);

                return [
                    'id' => $annee->id,
                    'libelle' => $annee->libelle,
                    'debut' => $annee->date_debut,
                    'fin' => $annee->date_fin,
                    'active' => $anneeActive?->id === $annee->id,
                    'eleves' => (int) ($elevesActuels[$annee->id] ?? 0) + $partis,
                    'elevesPartis' => $partis,
                    'classes' => (int) ($classes[$annee->id] ?? 0),
                    'notes' => (int) $periodesDeLAnnee->sum('notes'),
                    'periodes' => $periodesDeLAnnee,
                    'encaissements' => $avecEncaissements ? (float) ($encaissements[$annee->id] ?? 0) : null,
                ];
            });

        return ['lignes' => $lignes, 'anneesPlusAnciennes' => $anneesPlusAnciennes];
    }

    /**
     * Total encaissé pour chacune des années, en une seule requête : un sous-total par année, tous
     * calculés avec Paiement::pourAnnee(), pour ne jamais diverger du total affiché ailleurs.
     *
     * @param  list<int>  $anneeIds
     * @return Collection<int, float>
     */
    private function encaissementsParAnnee(array $anneeIds): Collection
    {
        $requete = null;

        foreach ($anneeIds as $anneeId) {
            $sousTotal = Paiement::query()->pourAnnee($anneeId)->toBase()
                ->selectRaw((int) $anneeId.' as annee_scolaire_id, COALESCE(SUM(montant), 0) as total');

            $requete = $requete ? $requete->unionAll($sousTotal) : $sousTotal;
        }

        return $requete
            ? $requete->get()->mapWithKeys(fn ($ligne) => [(int) $ligne->annee_scolaire_id => (float) $ligne->total])
            : collect();
    }

    // ─────────────────────────────────────────────────────────────────
    // ENCAISSEMENTS DE L'ANNÉE, MOIS PAR MOIS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Encaissements rattachés à l'année, mois par mois, de la rentrée à la fin de l'année. Un
     * paiement fait avant la rentrée ou après la fin de l'année allonge la période affichée
     * (au plus d'un an de chaque côté) ; au-delà, son montant est reporté dans « horsPeriode ».
     *
     * @return array{mois: Collection<int, array{cle: string, libelle: string, court: string, total: float, nombre: int}>, total: float, nombre: int, horsPeriode: float}
     */
    public function encaissementsParMois(AnneeScolaire $annee): array
    {
        // Un sous-total par jour de paiement, regroupé ensuite par mois en PHP : la requête n'emploie
        // aucune fonction de date, elle est donc la même sous MySQL et sous SQLite. Elle renvoie au
        // plus une ligne par jour où un paiement a été enregistré.
        $parJour = Paiement::query()->pourAnnee($annee->id)->toBase()
            ->selectRaw('date_paiement, SUM(montant) as total, COUNT(*) as nombre')
            ->groupBy('date_paiement')
            ->get();

        $parMois = [];
        $horsPeriode = 0.0;
        $total = 0.0;
        $nombre = 0;

        foreach ($parJour as $jour) {
            $total += (float) $jour->total;
            $nombre += (int) $jour->nombre;

            $cle = substr((string) $jour->date_paiement, 0, 7);
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $cle)) {
                $horsPeriode += (float) $jour->total;

                continue;
            }

            $parMois[$cle] ??= ['total' => 0.0, 'nombre' => 0];
            $parMois[$cle]['total'] += (float) $jour->total;
            $parMois[$cle]['nombre'] += (int) $jour->nombre;
        }

        $debut = CarbonImmutable::parse($annee->date_debut)->startOfMonth();
        $fin = CarbonImmutable::parse($annee->date_fin)->startOfMonth();
        if ($fin->lt($debut)) {
            $fin = $debut;
        }

        if ($parMois) {
            $premier = CarbonImmutable::createFromFormat('!Y-m', min(array_keys($parMois)));
            $dernier = CarbonImmutable::createFromFormat('!Y-m', max(array_keys($parMois)));

            $debut = $debut->min($premier)->max($debut->subMonths(self::MOIS_HORS_ANNEE));
            $fin = $fin->max($dernier)->min($fin->addMonths(self::MOIS_HORS_ANNEE));
        }

        $mois = collect();
        for ($courant = $debut; $courant->lte($fin); $courant = $courant->addMonth()) {
            $cle = $courant->format('Y-m');

            $mois->push([
                'cle' => $cle,
                'libelle' => $courant->locale('fr')->isoFormat('MMMM YYYY'),
                'court' => $courant->locale('fr')->isoFormat('MMM YY'),
                'total' => $parMois[$cle]['total'] ?? 0.0,
                'nombre' => $parMois[$cle]['nombre'] ?? 0,
            ]);

            unset($parMois[$cle]);
        }

        // Ce qui reste est en dehors de la période affichée
        $horsPeriode += array_sum(array_column($parMois, 'total'));

        return compact('mois', 'total', 'nombre', 'horsPeriode');
    }

    // ─────────────────────────────────────────────────────────────────
    // ACTIVITÉ RÉCENTE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Derniers paiements enregistrés, toutes années confondues (un paiement tardif pour une année
     * précédente reste une opération récente).
     *
     * @return Collection<int, array{date: Carbon, eleve: string, objet: string, montant: float, mode: string}>
     */
    public function derniersPaiements(int $limite = self::LIGNES_ACTIVITE): Collection
    {
        return Paiement::query()
            ->with(['eleve:id,nom,prenom', 'tranche:id,libelle'])
            ->orderByDesc('date_paiement')->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->map(fn (Paiement $paiement) => [
                'date' => Carbon::parse($paiement->date_paiement),
                'eleve' => $paiement->eleve ? trim($paiement->eleve->prenom.' '.$paiement->eleve->nom) : 'Élève non renseigné',
                'objet' => $this->objetDuPaiement($paiement),
                'montant' => (float) $paiement->montant,
                'mode' => self::MODES_DE_PAIEMENT[$paiement->mode_paiement] ?? (string) $paiement->mode_paiement,
            ]);
    }

    /** Ce que règle le paiement : scolarité (avec sa tranche), frais de dossier, cantine ou uniforme. */
    private function objetDuPaiement(Paiement $paiement): string
    {
        return match ($paiement->payable_type) {
            FraisDossier::class => 'Frais de dossier',
            CantineOffre::class => 'Cantine',
            UniformeVente::class => 'Uniforme',
            Tranche::class, null => $paiement->tranche ? 'Scolarité · '.$paiement->tranche->libelle : 'Scolarité',
            default => 'Paiement',
        };
    }

    /**
     * Derniers élèves inscrits dans une classe de l'année.
     *
     * @return Collection<int, Eleve>
     */
    public function dernieresInscriptions(?int $anneeScolaireId, int $limite = self::LIGNES_ACTIVITE): Collection
    {
        return Eleve::query()->pourAnnee($anneeScolaireId)
            ->with('classeAnnee.classe.niveau')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /**
     * Dernières saisies de notes, toutes années confondues. Une « saisie » regroupe les notes d'une
     * même classe, matière, période, type et numéro d'évaluation.
     *
     * La première requête repère les saisies touchées par les dernières notes créées (fenêtre
     * bornée sur la clé primaire), la seconde compte exactement les notes de ces seules saisies —
     * une saisie complétée plus tard affiche bien son effectif total, pas seulement les ajouts.
     *
     * @return Collection<int, array{classe: ?string, matiere: string, type: string, periode: string, annee: ?string, anneeId: ?int, nombre: int, date: Carbon}>
     */
    public function dernieresSaisiesDeNotes(int $limite = self::LIGNES_ACTIVITE): Collection
    {
        $cles = ['classe_annee_id', 'matiere_id', 'periode_id', 'type_note_id', 'numero'];

        // Seuil calculé ici et non en SQL : sous MySQL, « max(id) - 5000 » sur une clé non signée
        // provoque une erreur dès que la table compte moins de notes que la fenêtre
        $seuil = max(0, (int) DB::table('notes')->max('id') - $this->fenetreNotes);

        $recentes = DB::table('notes')
            ->where('id', '>', $seuil)
            ->select($cles)
            ->selectRaw('MAX(id) as derniere_note')
            ->groupBy($cles)
            ->orderByDesc('derniere_note')
            ->limit($limite)
            ->get();

        if ($recentes->isEmpty()) {
            return collect();
        }

        $colonnes = array_map(fn (string $cle) => 'notes.'.$cle, $cles);

        $saisies = DB::table('notes')
            ->join('matieres', 'matieres.id', '=', 'notes.matiere_id')
            ->join('type_notes', 'type_notes.id', '=', 'notes.type_note_id')
            ->join('periodes', 'periodes.id', '=', 'notes.periode_id')
            ->join('annee_scolaires', 'annee_scolaires.id', '=', 'periodes.annee_scolaire_id')
            ->leftJoin('classe_annees', 'classe_annees.id', '=', 'notes.classe_annee_id')
            ->leftJoin('classes', 'classes.id', '=', 'classe_annees.classe_id')
            ->leftJoin('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            // Les périodes concernées d'abord (colonne indexée), puis chaque saisie exactement
            ->whereIn('notes.periode_id', $recentes->pluck('periode_id')->unique()->values())
            ->where(function ($requete) use ($recentes) {
                foreach ($recentes as $saisie) {
                    $requete->orWhere(function ($q) use ($saisie) {
                        $q->where('notes.matiere_id', $saisie->matiere_id)
                            ->where('notes.periode_id', $saisie->periode_id)
                            ->where('notes.type_note_id', $saisie->type_note_id)
                            ->where('notes.numero', $saisie->numero);

                        $saisie->classe_annee_id === null
                            ? $q->whereNull('notes.classe_annee_id')
                            : $q->where('notes.classe_annee_id', $saisie->classe_annee_id);
                    });
                }
            })
            ->select($colonnes)
            ->selectRaw('COUNT(*) as nombre, MAX(notes.id) as derniere_note, MAX(notes.created_at) as saisie_le')
            // Libellés : une seule valeur possible par saisie, MAX() ne sert qu'à satisfaire le GROUP BY strict
            ->selectRaw('MAX(matieres.nom_matiere) as matiere, MAX(type_notes.nom) as type_nom, MAX(type_notes.max_par_periode) as type_max')
            ->selectRaw('MAX(periodes.nom) as periode, MAX(annee_scolaires.id) as annee_id, MAX(annee_scolaires.libelle) as annee')
            ->selectRaw('MAX(niveaux.nom) as niveau, MAX(classes.suffixe) as suffixe')
            ->groupBy($colonnes)
            ->orderByDesc('derniere_note')
            ->get();

        return $saisies->map(function ($saisie) {
            // Même présentation que la fiche de notes : « Interrogation n°2 » si le type se répète dans la période
            $type = (new TypeNote(['nom' => $saisie->type_nom]))->libelle;
            if ((int) $saisie->type_max > 1) {
                $type .= ' n°'.(int) $saisie->numero;
            }

            return [
                'classe' => trim($saisie->niveau.' '.$saisie->suffixe) ?: null,
                'matiere' => (string) $saisie->matiere,
                'type' => $type,
                'periode' => (string) $saisie->periode,
                'annee' => $saisie->annee,
                'anneeId' => $saisie->annee_id === null ? null : (int) $saisie->annee_id,
                'nombre' => (int) $saisie->nombre,
                'date' => Carbon::parse($saisie->saisie_le),
            ];
        });
    }

    /**
     * Dernières connexions des parents à l'application mobile (journal d'audit).
     *
     * @return Collection<int, ConnexionLog>
     */
    public function dernieresConnexions(int $limite = self::LIGNES_ACTIVITE): Collection
    {
        // Les lignes ne sont jamais modifiées : l'ordre des identifiants est l'ordre chronologique,
        // et il évite de trier tout le journal sur une date non indexée
        return ConnexionLog::query()
            ->with('parent:id,nom,prenom')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────
    // MEILLEURES MOYENNES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Élèves ayant la meilleure moyenne simple des notes saisies sur la période (sans coefficient :
     * ce n'est pas la moyenne du bulletin). La moyenne est calculée par la base, élève par élève.
     *
     * @return Collection<int, Eleve>
     */
    public function meilleuresMoyennes(int $periodeId, int $limite = self::LIGNES_ACTIVITE): Collection
    {
        $moyennes = DB::table('notes')
            ->where('periode_id', $periodeId)
            ->selectRaw('eleve_id, AVG(valeur) as moyenne, COUNT(*) as nombre_notes')
            ->groupBy('eleve_id');

        return Eleve::query()
            ->select('eleves.*', 'moyennes.moyenne', 'moyennes.nombre_notes')
            ->joinSub($moyennes, 'moyennes', 'moyennes.eleve_id', '=', 'eleves.id')
            ->with('classeAnnee.classe.niveau')
            ->orderByDesc('moyennes.moyenne')->orderBy('eleves.nom')->orderBy('eleves.prenom')
            ->limit($limite)
            ->get();
    }
}
