<?php

namespace App\Services;

use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Periode;
use App\Models\TypeNote;
use Illuminate\Support\Facades\DB;

/**
 * Centralise le calcul des moyennes/rangs de bulletin, utilisé par le cahier de notes
 * (vue classe/liste) et le bulletin individuel d'un élève. Auparavant cette logique était
 * dupliquée avec deux algorithmes d'ex-aequo différents entre CahierNotesController et son
 * bloc bulletinEleve() ; il n'y a plus qu'une seule implémentation ici.
 */
class BulletinService
{
    /**
     * Moyenne d'une matière à partir des notes d'un élève regroupées PAR TYPE
     * (interrogation, devoir, composition...) : $valeursParType[type_note_id] = [valeurs...].
     *
     * Formule : les types marqués `pre_moyenner` sont d'abord réduits à UNE moyenne avant
     * d'entrer dans le calcul final ; les autres (Devoir, Composition) y entrent
     * individuellement. Selon comment l'école modélise "plusieurs interros par période", ce
     * pré-moyennage porte soit sur les occurrences d'UN SEUL type (numero 1..4 sur la même
     * ligne "Interrogation"), soit sur PLUSIEURS types distincts partageant le même
     * `groupe_moyenne` (ex. des lignes séparées "Interrogation 1", "Interrogation 2"...) —
     * les deux modélisations cohabitent donc sans configuration supplémentaire. La moyenne de
     * matière est la moyenne simple de ce pool. Un type sans aucune note (absence justifiée,
     * pas encore saisi) est simplement absent du pool — jamais compté comme 0.
     *
     * @return array{moyenne: ?float, detail: array} detail[type_note_id] = ['valeurs' => [...], 'moyenne' => ?float]
     */
    public function moyenneMatiereDetail(array $valeursParType, $typesNotes): array
    {
        $detail = [];
        foreach ($typesNotes as $tn) {
            $vals = array_map('floatval', $valeursParType[$tn->id] ?? []);
            $detail[$tn->id] = [
                'valeurs' => $vals,
                'moyenne' => count($vals) ? round(array_sum($vals) / count($vals), 2) : null,
            ];
        }

        $pool = [];
        $groupes = $typesNotes->groupBy(fn ($tn) => $tn->groupe_moyenne ?? 'seul-'.$tn->id);

        foreach ($groupes as $typesDuGroupe) {
            $valeurs = [];
            $preMoyenner = false;
            foreach ($typesDuGroupe as $tn) {
                array_push($valeurs, ...$detail[$tn->id]['valeurs']);
                $preMoyenner = $preMoyenner || (bool) $tn->pre_moyenner;
            }

            if (empty($valeurs)) {
                continue;
            }

            if ($preMoyenner) {
                $pool[] = round(array_sum($valeurs) / count($valeurs), 2);
            } else {
                array_push($pool, ...$valeurs);
            }
        }

        return [
            'moyenne' => count($pool) ? round(array_sum($pool) / count($pool), 2) : null,
            'detail' => $detail,
        ];
    }

    /**
     * Moyennes par matière et moyenne générale pour un ensemble d'élèves d'une classe_annee donnée.
     *
     * @param  array  $notes  $notes[eleve_id][matiere_id][type_note_id] = [valeurs...] (une entrée par numéro/occurrence)
     * @return array{0: array, 1: array, 2: float} [$moyMatiere, $moyGenerale, $totalCoeff]
     */
    public function calculerMoyennes($eleves, $matieres, $typesNotes, array $notes, string $modeMoyenne): array
    {
        $moyMatiere = [];
        $totalCoeff = $matieres->sum('coefficient');

        foreach ($eleves as $eleve) {
            foreach ($matieres as $mat) {
                $valeursParType = $notes[$eleve->id][$mat->id] ?? [];
                $moyMatiere[$eleve->id][$mat->id] =
                    $this->moyenneMatiereDetail($valeursParType, $typesNotes)['moyenne'];
            }
        }

        $moyGenerale = [];
        foreach ($eleves as $eleve) {
            $moyGenerale[$eleve->id] = $this->moyenneGenerale($matieres, $moyMatiere[$eleve->id] ?? [], $modeMoyenne);
        }

        return [$moyMatiere, $moyGenerale, $totalCoeff];
    }

    /**
     * Moyenne générale d'un seul élève à partir de ses moyennes par matière déjà calculées.
     */
    public function moyenneGenerale($matieres, array $moyennesParMatiere, string $modeMoyenne): ?float
    {
        if ($modeMoyenne === 'ponderee') {
            $sumPond = 0;
            $sumCoeff = 0;
            foreach ($matieres as $mat) {
                $moy = $moyennesParMatiere[$mat->id] ?? null;
                if ($moy === null) {
                    continue;
                }
                $c = (float) ($mat->coefficient ?? 1);
                $sumPond += $moy * $c;
                $sumCoeff += $c;
            }

            return $sumCoeff > 0 ? round($sumPond / $sumCoeff, 2) : null;
        }

        $vals = array_filter(
            $matieres->map(fn ($mat) => $moyennesParMatiere[$mat->id] ?? null)->all(),
            fn ($v) => $v !== null
        );

        return count($vals) ? round(array_sum($vals) / count($vals), 2) : null;
    }

