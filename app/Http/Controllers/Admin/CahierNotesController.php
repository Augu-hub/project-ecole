<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Periode;
use App\Models\TypeNote;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CahierNotesController extends Controller
{
    public function __construct(private BulletinService $bulletinService) {}

    // ─────────────────────────────────────────────────────────────────
    // PAGE SÉLECTEUR
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $anneeActive = \App\Models\AnneeScolaire::active();

        if (! $anneeActive) {
            return redirect()->back()->with('error', 'Aucune année scolaire active trouvée.');
        }

        $classesAnnees = ClasseAnnee::with('classe.niveau', 'anneeScolaire')
            ->where('annee_scolaire_id', $anneeActive->id)
            ->get()
            ->sortBy(fn ($ca) => $ca->classe->niveau->ordre);

        $periodes = Periode::with('anneeScolaire')
            ->where('annee_scolaire_id', $anneeActive->id)
            ->orderBy('id')
            ->get();

        return view('back.pages.cahier-notes.index', compact('classesAnnees', 'periodes'));
    }

    // ─────────────────────────────────────────────────────────────────
    // CAHIER DE NOTES D'UNE CLASSE — UNE PÉRIODE
    // LIGNES = matières  |  COLONNES = élèves
    // GET /admin/cahier-notes/classe/{classeAnnee}?periode_id=2
    // ─────────────────────────────────────────────────────────────────
    public function classe(Request $request, ClasseAnnee $classeAnnee)
    {
        $classeAnnee->load('classe.niveau', 'anneeScolaire');

        $periodeId = $request->input('periode_id');
        $modeMoyenne = $request->input('mode_moyenne', 'ponderee');
        $afficherRang = (bool) $request->input('afficher_rang', 1);

        $allPeriodes = Periode::where('annee_scolaire_id', $classeAnnee->annee_scolaire_id)
            ->orderBy('id')->get();

        $periode = $periodeId
            ? $allPeriodes->firstWhere('id', $periodeId)
            : $allPeriodes->first();

        if (! $periode) {
            return redirect()->route('admin.cahier-notes.index')
                ->with('error', 'Aucune période disponible.');
        }

        $typesNotes = TypeNote::ordonnes()->get();

        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')->get();

        $matieres = DB::table('classe_matieres')
            ->join('matieres', 'matieres.id', '=', 'classe_matieres.matiere_id')
            ->where('classe_matieres.classe_annee_id', $classeAnnee->id)
            ->select('matieres.id', 'matieres.nom_matiere', 'classe_matieres.coefficient')
            ->orderBy('matieres.nom_matiere')->get();

        $matiereIds = $matieres->pluck('id')->all();
        $eleveIds = $eleves->pluck('id')->all();

        $notesRaw = Note::where('periode_id', $periode->id)
            ->whereIn('eleve_id', $eleveIds)
            ->whereIn('matiere_id', $matiereIds)
            ->get();

        $notes = [];
        foreach ($notesRaw as $n) {
            $notes[$n->eleve_id][$n->matiere_id][$n->type_note_id][] = $n->valeur;
        }

        [$moyMatiere, $moyGenerale, $totalCoeff] =
            $this->bulletinService->calculerMoyennes($eleves, $matieres, $typesNotes, $notes, $modeMoyenne);

        $rangs = $this->bulletinService->calculerRangs($eleves, $moyGenerale, $afficherRang);

        $statsMat = $this->bulletinService->statsParMatiere($eleves, $matieres, $moyMatiere);
        $statsGenerales = $this->bulletinService->statsGenerales($eleves, $moyGenerale);

        return view('back.pages.cahier-notes.classe', compact(
            'classeAnnee', 'allPeriodes', 'periode', 'eleves',
            'matieres', 'typesNotes', 'notes', 'moyMatiere', 'moyGenerale',
            'rangs', 'statsMat', 'statsGenerales', 'totalCoeff',
            'modeMoyenne', 'afficherRang'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // LISTE DES ÉLÈVES D'UNE CLASSE (point d'entrée bulletin)
    // GET /admin/cahier-notes/classe/{classeAnnee}/eleves
    // ─────────────────────────────────────────────────────────────────
    public function listeEleves(Request $request, ClasseAnnee $classeAnnee)
    {
        $classeAnnee->load('classe.niveau', 'anneeScolaire');

        $periodeId = $request->input('periode_id');
        $modeMoyenne = $request->input('mode_moyenne', 'ponderee');

        $allPeriodes = Periode::where('annee_scolaire_id', $classeAnnee->annee_scolaire_id)
            ->orderBy('id')->get();

        $periode = $periodeId
            ? $allPeriodes->firstWhere('id', $periodeId)
            : $allPeriodes->first();

        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')->get();

        // Calcul rapide des moyennes générales pour l'affichage de la liste
        $matieres = DB::table('classe_matieres')
            ->join('matieres', 'matieres.id', '=', 'classe_matieres.matiere_id')
            ->where('classe_matieres.classe_annee_id', $classeAnnee->id)
            ->select('matieres.id', 'matieres.nom_matiere', 'classe_matieres.coefficient')
            ->orderBy('matieres.nom_matiere')->get();

        $typesNotes = TypeNote::ordonnes()->get();

        $notesRaw = $periode
            ? Note::where('periode_id', $periode->id)
                ->whereIn('eleve_id', $eleves->pluck('id'))
                ->whereIn('matiere_id', $matieres->pluck('id'))
                ->get()
            : collect();

        $notes = [];
        foreach ($notesRaw as $n) {
            $notes[$n->eleve_id][$n->matiere_id][$n->type_note_id][] = $n->valeur;
        }

        [$moyMatiere, $moyGenerale, $totalCoeff] =
            $this->bulletinService->calculerMoyennes($eleves, $matieres, $typesNotes, $notes, $modeMoyenne);

        $rangs = $this->bulletinService->calculerRangs($eleves, $moyGenerale, true);

        return view('back.pages.cahier-notes.liste-eleves', compact(
            'classeAnnee', 'allPeriodes', 'periode',
            'eleves', 'moyGenerale', 'rangs', 'modeMoyenne'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // BULLETIN INDIVIDUEL D'UN ÉLÈVE — TOUTES LES PÉRIODES
    // GET /admin/cahier-notes/eleve/{eleve}?mode_moyenne=ponderee
    // ─────────────────────────────────────────────────────────────────
    public function bulletinEleve(Request $request, Eleve $eleve)
    {
        $modeMoyenne = $request->input('mode_moyenne', 'ponderee');
        $anneeScolaireId = $request->input('annee_scolaire_id');

        $bulletin = $this->bulletinService->bulletinComplet($eleve, $anneeScolaireId ? (int) $anneeScolaireId : null, $modeMoyenne);

        if (! $bulletin['classeAnnee']) {
            return redirect()->route('admin.cahier-notes.index')
                ->with('error', "Aucune donnée de classe trouvée pour cet élève sur l'année demandée.");
        }

        // Années scolaires pour lesquelles cet élève a des notes, pour le sélecteur d'historique.
        $anneeIds = Note::where('eleve_id', $eleve->id)
            ->join('classe_annees', 'classe_annees.id', '=', 'notes.classe_annee_id')
            ->distinct()
            ->pluck('classe_annees.annee_scolaire_id')
            ->push($bulletin['classeAnnee']->annee_scolaire_id)
            ->unique();

        $anneesDisponibles = \App\Models\AnneeScolaire::whereIn('id', $anneeIds)
            ->orderByDesc('date_debut')->get();

        return view('back.pages.cahier-notes.bulletin-eleve', array_merge($bulletin, [
            'eleve' => $eleve,
            'modeMoyenne' => $modeMoyenne,
            'anneesDisponibles' => $anneesDisponibles,
            'anneeScolaireId' => $bulletin['classeAnnee']->annee_scolaire_id,
        ]));
    }
}
