<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Note;
use App\Models\Paiement;
use App\Services\BulletinService;
use Illuminate\Http\Request;

class EleveController extends Controller
{
    public function __construct(private BulletinService $bulletinService) {}

    /**
     * Résout l'année scolaire ciblée par la requête : le paramètre "annee_scolaire_id" s'il est
     * fourni, sinon l'année active. Paramètre optionnel et additif : les appels existants de
     * l'app mobile (sans ce paramètre) continuent de fonctionner, juste désormais bien scopés
     * sur l'année active plutôt que sur toutes les années confondues.
     */
    private function resolveAnneeScolaireId(Request $request): ?int
    {
        if ($request->filled('annee_scolaire_id')) {
            return (int) $request->input('annee_scolaire_id');
        }

        return AnneeScolaire::active()?->id;
    }

    private function scopeNotesParAnnee($query, ?int $anneeScolaireId)
    {
        if ($anneeScolaireId) {
            $query->whereHas('classeAnnee', fn ($q) => $q->where('annee_scolaire_id', $anneeScolaireId));
        }

        return $query;
    }

    public function dernieresNotes($id, Request $request)
    {
        $limit = $request->get('limit', 5);
        $anneeScolaireId = $this->resolveAnneeScolaireId($request);

        $notes = $this->scopeNotesParAnnee(Note::where('eleve_id', $id), $anneeScolaireId)
            ->with(['matiere', 'periode', 'typeNote'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'valeur' => (float) $note->valeur, // Forcer en float
                    'matiere_nom' => $note->matiere->nom_matiere,
                    'periode_nom' => $note->periode->nom,
                    'type_note_nom' => $note->typeNote->nom,
                    'commentaire' => $note->commentaire,
                    'created_at' => $note->created_at->diffForHumans(),
                ];
            });