    /**
     * Rangs par ordre de moyenne générale décroissante, gestion des ex-aequo par tolérance
     * (deux moyennes à 0.001 près sont considérées égales).
     */
    public function calculerRangs($eleves, array $moyGenerale, bool $afficherRang = true): array
    {
        if (! $afficherRang) {
            return [];
        }

        $rangs = [];
        $sorted = $eleves->sortByDesc(fn ($e) => $moyGenerale[$e->id] ?? -1)->values();
        $rang = 1;

        foreach ($sorted as $i => $e) {
            if ($i > 0) {
                $prevVal = $moyGenerale[$sorted[$i - 1]->id] ?? null;
                $curVal = $moyGenerale[$e->id] ?? null;
                $sontEgaux = $prevVal !== null && $curVal !== null && abs($prevVal - $curVal) < 0.001;
                if (! $sontEgaux) {
                    $rang = $i + 1;
                }
            }
            $rangs[$e->id] = $rang;
        }

        return $rangs;
    }

    public function statsParMatiere($eleves, $matieres, array $moyMatiere): array
    {
        $stats = [];
        foreach ($matieres as $mat) {
            $vals = array_filter(
                $eleves->map(fn ($e) => $moyMatiere[$e->id][$mat->id] ?? null)->all(),
                fn ($v) => $v !== null
            );
            $stats[$mat->id] = [
                'moy' => count($vals) ? round(array_sum($vals) / count($vals), 2) : null,
                'min' => count($vals) ? min($vals) : null,
                'max' => count($vals) ? max($vals) : null,
            ];
        }

        return $stats;
    }

    public function statsGenerales($eleves, array $moyGenerale): array
    {
        $vals = array_filter($moyGenerale, fn ($v) => $v !== null);
        $n = count($vals);

        return [
            'moy_classe' => $n ? round(array_sum($vals) / $n, 2) : null,
            'max' => $n ? max($vals) : null,
            'min' => $n ? min($vals) : null,
            'nb_admis' => count(array_filter($vals, fn ($v) => $v >= 10)),
            'taux_reussite' => $n > 0
                ? round(count(array_filter($vals, fn ($v) => $v >= 10)) / $n * 100)
                : 0,
        ];
    }

    /**
     * Résout la classe_annee dans laquelle l'élève a réellement ses notes pour une année scolaire
     * donnée (via notes.classe_annee_id), plutôt que sa classe_annee ACTUELLE — indispensable pour
     * qu'un élève promu conserve, sur ses bulletins passés, les coefficients de sa classe d'alors.
     * Repli sur la classe_annee courante de l'élève si aucune note n'existe encore pour cette année
     * (ex : tout début d'année scolaire).
     */
    public function resoudreClasseAnneePourAnnee(Eleve $eleve, ?int $anneeScolaireId): ?ClasseAnnee
    {
        if ($anneeScolaireId) {
            $classeAnneeId = Note::where('eleve_id', $eleve->id)
                ->whereHas('classeAnnee', fn ($q) => $q->where('annee_scolaire_id', $anneeScolaireId))
                ->value('classe_annee_id');

            if ($classeAnneeId) {
                return ClasseAnnee::with('classe.niveau', 'anneeScolaire')->find($classeAnneeId);
            }

            // Aucune note pour cette année : si c'est la classe_annee courante de l'élève, on l'utilise.
            $eleve->loadMissing('classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire');
            if ($eleve->classeAnnee && $eleve->classeAnnee->annee_scolaire_id == $anneeScolaireId) {
                return $eleve->classeAnnee;
            }

            return null;
        }

        $eleve->loadMissing('classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire');

        return $eleve->classeAnnee;
    }

