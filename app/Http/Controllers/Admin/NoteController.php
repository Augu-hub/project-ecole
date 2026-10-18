<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Periode;
use App\Models\TypeNote;
use App\Services\ImageNoteExtractorService;
use App\Services\NotesPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoteController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // NOTIFICATIONS FCM
    // ─────────────────────────────────────────────────────────────────

    private function notifyParents(array $notes, $matiere, $typeNote, Messaging $messaging): void
    {
        $notificationController = new NotificationController($messaging);

        foreach ($notes as $eleveId => $data) {
            if (empty($data['valeur'])) {
                continue;
            }

            $eleve = Eleve::with('parentPrincipal.user')->find($eleveId);
            if (! $eleve || ! $eleve->parentPrincipal || ! $eleve->parentPrincipal->user) {
                continue;
            }

            $parentUser = $eleve->parentPrincipal->user;
            if (! $parentUser->fcm_token) {
                continue;
            }

            $notificationController->sendFCMNotification(
                $parentUser->fcm_token,
                "Nouvelle note pour {$eleve->prenom}",
                "{$eleve->prenom} a obtenu {$data['valeur']}/20 en {$matiere->nom_matiere} ({$typeNote->libelle})",
                ['route' => '/dashboard']
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // INDEX avec filtres et tris
    // ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Note::with(['eleve.classeAnnee.classe.niveau', 'matiere', 'periode', 'typeNote']);

        // Sans choix explicite, la liste s'ouvre sur l'année active ; « Toutes les années »
        // envoie une valeur vide et lève le filtre.
        $anneeSelectionneeId = $request->has('annee_scolaire_id')
            ? $request->input('annee_scolaire_id')
            : AnneeScolaire::active()?->id;

        // Application des filtres
        if ($anneeSelectionneeId) {
            $query->whereHas('eleve.classeAnnee', function ($q) use ($anneeSelectionneeId) {
                $q->where('annee_scolaire_id', $anneeSelectionneeId);
            });
        }

        if ($request->filled('classe_id')) {
            $query->whereHas('eleve.classeAnnee', function ($q) use ($request) {
                $q->where('classe_id', $request->classe_id);
            });
        }

        if ($request->filled('periode_id')) {
            $query->where('periode_id', $request->periode_id);
        }

        if ($request->filled('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        if ($request->filled('type_note_id')) {
            $query->where('type_note_id', $request->type_note_id);
        }

        // Filtre par plage de dates
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', today()->subDay());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'last_week':
                    $query->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', now()->subMonth()->month)
                        ->whereYear('created_at', now()->subMonth()->year);
                    break;
            }
        }

        // Recherche par élève
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('eleve', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        // Filtre par note minimale
        if ($request->filled('note_min')) {
            $query->where('valeur', '>=', (float) $request->note_min);
        }

        // Filtre par note maximale
        if ($request->filled('note_max')) {
            $query->where('valeur', '<=', (float) $request->note_max);
        }

        // Application du tri
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');

        switch ($sort) {
            case 'id':
                $query->orderBy('id', $direction);
                break;
            case 'classe':
                $query->join('eleves', 'notes.eleve_id', '=', 'eleves.id')
                    ->join('classe_annees', 'eleves.classe_annee_id', '=', 'classe_annees.id')
                    ->join('classes', 'classe_annees.classe_id', '=', 'classes.id')
                    ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
                    ->orderBy('niveaux.ordre', $direction)
                    ->orderBy('classes.suffixe', $direction)
                    ->select('notes.*');
                break;
            case 'eleve':
                $query->join('eleves', 'notes.eleve_id', '=', 'eleves.id')
                    ->orderBy('eleves.nom', $direction)
                    ->orderBy('eleves.prenom', $direction)
                    ->select('notes.*');
                break;
            case 'matiere':
                $query->join('matieres', 'notes.matiere_id', '=', 'matieres.id')
                    ->orderBy('matieres.nom_matiere', $direction)
                    ->select('notes.*');
                break;
            case 'periode':
                $query->join('periodes', 'notes.periode_id', '=', 'periodes.id')
                    ->orderBy('periodes.created_at', $direction)
                    ->select('notes.*');
                break;
            case 'valeur':
                $query->orderBy('valeur', $direction);
                break;
            default:
                $query->orderBy('created_at', $direction);
        }

        $notes = $query->paginate(20);

        // Données pour les filtres
        $anneesScolaires = AnneeScolaire::orderBy('libelle', 'desc')->get();
        $classes = Classe::with('niveau')->orderByFullName()->get();
        // Périodes de l'année filtrée (plus celle déjà choisie, pour qu'elle reste visible
        // dans la liste si on change d'année ensuite).
        $periodes = Periode::with('anneeScolaire')
            ->when($anneeSelectionneeId, fn ($q) => $q->where(fn ($q) => $q
                ->where('annee_scolaire_id', $anneeSelectionneeId)
                ->orWhere('id', $request->input('periode_id'))))
            ->orderBy('created_at', 'desc')
            ->get();
        $matieres = Matiere::orderBy('nom_matiere')->get();
        $typeNotes = TypeNote::ordonnes()->get();

        // Variables pour la vue
        $sortBy = $sort;
        $sortDirection = $direction;

        return view('back.pages.notes.index', compact(
            'notes', 'anneesScolaires', 'anneeSelectionneeId', 'classes', 'periodes',
            'matieres', 'typeNotes', 'sortBy', 'sortDirection'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT EXCEL
    // ─────────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $query = Note::with(['eleve.classeAnnee.classe.niveau', 'matiere', 'periode', 'typeNote']);

        // Appliquer les mêmes filtres que pour l'index
        if ($request->filled('annee_scolaire_id')) {
            $query->whereHas('eleve.classeAnnee', function ($q) use ($request) {
                $q->where('annee_scolaire_id', $request->annee_scolaire_id);
            });
        }

        if ($request->filled('classe_id')) {
            $query->whereHas('eleve.classeAnnee', function ($q) use ($request) {
                $q->where('classe_id', $request->classe_id);
            });
        }

        if ($request->filled('periode_id')) {
            $query->where('periode_id', $request->periode_id);
        }

        if ($request->filled('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        if ($request->filled('type_note_id')) {
            $query->where('type_note_id', $request->type_note_id);
        }

        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', today()->subDay());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'last_week':
                    $query->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', now()->subMonth()->month)
                        ->whereYear('created_at', now()->subMonth()->year);
                    break;
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('eleve', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        if ($request->filled('note_min')) {
            $query->where('valeur', '>=', (float) $request->note_min);
        }

        if ($request->filled('note_max')) {
            $query->where('valeur', '<=', (float) $request->note_max);
        }

        // Application du tri pour l'export
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');

        switch ($sort) {
            case 'classe':
                $query->join('eleves', 'notes.eleve_id', '=', 'eleves.id')
                    ->join('classe_annees', 'eleves.classe_annee_id', '=', 'classe_annees.id')
                    ->join('classes', 'classe_annees.classe_id', '=', 'classes.id')
                    ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
                    ->orderBy('niveaux.ordre', $direction)
                    ->orderBy('classes.suffixe', $direction)
                    ->select('notes.*');
                break;
            case 'eleve':
                $query->join('eleves', 'notes.eleve_id', '=', 'eleves.id')
                    ->orderBy('eleves.nom', $direction)
                    ->orderBy('eleves.prenom', $direction)
                    ->select('notes.*');
                break;
            case 'matiere':
                $query->join('matieres', 'notes.matiere_id', '=', 'matieres.id')
                    ->orderBy('matieres.nom_matiere', $direction)
                    ->select('notes.*');
                break;
            case 'valeur':
                $query->orderBy('valeur', $direction);
                break;
            default:
                $query->orderBy('created_at', $direction);
        }

        $notes = $query->get();

        // Création du fichier Excel
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Titre
        $sheet->setTitle('Export Notes');

        // En-têtes
        $headers = ['ID', 'Classe', 'Élève', 'Matricule', 'Matière', 'Période', 'Type de note', 'Note /20', 'Commentaire', 'Date de saisie'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

        // Style des en-têtes
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D47A1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        foreach ($headers as $i => $header) {
            $sheet->setCellValue($columns[$i].'1', $header);
            $sheet->getStyle($columns[$i].'1')->applyFromArray($headerStyle);
        }

        // Largeurs des colonnes
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(40);
        $sheet->getColumnDimension('J')->setWidth(18);

        // Remplissage des données
        $row = 2;
        foreach ($notes as $note) {
            $classeName = $note->eleve->classeAnnee->classe->full_name ??
                          ($note->eleve->classeAnnee->classe->niveau->nom ?? '').' '.
                          ($note->eleve->classeAnnee->classe->suffixe ?? '');

            $sheet->setCellValue('A'.$row, $note->id);
            $sheet->setCellValue('B'.$row, trim($classeName));
            $sheet->setCellValue('C'.$row, $note->eleve->nom.' '.$note->eleve->prenom);
            $sheet->setCellValue('D'.$row, $note->eleve->matricule ?? 'N/A');
            $sheet->setCellValue('E'.$row, $note->matiere->nom_matiere);
            $sheet->setCellValue('F'.$row, $note->periode->nom);
            $sheet->setCellValue('G'.$row, $note->typeNote->libelle);
            $sheet->setCellValue('H'.$row, $note->valeur);
            $sheet->setCellValue('I'.$row, $note->commentaire);
            $sheet->setCellValue('J'.$row, $note->created_at->format('d/m/Y H:i:s'));

            // Alternance des couleurs
            if ($row % 2 == 0) {
                $sheet->getStyle('A'.$row.':J'.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F5F5F5');
            }

            $row++;
        }

        // Freeze la première ligne
        $sheet->freezePane('A2');

        // Téléchargement
        $filename = 'notes_export_'.date('Y-m-d_His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Préparation d'une saisie : choix de la classe, de la matière, de la période et de l'évaluation.
     *
     * Les listes sont liées sans appel réseau : la page reçoit, pour chaque classe, les matières qui
     * lui sont rattachées (classe_matieres). Tout suit l'année scolaire active. Un enseignant ne voit
     * que les matières qui lui sont attribuées, donc que ses classes (même règle que assertPeutSaisir()).
     */
    public function create(\App\Services\RepriseMatieresService $repriseMatieres)
    {
        $user = Auth::user();
        $estEnseignant = $user->role === 'enseignant';
        $enseignant = $user->enseignant;
        $ficheEnseignantManquante = $estEnseignant && ! $enseignant;

        $anneeActive = AnneeScolaire::active();

        $classesAnnees = ClasseAnnee::with('classe.niveau', 'anneeScolaire')
            ->deLAnneeActive()
            ->get()
            ->sortBy(fn (ClasseAnnee $classeAnnee) => [
                $classeAnnee->classe->niveau->ordre,
                $classeAnnee->classe->niveau->nom,
                $classeAnnee->classe->suffixe,
            ])
            ->values();

        // Matières rattachées à ces classes, dans l'ordre alphabétique, avec l'enseignant attribué
        $rattachements = $ficheEnseignantManquante
            ? collect()
            : DB::table('classe_matieres')
                ->join('matieres', 'matieres.id', '=', 'classe_matieres.matiere_id')
                ->whereIn('classe_matieres.classe_annee_id', $classesAnnees->modelKeys())
                ->when($estEnseignant, fn ($q) => $q->where('classe_matieres.enseignant_id', $enseignant->id))
                ->orderBy('matieres.nom_matiere')
                ->get([
                    'classe_matieres.classe_annee_id', 'classe_matieres.matiere_id',
                    'classe_matieres.enseignant_id', 'matieres.nom_matiere',
                ]);

        if ($estEnseignant) {
            $classesAnnees = $classesAnnees->whereIn('id', $rattachements->pluck('classe_annee_id')->all())->values();
        }

        // Pour chaque classe proposée (même sans matière) : la liste de ses matières
        $parClasse = $rattachements->groupBy('classe_annee_id');
        $matieresParClasse = [];
        foreach ($classesAnnees as $classeAnnee) {
            $matieresParClasse[$classeAnnee->id] = $parClasse->get($classeAnnee->id, collect())
                ->map(fn ($ligne) => [
                    'id' => (int) $ligne->matiere_id,
                    'nom' => $ligne->nom_matiere,
                    'enseignant' => $ligne->enseignant_id !== null,
                ])
                ->values()
                ->all();
        }

        // Toutes classes confondues : liste affichée tant qu'aucune classe n'est choisie si le
        // navigateur n'exécute pas le script de la page
        $matieres = $rattachements->unique('matiere_id')
            ->map(fn ($ligne) => ['id' => (int) $ligne->matiere_id, 'nom' => $ligne->nom_matiere])
            ->values();

        $classesSansMatiere = $classesAnnees
            ->filter(fn (ClasseAnnee $classeAnnee) => $matieresParClasse[$classeAnnee->id] === [])
            ->values();

        // Reprise des matières de l'année précédente : proposée à ceux qui gèrent les matières des classes
        $reprise = $classesSansMatiere->isNotEmpty() && \App\Support\AccesMenu::autorise('admin.classe-matieres.reprise-annee')
            ? $repriseMatieres->apercu($classesSansMatiere)
            : collect();

        // La saisie se fait sur l'année active : seules ses périodes sont proposées.
        $periodes = Periode::with('anneeScolaire')
            ->whereHas('anneeScolaire', fn ($q) => $q->active())
            ->orderBy('date_debut')
            ->orderBy('id')
            ->get();
        $types = TypeNote::ordonnes()->get();

        // store() rattache chaque note à un enseignant : celui attribué à la matière dans la classe,
        // sinon celui du compte connecté, sinon l'enseignant par défaut. S'il n'en existe aucun dans
        // l'établissement, la page prévient dès le choix de la matière, avant une saisie pour rien.
        $avertirSansEnseignant = ! $enseignant && $this->enseignantParDefaut() === null;

        return view('back.pages.notes.create', compact(
            'anneeActive', 'classesAnnees', 'matieres', 'matieresParClasse', 'classesSansMatiere', 'reprise',
            'periodes', 'types', 'estEnseignant', 'ficheEnseignantManquante', 'avertirSansEnseignant'
        ));
    }

    /**
     * Vérifie que l'utilisateur connecté a le droit de saisir des notes pour ce couple classe/matière.
     * Un enseignant ne peut agir que sur ses propres classe_annee/matiere assignés ; les autres rôles ne sont pas restreints ici.
     */
    private function assertPeutSaisir(int $classeAnneeId, int $matiereId): void
    {
        if (Auth::user()->role !== 'enseignant') {
            return;
        }

        $enseignant = Auth::user()->enseignant;
        abort_if(! $enseignant, 403, 'Aucune fiche enseignant associée à votre compte.');

        $autorise = $enseignant->classeMatieres()
            ->where('classe_annee_id', $classeAnneeId)
            ->where('matiere_id', $matiereId)
            ->exists();

        abort_unless($autorise, 403, "Vous n'êtes pas assigné à cette classe/matière.");
    }

    /**
     * Critères de la fiche en cours (mémorisés en session à l'étape « preview »).
     * Retourne null si la session a expiré ou si un élément a été supprimé entre-temps,
     * pour rediriger proprement au lieu de planter sur un modèle introuvable.
     *
     * @return array{0: ClasseAnnee, 1: Matiere, 2: Periode, 3: TypeNote, 4: int}|null
     */
    private function chargerCriteres(): ?array
    {
        $criteres = Session::get('note_criteres');
        if (! $criteres) {
            return null;
        }

        $classeAnnee = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->find($criteres['classe_annee_id'] ?? null);
        $matiere = Matiere::find($criteres['matiere_id'] ?? null);
        $periode = Periode::with('anneeScolaire')->find($criteres['periode_id'] ?? null);
        $typeNote = TypeNote::find($criteres['type_note_id'] ?? null);

        if (! $classeAnnee || ! $matiere || ! $periode || ! $typeNote) {
            Session::forget('note_criteres');

            return null;
        }

        $this->assertPeutSaisir($classeAnnee->id, $matiere->id);

        return [$classeAnnee, $matiere, $periode, $typeNote, (int) ($criteres['numero'] ?? 1)];
    }

    private function redirigerCriteresExpires()
    {
        return redirect()->route('admin.notes.create')
            ->with('error', 'Votre sélection a expiré. Veuillez choisir à nouveau la classe, la matière et la période.');
    }

    private function libelleClasse(ClasseAnnee $classeAnnee): string
    {
        return trim($classeAnnee->classe->niveau->nom.' '.$classeAnnee->classe->suffixe);
    }

    /** « Interrogation n°2 » quand le type autorise plusieurs occurrences par période. */
    private function libelleTypeNote(TypeNote $typeNote, int $numero): string
    {
        return $typeNote->libelle.(($typeNote->max_par_periode ?? 1) > 1 ? " n°{$numero}" : '');
    }

    /**
     * Enseignant auquel rattacher une note quand la matière n'en a pas dans la classe et que le compte
     * connecté n'est pas un enseignant : le premier enseignant enregistré dans l'établissement.
     */
    private function enseignantParDefaut(): ?int
    {
        return Enseignant::query()->orderBy('id')->value('id');
    }

    /**
     * Enseignant affiché sur la fiche : celui assigné au couple classe/matière, sinon l'enseignant
     * connecté. Jamais le nom d'un administrateur, d'un saisisseur ni de l'enseignant par défaut.
     */
    private function nomEnseignant(ClasseAnnee $classeAnnee, Matiere $matiere): string
    {
        $enseignantId = DB::table('classe_matieres')
            ->where('classe_annee_id', $classeAnnee->id)
            ->where('matiere_id', $matiere->id)
            ->value('enseignant_id');

        $enseignant = $enseignantId ? Enseignant::find($enseignantId) : Auth::user()->enseignant;

        return $enseignant ? trim($enseignant->prenom.' '.$enseignant->nom) : '';
    }

    private function nomFichier(string $prefixe, ClasseAnnee $classeAnnee, Matiere $matiere, Periode $periode, TypeNote $typeNote, int $numero, string $extension): string
    {
        $parties = [$prefixe, $this->libelleClasse($classeAnnee), $matiere->nom_matiere, $this->libelleTypeNote($typeNote, $numero), $periode->nom];

        return collect($parties)
            ->map(fn ($partie) => Str::of($partie)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_'))
            ->implode('_').'.'.$extension;
    }

    // ─────────────────────────────────────────────────────────────────
    // PREVIEW — GET + POST
    // ─────────────────────────────────────────────────────────────────

    public function preview(Request $request)
    {
        // ══════════════════════════════════════════════════════════════
        // GET — rechargement après import (Excel ou IA)
        // ══════════════════════════════════════════════════════════════
        if ($request->isMethod('get')) {
            $criteres = $this->chargerCriteres();
            if (! $criteres) {
                return $this->redirigerCriteresExpires();
            }

            [$classeAnnee, $matiere, $periode, $typeNote, $numero] = $criteres;
            $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
                ->orderBy('nom')->orderBy('prenom')->get();

            // ── Priorité 1 : notes importées (Excel/IA) en session. Une ligne sans note (case vide,
            //    note non lue) n'apporte rien : elle ne doit pas masquer une note déjà enregistrée. ──
            $importedData = Session::get('imported_data', []);
            $importedNotes = [];
            foreach ($importedData as $item) {
                if ($item['eleve_id'] && empty($item['errors']) && $item['note'] !== null && $item['note'] !== '') {
                    $importedNotes[$item['eleve_id']] = [
                        'valeur' => $item['note'],
                        'commentaire' => $item['commentaire'],
                    ];
                }
            }

            // Notes importées affichées mais pas encore enregistrées : absentes de la fiche PDF
            $notesNonEnregistrees = ! empty($importedNotes);

            // ── Priorité 2 : pour les autres élèves, les notes déjà enregistrées ──
            $notesExistantes = Note::where('matiere_id', $matiere->id)
                ->where('periode_id', $periode->id)
                ->where('type_note_id', $typeNote->id)
                ->where('numero', $numero)
                ->whereIn('eleve_id', $eleves->pluck('id'))
                ->get()
                ->keyBy('eleve_id');

            foreach ($notesExistantes as $eleveId => $note) {
                $importedNotes[$eleveId] ??= [
                    'valeur' => $note->valeur,
                    'commentaire' => $note->commentaire ?? '',
                ];
            }

            $modeModification = collect($importedNotes)->isNotEmpty();

            return view('back.pages.notes.preview', compact(
                'classeAnnee', 'matiere', 'periode', 'typeNote', 'numero',
                'eleves', 'importedNotes', 'modeModification', 'notesNonEnregistrees'
            ));
        }

        // ══════════════════════════════════════════════════════════════
        // POST — soumission du formulaire critères
        // ══════════════════════════════════════════════════════════════
        $request->validate([
            'classe_annee_id' => 'required|exists:classe_annees,id',
            'matiere_id' => 'required|exists:matieres,id',
            'periode_id' => 'required|exists:periodes,id',
            'type_note_id' => 'required|exists:type_notes,id',
            'numero' => 'nullable|integer|min:1',
        ]);

        $this->assertPeutSaisir((int) $request->classe_annee_id, (int) $request->matiere_id);

        $classeAnnee = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->find($request->classe_annee_id);
        $matiere = Matiere::find($request->matiere_id);
        $periode = Periode::with('anneeScolaire')->find($request->periode_id);
        $typeNote = TypeNote::find($request->type_note_id);
        $numero = (int) $request->input('numero', 1);

        if ($numero < 1 || $numero > ($typeNote->max_par_periode ?? 1)) {
            return back()->with('error', "N° d'occurrence invalide pour ce type de note (max {$typeNote->max_par_periode}).")->withInput();
        }

        // Vérifier que la matière est enseignée dans cette classe
        $coefficient = DB::table('classe_matieres')
            ->where('classe_annee_id', $classeAnnee->id)
            ->where('matiere_id', $matiere->id)
            ->first();
        if (! $coefficient) {
            return back()->with('error', 'Cette matière n\'est pas enseignée dans cette classe.');
        }

        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')
            ->get();

        // ── VÉRIFICATION BDD : notes existantes pour ces critères ────
        $notesExistantes = Note::where('matiere_id', $matiere->id)
            ->where('periode_id', $periode->id)
            ->where('type_note_id', $typeNote->id)
            ->where('numero', $numero)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->get()
            ->keyBy('eleve_id');

        // Pré-remplir le formulaire avec les notes existantes
        $importedNotes = [];
        foreach ($notesExistantes as $eleveId => $note) {
            $importedNotes[$eleveId] = [
                'valeur' => $note->valeur,
                'commentaire' => $note->commentaire ?? '',
            ];
        }

        $modeModification = $notesExistantes->isNotEmpty();
        $notesNonEnregistrees = false;

        // Sauvegarder les critères en session (pour export PDF/template)
        Session::put('note_criteres', array_merge(
            $request->only(['classe_annee_id', 'matiere_id', 'periode_id', 'type_note_id']),
            ['numero' => $numero]
        ));
        // Effacer les données d'import précédentes
        Session::forget(['imported_data', 'ia_meta']);

        return view('back.pages.notes.preview', compact(
            'classeAnnee', 'matiere', 'periode', 'typeNote', 'numero',
            'eleves', 'importedNotes', 'modeModification', 'notesNonEnregistrees'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT FICHE VIERGE PDF
    // ─────────────────────────────────────────────────────────────────

    public function exportTemplate(NotesPdfService $pdfService)
    {
        $criteres = $this->chargerCriteres();
        if (! $criteres) {
            return $this->redirigerCriteresExpires();
        }

        [$classeAnnee, $matiere, $periode, $typeNote, $numero] = $criteres;

        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'matricule', 'nom', 'prenom']);

        $notesVides = $eleves->values()->map(fn ($e, $i) => [
            'num' => $i + 1,
            'matricule' => $e->matricule,
            'nom' => $e->nom,
            'prenom' => $e->prenom,
            'note' => null,
            'commentaire' => '',
        ])->toArray();

        $pdfPath = $pdfService->generateVierge([
            'classe' => $this->libelleClasse($classeAnnee),
            'annee_scolaire' => $classeAnnee->anneeScolaire->libelle,
            'matiere' => $matiere->nom_matiere,
            'periode' => $periode->nom,
            'type_note' => $this->libelleTypeNote($typeNote, $numero),
            'enseignant' => $this->nomEnseignant($classeAnnee, $matiere),
            'notes' => $notesVides,
        ]);

        $filename = $this->nomFichier('FicheVierge', $classeAnnee, $matiere, $periode, $typeNote, $numero, 'pdf');

        return response()->download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT FICHE VIERGE EXCEL (template import)
    // ─────────────────────────────────────────────────────────────────

    public function exportTemplateExcel()
    {
        $criteres = $this->chargerCriteres();
        if (! $criteres) {
            return $this->redirigerCriteresExpires();
        }

        [$classeAnnee, $matiere, $periode, $typeNote, $numero] = $criteres;

        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'matricule', 'nom', 'prenom']);

        // ── Styles réutilisables ─────────────────────────────────────
        $borderAll = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'BDBDBD'],
                ],
            ],
        ];
        $styleTitre = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D47A1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $styleColHeader = array_merge([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1565C0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ], $borderAll);
        $styleExemple = array_merge([
            'font' => ['italic' => true, 'color' => ['rgb' => '9E9E9E'], 'size' => 9, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F2FD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ], $borderAll);
        $styleLocked = array_merge([
            'font' => ['name' => 'Arial', 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ], $borderAll);
        $styleSaisie = array_merge([
            'font' => ['name' => 'Arial', 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ], $borderAll);
        $styleWarning = [
            'font' => ['italic' => true, 'color' => ['rgb' => 'E65100'], 'size' => 8, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF9C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        // ── Feuille 1 : Import Notes ─────────────────────────────────
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Notes');

        // Titre
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'FICHE DE SAISIE DES NOTES — IMPORTATION');
        $sheet->getStyle('A1')->applyFromArray($styleTitre);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Métadonnées (lignes 2-5)
        $labelStyle = [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $valueStyle = [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 9, 'color' => ['rgb' => '1565C0']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F2FD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $meta = [
            2 => ['Classe - Année scolaire :', $this->libelleClasse($classeAnnee).' — '.$classeAnnee->anneeScolaire->libelle],
            3 => ['Matière :', $matiere->nom_matiere],
            4 => ['Période :', $periode->nom],
            5 => ['Type de note :', $this->libelleTypeNote($typeNote, $numero)],
        ];
        foreach ($meta as $row => [$label, $value]) {
            $sheet->setCellValue("B{$row}", $label);
            $sheet->getStyle("B{$row}")->applyFromArray($labelStyle);
            $sheet->mergeCells("C{$row}:E{$row}");
            $sheet->setCellValue("C{$row}", $value);
            $sheet->getStyle("C{$row}")->applyFromArray($valueStyle);
            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        // Séparateur ligne 6
        $sheet->getRowDimension(6)->setRowHeight(6);

        // En-têtes colonnes (ligne 7)
        $cols = ['A', 'B', 'C', 'D', 'E'];
        $headers = ['Matricule', 'Nom', 'Prénom', 'Note (/20)', 'Commentaire'];
        foreach ($headers as $i => $h) {
            $coord = $cols[$i].'7';
            $sheet->setCellValue($coord, $h);
            $sheet->getStyle($coord)->applyFromArray($styleColHeader);
        }
        $sheet->getRowDimension(7)->setRowHeight(22);

        // Ligne exemple (ligne 8)
        $example = ['EX-001', 'EXEMPLE', 'Prénom', 15.5, 'Bon travail'];
        foreach ($example as $i => $val) {
            $coord = $cols[$i].'8';
            $sheet->setCellValue($coord, $val);
            $sheet->getStyle($coord)->applyFromArray($styleExemple);
        }
        $sheet->getRowDimension(8)->setRowHeight(18);

        // Données élèves (à partir de la ligne 9)
        foreach ($eleves as $i => $eleve) {
            $row = $i + 9;
            $sheet->setCellValue("A{$row}", $eleve->matricule);
            $sheet->setCellValue("B{$row}", $eleve->nom);
            $sheet->setCellValue("C{$row}", $eleve->prenom);
            // Colonnes D et E vierges — à saisir par l'utilisateur

            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($styleLocked);
            $sheet->getStyle("D{$row}:E{$row}")->applyFromArray($styleSaisie);
            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        // Note de bas de page
        $lastRow = $eleves->count() + 9;
        $sheet->mergeCells("A{$lastRow}:E{$lastRow}");
        $sheet->setCellValue(
            "A{$lastRow}",
            '⚠  Colonnes A, B, C (Matricule / Nom / Prénom) : ne pas modifier. '
            .'Colonne D : valeur numérique entre 0 et 20. Laissez vide si absent.'
        );
        $sheet->getStyle("A{$lastRow}")->applyFromArray($styleWarning);
        $sheet->getRowDimension($lastRow)->setRowHeight(16);

        // Largeurs colonnes
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(13);
        $sheet->getColumnDimension('E')->setWidth(38);

        // Figer après ligne d'en-têtes + exemple
        $sheet->freezePane('A9');

        // ── Feuille 2 : Instructions ─────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Instructions');

        $sheet2->mergeCells('A1:B1');
        $sheet2->setCellValue('A1', "INSTRUCTIONS D'IMPORTATION");
        $sheet2->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D47A1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet2->getRowDimension(1)->setRowHeight(28);

        $lines = [
            3 => "1. Ne supprimez pas et ne modifiez pas la ligne exemple (ligne 8) — elle sera ignorée à l'import.",
            4 => '2. Remplissez uniquement les colonnes D (Note /20) et E (Commentaire).',
            5 => '3. Les colonnes A, B, C (Matricule, Nom, Prénom) sont pré-remplies — ne pas les modifier.',
            6 => '4. La note doit être un nombre entre 0 et 20, avec au max 2 décimales (ex : 14, 7.5, 0).',
            7 => "5. Laissez la cellule Note vide si l'élève est absent ou non évalué.",
            8 => "6. Sauvegardez le fichier au format .xlsx avant de l'importer dans le système.",
            10 => "Pour toute question, contactez l'administrateur du système scolaire.",
        ];
        foreach ($lines as $r => $txt) {
            $sheet2->setCellValue("A{$r}", $txt);
            $sheet2->getStyle("A{$r}")->getFont()->setName('Arial')->setSize(10);
            if ($r === 10) {
                $sheet2->getStyle("A{$r}")->getFont()->setItalic(true)->getColor()->setRGB('757575');
            }
            $sheet2->getRowDimension($r)->setRowHeight(18);
        }
        $sheet2->getColumnDimension('A')->setWidth(90);

        $spreadsheet->setActiveSheetIndex(0);

        // ── Envoi en téléchargement ───────────────────────────────────
        $filename = $this->nomFichier('Template_Notes', $classeAnnee, $matiere, $periode, $typeNote, $numero, 'xlsx');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // IMPORT EXCEL — prévisualisation
    // ─────────────────────────────────────────────────────────────────

    public function importPreview(Request $request)
    {
        $request->validate(['import_file' => 'required|mimes:xlsx,xls|max:2048']);

        $criteres = $this->chargerCriteres();
        if (! $criteres) {
            return $this->redirigerCriteresExpires();
        }

        [$classeAnnee, $matiere, $periode, $typeNote] = $criteres;

        try {
            $spreadsheet = IOFactory::load($request->file('import_file')->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.notes.preview')
                ->with('error', 'Ce fichier Excel est illisible. Utilisez le « Template Excel » téléchargé depuis cette page.');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Le template a un titre et des métadonnées au-dessus de l'en-tête « Matricule » (ligne 7) :
        // seules les lignes situées sous cet en-tête sont des élèves.
        $ligneEntete = collect($rows)->search(fn ($row) => strcasecmp(trim((string) ($row[0] ?? '')), 'Matricule') === 0);
        if ($ligneEntete !== false) {
            $rows = array_slice($rows, $ligneEntete + 1);
        }

        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->get(['id', 'matricule', 'nom', 'prenom'])
            ->keyBy('matricule');
        $importedData = [];

        foreach ($rows as $row) {
            $matricule = trim($row[0] ?? '');
            $note = trim(str_replace([',', ' '], ['.', ''], (string) ($row[3] ?? '')));
            $commentaire = trim($row[4] ?? '');

            // Ligne vide, ligne exemple du template ou consigne de bas de page (« ⚠ ... »)
            if ($matricule === '' || $matricule === 'EX-001' || str_starts_with($matricule, '⚠')) {
                continue;
            }

            $lineErrors = [];
            if (! isset($eleves[$matricule])) {
                $lineErrors[] = 'Matricule inconnu';
            }
            if ($note !== '') {
                if (! is_numeric($note) || (float) $note < 0 || (float) $note > 20) {
                    $lineErrors[] = 'Note invalide (0-20)';
                } else {
                    $note = round((float) $note, 2);
                }
            }

            $importedData[] = [
                'matricule' => $matricule,
                'nom' => $eleves[$matricule]->nom ?? '',
                'prenom' => $eleves[$matricule]->prenom ?? '',
                'note' => $note,
                'commentaire' => $commentaire,
                'errors' => $lineErrors,
                'eleve_id' => $eleves[$matricule]->id ?? null,
                'matched_by' => 'excel',
            ];
        }

        $hasErrors = collect($importedData)->filter(fn ($i) => ! empty($i['errors']))->isNotEmpty();
        Session::put('imported_data', $importedData);

        return view('back.pages.notes.import_preview', compact(
            'importedData', 'hasErrors', 'classeAnnee', 'matiere', 'periode', 'typeNote'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // IMPORT PAR IMAGE — IA Vision (Mistral)
    // ─────────────────────────────────────────────────────────────────

    public function importImage(Request $request, ImageNoteExtractorService $extractor)
    {
        // Une à cinq photos (une par page de la fiche). L'ancien champ unique « image_file » reste accepté.
        $photos = collect([$request->file('image_files'), $request->file('image_file')])
            ->flatten()->filter()->values()->all();

        validator(['image_files' => $photos], [
            'image_files' => 'required|array|max:5',
            'image_files.*' => 'bail|image|mimes:jpeg,jpg,png,webp|max:10240',
        ], [
            'image_files.*.uploaded' => "La photo n° :position n'a pas pu être envoyée : elle est sans doute trop lourde.",
            'image_files.*.max' => 'La photo n° :position est trop lourde (10 Mo au maximum).',
        ])->validate();

        $criteres = $this->chargerCriteres();
        if (! $criteres) {
            return $this->redirigerCriteresExpires();
        }

        [$classeAnnee, $matiere, $periode, $typeNote, $numero] = $criteres;

        // Élèves dans l'ordre de la fiche imprimée (bordereau de saisie des notes)
        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'matricule', 'nom', 'prenom'])
            ->toArray();

        $context = [
            'classe' => $this->libelleClasse($classeAnnee),
            'matiere' => $matiere->nom_matiere,
            'periode' => $periode->nom,
            'type_note' => $this->libelleTypeNote($typeNote, $numero),
            // Case du bordereau où s'écrit cette note : la photo est alors découpée case par case
            'case' => $typeNote->caseDuBordereau($numero),
            'eleves' => array_map(fn (array $eleve, int $i) => [
                'num' => $i + 1,
                'matricule' => $eleve['matricule'],
                'nom' => $eleve['nom'],
                'prenom' => $eleve['prenom'],
            ], $eleves, array_keys($eleves)),
        ];

        // ── Lecture des photos, l'une après l'autre, dans l'ordre d'envoi ──
        $dossier = storage_path('app'.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR.'notes-images');
        if (! is_dir($dossier)) {
            @mkdir($dossier, 0755, true);
        }

        $lectures = []; // n° de photo => résultat de la lecture
        $echecs = [];   // n° de photo => raison, en clair, pour laquelle elle n'a pas été lue

        foreach ($photos as $index => $photo) {
            $numeroPhoto = $index + 1;
            $nomFichier = uniqid('note_img_').'_'.$numeroPhoto.'.'.($photo->extension() ?: 'jpg');
            $chemin = $dossier.DIRECTORY_SEPARATOR.$nomFichier;

            try {
                $photo->move($dossier, $nomFichier);
                $resultat = $extractor->extractNotesFromImage($chemin, $context);
            } catch (\Throwable $e) {
                report($e);
                $resultat = ['success' => false, 'notes' => [], 'errors' => ['Une erreur est survenue pendant la lecture. Réessayez.']];
            } finally {
                // Le fichier temporaire est toujours supprimé, que la lecture ait réussi ou non
                if (is_file($chemin)) {
                    @unlink($chemin);
                }
            }

            if (! empty($resultat['success']) && ! empty($resultat['notes'])) {
                $lectures[$numeroPhoto] = $resultat;
            } else {
                $echecs[$numeroPhoto] = $resultat['errors'][0] ?? "Aucune note n'a été trouvée sur cette photo.";
            }
        }

        // Aucune photo lisible : retour à la saisie, avec la raison du premier échec
        if (empty($lectures)) {
            $constat = count($photos) > 1
                ? 'Aucune des '.count($photos)." photos n'a pu être lue"
                : "La photo n'a pas pu être lue";

            return back()->with('error', $constat.' : '.Str::lcfirst(reset($echecs)));
        }

        // ── Une ligne par élève : toutes les lignes lues, dans l'ordre des photos, rapprochées de la classe ──
        $lignesLues = $extractor->matchWithDatabase(
            array_merge(...array_column($lectures, 'notes')),
            $eleves
        )['rows'];

        [$lignes, $notesDifferentes] = $this->fusionnerLignesLues($lignesLues);

        // Toute la classe est affichée, dans l'ordre de la fiche : la note lue de chaque élève, ou une
        // case vide à saisir à la main. Les lignes lues qui ne désignent aucun élève viennent ensuite.
        $parEleve = collect($lignes)->whereNotNull('eleve_id')->keyBy('eleve_id');
        $importedData = array_map(fn (array $eleve) => $parEleve->get($eleve['id']) ?? [
            'matricule' => $eleve['matricule'],
            'nom' => $eleve['nom'],
            'prenom' => $eleve['prenom'],
            'note' => null,
            'commentaire' => '',
            'eleve_id' => $eleve['id'],
            'errors' => [],
            'matched_by' => 'ia_vision',
        ], $eleves);
        $nonReconnus = collect($lignes)->whereNull('eleve_id')->values()->all();
        $importedData = array_merge($importedData, $nonReconnus);

        // Avertissements : photos non lues, puis notes différentes lues pour un même élève
        $avertissements = [];
        foreach ($echecs as $numeroPhoto => $raison) {
            $avertissements[] = "Photo n° {$numeroPhoto} : non lue. {$raison}";
        }
        $avertissements = array_merge($avertissements, $notesDifferentes);

        $notesLues = $parEleve->whereNotNull('note')->count();

        Session::put('imported_data', $importedData);
        Session::put('ia_meta', [
            'photos' => count($lectures),
            'lues' => $notesLues,
            'effectif' => count($eleves),
            'unmatched' => count($nonReconnus),
            'warnings' => array_values(array_unique($avertissements)),
        ]);

        // Une ligne non reconnue ou une note non lue n'empêche pas d'appliquer les autres : seule
        // l'absence de toute note rattachée à un élève de la classe bloque.
        $hasErrors = $notesLues === 0;

        return view('back.pages.notes.import_preview', compact(
            'importedData', 'hasErrors', 'classeAnnee', 'matiere', 'periode', 'typeNote'
        ))->with('ia_mode', true);
    }

    /**
     * Réunit les lignes lues sur toutes les photos : une seule ligne par élève, la première note lisible
     * l'emporte, et deux notes différentes pour un même élève donnent un avertissement.
     * Une ligne dont l'élève n'est pas reconnu est gardée une seule fois et signalée dans « errors » :
     * elle n'est pas reprise dans le formulaire de saisie.
     *
     * @param  list<array>  $lignesLues  lignes rapprochées des élèves (« rows » de ImageNoteExtractorService::matchWithDatabase)
     * @return array{0: list<array>, 1: list<string>} lignes à afficher, avertissements « deux notes différentes »
     */
    private function fusionnerLignesLues(array $lignesLues): array
    {
        $lignes = [];
        $avertissements = [];
        $enClair = fn (float $note) => str_replace('.', ',', (string) $note);

        foreach ($lignesLues as $lue) {
            $cle = $lue['key'];
            $eleve = $lue['eleve'];

            if (! isset($lignes[$cle])) {
                // Élève reconnu : on affiche son identité telle qu'elle est en base, pas celle lue sur la photo
                // (matricule mal lu, nom absent ou incomplet)
                $lignes[$cle] = [
                    'matricule' => $eleve['matricule'] ?? $lue['matricule'],
                    'nom' => $eleve['nom'] ?? $lue['nom'],
                    'prenom' => $eleve['prenom'] ?? $lue['prenom'],
                    'note' => $lue['note'],
                    'commentaire' => $lue['commentaire'],
                    'eleve_id' => $eleve['id'] ?? null,
                    'errors' => $eleve ? [] : ['Élève non reconnu'],
                    'matched_by' => 'ia_vision',
                ];

                continue;
            }

            $dejaLue = $lignes[$cle]['note'];

            if ($dejaLue === null) {
                $lignes[$cle]['note'] = $lue['note'];
            } elseif ($eleve && $lue['note'] !== null && abs($lue['note'] - $dejaLue) >= 0.005) {
                $avertissements[] = trim($eleve['nom'].' '.$eleve['prenom'])
                    .' : deux notes différentes lues ('.$enClair($dejaLue).' et '.$enClair($lue['note']).'). Vérifiez.';
            }

            if ($lignes[$cle]['commentaire'] === '') {
                $lignes[$cle]['commentaire'] = $lue['commentaire'];
            }
        }

        return [array_values($lignes), array_values(array_unique($avertissements))];
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT PDF OFFICIEL CCPA
    // ─────────────────────────────────────────────────────────────────

    public function exportPdf(NotesPdfService $pdfService)
    {
        $criteres = $this->chargerCriteres();
        if (! $criteres) {
            return $this->redirigerCriteresExpires();
        }

        [$classeAnnee, $matiere, $periode, $typeNote, $numero] = $criteres;

        $eleves = Eleve::where('classe_annee_id', $classeAnnee->id)
            ->orderBy('nom')->orderBy('prenom')
            ->get();

        $notes = Note::where('matiere_id', $matiere->id)
            ->where('periode_id', $periode->id)
            ->where('type_note_id', $typeNote->id)
            ->where('numero', $numero)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->get()
            ->keyBy('eleve_id');

        $notesData = [];
        foreach ($eleves->values() as $i => $eleve) {
            $note = $notes->get($eleve->id);

            $notesData[] = [
                'num' => $i + 1,
                'matricule' => $eleve->matricule,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'note' => $note?->valeur ?? null,
                'commentaire' => $note?->commentaire ?? '',
            ];
        }

        $pdfPath = $pdfService->generate([
            'classe' => $this->libelleClasse($classeAnnee),
            'annee_scolaire' => $classeAnnee->anneeScolaire->libelle,
            'matiere' => $matiere->nom_matiere,
            'periode' => $periode->nom,
            'type_note' => $this->libelleTypeNote($typeNote, $numero),
            'enseignant' => $this->nomEnseignant($classeAnnee, $matiere),
            'notes' => $notesData,
        ]);

        $filename = $this->nomFichier('FicheNotes', $classeAnnee, $matiere, $periode, $typeNote, $numero, 'pdf');

        // Ouvert dans un nouvel onglet (target="_blank") : affichage direct, prêt à imprimer ou enregistrer
        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ])->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────────────────────────
    // STORE — enregistrement (création ou mise à jour)
    // ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'classe_annee_id' => 'required|exists:classe_annees,id',
            'matiere_id' => 'required|exists:matieres,id',
            'periode_id' => 'required|exists:periodes,id',
            'type_note_id' => 'required|exists:type_notes,id',
            'numero' => 'nullable|integer|min:1',
            'notes' => 'required|array',
            'notes.*.valeur' => 'nullable|numeric|min:0|max:20|regex:/^\d+(\.\d{1,2})?$/',
            'notes.*.commentaire' => 'nullable|string|max:255',
        ]);

        $this->assertPeutSaisir((int) $request->classe_annee_id, (int) $request->matiere_id);

        $matiere = Matiere::find($request->matiere_id);
        $periode = Periode::find($request->periode_id);
        $typeNote = TypeNote::find($request->type_note_id);
        $numero = (int) $request->input('numero', 1);

        if ($numero < 1 || $numero > ($typeNote->max_par_periode ?? 1)) {
            return back()->with('error', "N° d'occurrence invalide pour ce type de note (max {$typeNote->max_par_periode}).")->withInput();
        }

        // Enseignant de la note : celui attribué à la matière dans la classe, sinon celui du compte connecté,
        // sinon l'enseignant par défaut. L'administration saisit elle-même les notes : elle n'a pas à
        // attribuer un enseignant à chaque matière avant de pouvoir enregistrer.
        $enseignantId = DB::table('classe_matieres')
            ->where('classe_annee_id', $request->classe_annee_id)
            ->where('matiere_id', $request->matiere_id)
            ->value('enseignant_id') ?? Auth::user()->enseignant?->id ?? $this->enseignantParDefaut();

        if (! $enseignantId) {
            return back()->withInput()->with('error', "Les notes n'ont pas pu être enregistrées : aucun enseignant n'est encore enregistré dans l'établissement. Il en faut au moins un pour rattacher les notes.");
        }

        DB::beginTransaction();
        $created = 0;
        $updated = 0;

        try {
            foreach ($request->notes as $eleveId => $data) {
                if (! isset($data['valeur']) || $data['valeur'] === '') {
                    continue;
                }

                $existing = Note::where('eleve_id', $eleveId)
                    ->where('matiere_id', $matiere->id)
                    ->where('periode_id', $periode->id)
                    ->where('type_note_id', $typeNote->id)
                    ->where('numero', $numero)
                    ->first();

                $payload = [
                    'valeur' => $data['valeur'],
                    'commentaire' => $data['commentaire'] ?? null,
                    'enseignant_id' => $enseignantId,
                    'classe_annee_id' => $request->classe_annee_id,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    Note::create(array_merge($payload, [
                        'eleve_id' => $eleveId,
                        'matiere_id' => $matiere->id,
                        'periode_id' => $periode->id,
                        'type_note_id' => $typeNote->id,
                        'numero' => $numero,
                    ]));
                    $created++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', "Les notes n'ont pas pu être enregistrées. Veuillez réessayer.")->withInput();
        }

        // Notes déjà enregistrées : un échec d'envoi des notifications ne doit pas afficher d'erreur
        try {
            $this->notifyParents($request->notes, $matiere, $typeNote, app(Messaging::class));
        } catch (\Throwable $e) {
            report($e);
        }

        // On revient sur la fiche qui vient d'être enregistrée, pour pouvoir l'exporter en PDF
        Session::put('note_criteres', array_merge(
            $request->only(['classe_annee_id', 'matiere_id', 'periode_id', 'type_note_id']),
            ['numero' => $numero]
        ));
        Session::forget(['imported_data', 'ia_meta']);

        $msg = [];
        if ($created) {
            $msg[] = "$created note(s) créée(s)";
        }
        if ($updated) {
            $msg[] = "$updated note(s) mise(s) à jour";
        }

        return redirect()->route('admin.notes.preview')
            ->with('success', ($msg ? implode(', ', $msg) : 'Aucune note saisie').'. Vous pouvez maintenant exporter la fiche PDF.');
    }
}