        return response()->json($notes);
    }

    public function toutesNotes($id, Request $request)
    {
        $eleve = Eleve::findOrFail($id);
        $anneeScolaireId = $this->resolveAnneeScolaireId($request);

        $notes = $this->scopeNotesParAnnee(Note::where('eleve_id', $id), $anneeScolaireId)
            ->with(['matiere', 'periode', 'typeNote', 'enseignant.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Coefficients par classe_annee réellement liée à chaque note (pas la classe ACTUELLE de
        // l'élève) : un élève promu doit voir, sur ses notes passées, les coefficients d'alors.
        $classeAnneeIds = $notes->pluck('classe_annee_id')->filter()->unique();
        $coefficients = ClasseMatiere::whereIn('classe_annee_id', $classeAnneeIds)
            ->get()
            ->groupBy('classe_annee_id')
            ->map(fn ($group) => $group->keyBy('matiere_id'));

        $notes = $notes->map(function ($note) use ($coefficients) {
            $coefficient = $coefficients->get($note->classe_annee_id)?->get($note->matiere_id)?->coefficient;

            return [
                'id' => $note->id,
                'valeur' => (float) $note->valeur,
                'matiere_nom' => $note->matiere->nom_matiere,
                'periode_nom' => $note->periode->nom,
                'type_note_nom' => $note->typeNote->nom,
                'commentaire' => $note->commentaire,
                'created_at' => $note->created_at->format('d/m/Y H:i'),
                'enseignant_nom' => optional($note->enseignant?->user)->name ?? '-',
                'coefficient' => $coefficient !== null ? (float) $coefficient : 1.0,
                'appreciation' => $this->getAppreciation($note->valeur),
            ];
        });

        return response()->json($notes);
    }

    public function statistiques($id, Request $request)
    {
        $anneeScolaireId = $this->resolveAnneeScolaireId($request);

        $notes = $this->scopeNotesParAnnee(Note::where('eleve_id', $id), $anneeScolaireId)->get();

        $moyenne = $notes->avg('valeur');
        $meilleure = $notes->max('valeur');
        $plusFaible = $notes->min('valeur');
        $totalNotes = $notes->count();

        return response()->json([
            'moyenne' => $moyenne !== null ? round($moyenne, 2) : null,
            'meilleure' => $meilleure,
            'plus_faible' => $plusFaible,
            'total_notes' => $totalNotes,
        ]);
    }

    /**
     * Bulletin de l'élève (toutes périodes de l'année ciblée) : pour chaque matière, le détail
     * par type de note (interrogations, devoir, composition...), la moyenne de matière, la
     * moyenne coefficiée, et la moyenne générale par période + annuelle. Réutilise le même
     * calcul que le cahier de notes admin (BulletinService), donc toujours cohérent avec lui.
     */
    public function bulletin($id, Request $request)
    {
        $eleve = Eleve::findOrFail($id);
        $anneeScolaireId = $this->resolveAnneeScolaireId($request);

        $b = $this->bulletinService->bulletinComplet($eleve, $anneeScolaireId, 'ponderee');

        if (! $b['classeAnnee']) {
            return response()->json([
                'periodes' => [],
                'moyenne_annuelle' => null,
                'appreciation' => '',
            ]);
        }

        $periodes = $b['allPeriodes']->map(function ($p) use ($b) {
            $matieres = $b['matieres']->map(function ($mat) use ($b, $p) {
                $detail = $b['detailMatPeriode'][$p->id][$mat->id] ?? [];
                $types = $b['typesNotes']->map(function ($tn) use ($detail) {
                    $d = $detail[$tn->id] ?? ['valeurs' => [], 'moyenne' => null];

                    return [
                        'nom' => $tn->nom,
                        'code' => $tn->code,
                        'pre_moyenne' => (bool) $tn->pre_moyenner,
                        'valeurs' => $d['valeurs'],
                        'moyenne' => $d['moyenne'],
                    ];
                })->values();

                $moyMatiere = $b['moyMatPeriode'][$p->id][$mat->id] ?? null;

                return [
                    'id' => $mat->id,
                    'nom' => $mat->nom_matiere,
                    'coefficient' => (float) $mat->coefficient,
                    'types' => $types,
                    'moyenne_matiere' => $moyMatiere,
                    'moyenne_coefficiee' => $moyMatiere !== null
                        ? round($moyMatiere * $mat->coefficient, 2)
                        : null,
                ];
            })->values();

            return [
                'id' => $p->id,
                'nom' => $p->nom,
                'moyenne_generale' => $b['moyGenPeriode'][$p->id] ?? null,
                'rang' => $b['rangsParPeriode'][$p->id] ?? null,
                'matieres' => $matieres,
            ];
        })->values();

        return response()->json([
            'periodes' => $periodes,
            'total_coefficients' => (float) $b['totalCoeff'],
            'moyenne_annuelle' => $b['moyAnnuelle'],
            'appreciation' => $b['appreciation'],
        ]);
    }

    /**
     * Liste des années scolaires pour lesquelles l'élève a des notes et/ou des paiements,
     * afin que le parent puisse consulter les données des années antérieures depuis le mobile.
     */
    public function anneesScolaires($id)
    {
        Eleve::findOrFail($id);

        $anneeIdsNotes = Note::where('eleve_id', $id)
            ->join('classe_annees', 'classe_annees.id', '=', 'notes.classe_annee_id')
            ->pluck('classe_annees.annee_scolaire_id');

        $anneeIdsPaiements = Paiement::where('eleve_id', $id)
            ->whereNotNull('tranche_id')
            ->join('tranches', 'tranches.id', '=', 'paiements.tranche_id')
            ->join('scolarites', 'scolarites.id', '=', 'tranches.scolarite_id')
            ->join('classe_annees', 'classe_annees.id', '=', 'scolarites.classe_annee_id')
            ->pluck('classe_annees.annee_scolaire_id');

        $anneeIds = $anneeIdsNotes->merge($anneeIdsPaiements)->unique();

        $annees = AnneeScolaire::whereIn('id', $anneeIds)
            ->orderByDesc('date_debut')
            ->get(['id', 'libelle', 'est_active']);

        return response()->json($annees);
    }

    /**
     * Emploi du temps de l'élève (classe_annee courante), groupé par jour et trié par créneau,
     * pour l'affichage dans l'app mobile côté parent.
     */
    public function emploiDuTemps($id)
    {
        $eleve = Eleve::findOrFail($id);

        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

        if (! $eleve->classe_annee_id) {
            return response()->json([]);
        }

        $emplois = EmploiDuTemps::where('classe_annee_id', $eleve->classe_annee_id)
            ->with('matiere', 'creneauHoraire')
            ->get()
            ->sortBy('creneauHoraire.heure_debut')
            ->groupBy('jour_semaine');

        $classeMatieres = ClasseMatiere::where('classe_annee_id', $eleve->classe_annee_id)
            ->with('enseignant')
            ->get()
            ->keyBy('matiere_id');

        $resultat = collect($jours)->map(function ($jour) use ($emplois, $classeMatieres) {
            $creneaux = ($emplois->get($jour) ?? collect())->map(function ($e) use ($classeMatieres) {
                $enseignant = $classeMatieres->get($e->matiere_id)?->enseignant;

                return [
                    'heure_debut' => $e->creneauHoraire->heure_debut_courte,
                    'heure_fin' => $e->creneauHoraire->heure_fin_courte,
                    'matiere' => $e->matiere->nom_matiere,
                    'enseignant' => $enseignant ? trim($enseignant->nom.' '.$enseignant->prenom) : null,
                    'salle' => $e->salle,
                ];
            })->values();

            return [
                'jour' => $jour,
                'creneaux' => $creneaux,
            ];
        });

        return response()->json($resultat);
    }

    private function getAppreciation($valeur)
    {
        if ($valeur == 0) {
            return 'Nul';
        }
        if ($valeur < 6) {
            return 'Médiocre';
        }
        if ($valeur < 8) {
            return 'Faible';
        }
        if ($valeur < 10) {
            return 'Insuffisant';
        }
        if ($valeur < 12) {
            return 'Passable';
        }
        if ($valeur < 14) {
            return 'Assez bien';
        }
        if ($valeur < 16) {
            return 'Bien';
        }
        if ($valeur < 18) {
            return 'Très bien';
        }

        return 'Excellent';
    }
}