    /**
     * Bulletin complet d'un élève (toutes périodes de l'année ciblée), avec les coefficients
     * de la classe_annee réellement liée à ses notes pour cette année (voir resoudreClasseAnneePourAnnee).
     */
    public function bulletinComplet(Eleve $eleve, ?int $anneeScolaireId, string $modeMoyenne): array
    {
        $classeAnnee = $this->resoudreClasseAnneePourAnnee($eleve, $anneeScolaireId);

        if (! $classeAnnee) {
            return [
                'classeAnnee' => null, 'allPeriodes' => collect(), 'matieres' => collect(),
                'typesNotes' => collect(), 'notes' => [], 'moyMatPeriode' => [], 'detailMatPeriode' => [],
                'moyGenPeriode' => [], 'rangsParPeriode' => [], 'statsMoyClasse' => [], 'statsGeneralesParPeriode' => [],
                'totalCoeff' => 0, 'moyAnnuelle' => null, 'appreciation' => '', 'tousEleves' => collect(),
            ];
        }

        $allPeriodes = Periode::where('annee_scolaire_id', $classeAnnee->annee_scolaire_id)
            ->orderBy('id')->get();

        $matieres = DB::table('classe_matieres')
            ->join('matieres', 'matieres.id', '=', 'classe_matieres.matiere_id')
            ->where('classe_matieres.classe_annee_id', $classeAnnee->id)
            ->select('matieres.id', 'matieres.nom_matiere', 'classe_matieres.coefficient')
            ->orderBy('matieres.nom_matiere')->get();

        $typesNotes = TypeNote::ordonnes()->get();
        $matiereIds = $matieres->pluck('id')->all();
        $totalCoeff = $matieres->sum('coefficient');

        // Notes de l'élève rattachées à CETTE classe_annee précisément (pas toutes ses notes toutes années).
        $notesRaw = Note::where('eleve_id', $eleve->id)
            ->where('classe_annee_id', $classeAnnee->id)
            ->whereIn('matiere_id', $matiereIds)
            ->get();

        $notes = [];
        foreach ($notesRaw as $n) {
            $notes[$n->periode_id][$n->matiere_id][$n->type_note_id][] = $n->valeur;
        }

        $tousEleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')->get();
        // L'élève consulté peut avoir changé de classe depuis : on l'ajoute s'il n'y est plus.
        if (! $tousEleves->contains('id', $eleve->id)) {
            $tousEleves->push($eleve);
        }

        $notesClasseRaw = Note::whereIn('eleve_id', $tousEleves->pluck('id'))
            ->where('classe_annee_id', $classeAnnee->id)
            ->whereIn('matiere_id', $matiereIds)
            ->get();

        $notesClasseParPeriode = [];
        foreach ($notesClasseRaw as $n) {
            $notesClasseParPeriode[$n->periode_id][$n->eleve_id][$n->matiere_id][$n->type_note_id][] = $n->valeur;
        }

        $moyMatPeriode = [];
        $detailMatPeriode = [];
        $moyGenPeriode = [];
        $rangsParPeriode = [];
        $statsMoyClasse = [];
        $statsGeneralesParPeriode = [];

        foreach ($allPeriodes as $p) {
            $notesPeriode = $notesClasseParPeriode[$p->id] ?? [];

            $moysClasse = [];
            foreach ($tousEleves as $e) {
                $moyMatEleve = [];
                $detailMatEleve = [];
                foreach ($matieres as $mat) {
                    $valeursParType = $notesPeriode[$e->id][$mat->id] ?? [];
                    $r = $this->moyenneMatiereDetail($valeursParType, $typesNotes);
                    $moyMatEleve[$mat->id] = $r['moyenne'];
                    $detailMatEleve[$mat->id] = $r['detail'];
                }
                if ($e->id === $eleve->id) {
                    $moyMatPeriode[$p->id] = $moyMatEleve;
                    $detailMatPeriode[$p->id] = $detailMatEleve;
                }
                $moysClasse[$e->id] = $this->moyenneGenerale($matieres, $moyMatEleve, $modeMoyenne);
            }

            $moyGenPeriode[$p->id] = $moysClasse[$eleve->id] ?? null;

            $rangs = $this->calculerRangs($tousEleves, $moysClasse, true);
            $rangsParPeriode[$p->id] = $rangs[$eleve->id] ?? null;
            $statsGeneralesParPeriode[$p->id] = $this->statsGenerales($tousEleves, $moysClasse);

            $statsMoyClasse[$p->id] = [];
            foreach ($matieres as $mat) {
                $vals = [];
                foreach ($tousEleves as $e) {
                    $valeursParType = $notesPeriode[$e->id][$mat->id] ?? [];
                    $moy = $this->moyenneMatiereDetail($valeursParType, $typesNotes)['moyenne'];
                    if ($moy !== null) {
                        $vals[] = $moy;
                    }
                }
                $statsMoyClasse[$p->id][$mat->id] = count($vals)
                    ? round(array_sum($vals) / count($vals), 2)
                    : null;
            }
        }

        $moysPeriodes = array_filter($moyGenPeriode, fn ($v) => $v !== null);
        $moyAnnuelle = count($moysPeriodes) ? round(array_sum($moysPeriodes) / count($moysPeriodes), 2) : null;

        $appreciation = match (true) {
            $moyAnnuelle === null => '',
            $moyAnnuelle >= 18 => 'Félicitations — Travail excellent',
            $moyAnnuelle >= 16 => 'Très bien — Continuez vos efforts',
            $moyAnnuelle >= 14 => 'Bien — Bon travail',
            $moyAnnuelle >= 12 => 'Assez bien — Peut mieux faire',
            $moyAnnuelle >= 10 => 'Passable — Efforts insuffisants',
            $moyAnnuelle >= 8 => 'Insuffisant — Travail à améliorer',
            default => 'Très insuffisant — Sérieuses lacunes',
        };

        return compact(
            'classeAnnee', 'allPeriodes', 'matieres', 'typesNotes', 'notes',
            'moyMatPeriode', 'detailMatPeriode', 'moyGenPeriode', 'rangsParPeriode', 'statsMoyClasse',
            'statsGeneralesParPeriode', 'totalCoeff', 'moyAnnuelle', 'appreciation', 'tousEleves'
        );
    }
}
