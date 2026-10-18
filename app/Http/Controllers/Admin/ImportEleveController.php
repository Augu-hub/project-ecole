<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Parents as ParentModel;
use App\Services\MatriculeService;
use App\Services\ParentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportEleveController extends Controller
{
    public function __construct(
        private ParentService $parentService,
        private MatriculeService $matriculeService,
    ) {}

    // =====================================================================
    // Structure du fichier Excel attendu :
    //   Ligne 1 : En-têtes → Matricule | Nom | Prénoms | Classe | N° GSM
    //   Ligne 2+ : Données élèves
    //
    // Remarque : Ce fichier ne contient PAS de colonnes parent.
    // Le parent est recherché en base via le N° GSM de l'élève.
    // S'il n'existe pas, il est créé automatiquement.
    // Le matricule est conservé tel quel (ex: 24-0138),
    // et le numéro est toujours préfixé avec "01" si non fourni.
    // =====================================================================

    /**
     * Formulaire d'upload
     */
    public function create()
    {
        $classesAnnees = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->deLAnneeActive()->get();

        return view('back.pages.eleves.import-create', compact('classesAnnees'));
    }

    /**
     * Prévisualisation des données du fichier Excel
     */
    public function preview(Request $request)
    {
        $request->validate([
            'classe_annee_id' => 'required|exists:classe_annees,id',
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
            'sheet_name' => 'required|string|max:100',
        ]);

        $classeAnnee = ClasseAnnee::with('classe.niveau', 'anneeScolaire')
            ->findOrFail($request->classe_annee_id);

        // Charger le fichier Excel
        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());

        // Récupérer la feuille par son nom
        $worksheet = $spreadsheet->getSheetByName($request->sheet_name);
        if (! $worksheet) {
            return redirect()->back()->withErrors([
                'sheet_name' => "L'onglet '{$request->sheet_name}' n'existe pas dans le fichier.",
            ]);
        }

        // Lire les en-têtes (ligne 1)
        $rawHeaders = $worksheet->rangeToArray(
            'A1:'.$worksheet->getHighestColumn().'1',
            null, true, false
        )[0];
        $headers = array_map('trim', $rawHeaders);

        // Mapping des colonnes selon la structure réelle du fichier
        // Colonnes : Matricule | Nom | Prénoms | Classe | N° GSM
        // Les variantes couvrent : accents, majuscules/minuscules, espaces, caractères spéciaux
        $columnMapping = [
            'matricule' => ['matricule'],
            'nom' => ['nom'],
            'prenom' => ['prenoms', 'prenom'],   // après normalisation sans accents
            'classe' => ['classe'],
            'tel_eleve' => ['n gsm', 'ngsm', 'gsm', 'telephone', 'tel'],
        ];

        /**
         * Normalise un libellé pour la comparaison :
         * - Supprime les accents (é→e, è→e, ê→e, °→ …)
         * - Met en minuscules
         * - Supprime les caractères non alphanumériques (°, ., espace) sauf espace simple
         */
        $normaliser = function (string $s): string {
            $s = mb_strtolower(trim($s), 'UTF-8');
            // Translittération des caractères accentués
            $from = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç', 'œ', 'æ'];
            $to = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c', 'oe', 'ae'];
            $s = str_replace($from, $to, $s);
            // Supprimer tout sauf lettres, chiffres et espaces
            $s = preg_replace('/[^a-z0-9 ]/', '', $s);

            // Réduire les espaces multiples
            return preg_replace('/\s+/', ' ', trim($s));
        };

        // Normaliser les en-têtes du fichier
        $headersNorm = array_map($normaliser, $headers);
        $colIndexes = [];

        foreach ($columnMapping as $key => $variants) {
            $colIndexes[$key] = null;
            foreach ($variants as $label) {
                $idx = array_search($label, $headersNorm);
                if ($idx !== false) {
                    $colIndexes[$key] = $idx;
                    break;
                }
            }
        }

        // Récupérer toutes les lignes (sans la ligne d'en-tête)
        $allRows = $worksheet->toArray();
        array_shift($allRows); // Supprimer ligne 1 (en-têtes)

        $data = [];
        $errors = [];

        foreach ($allRows as $index => $row) {
            // Ignorer les lignes entièrement vides
            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $lineNumber = $index + 2; // +2 car ligne 1 = en-têtes

            // Extraire les valeurs
            $matricule = $colIndexes['matricule'] !== null
                ? trim((string) ($row[$colIndexes['matricule']] ?? ''))
                : '';
            $nom = $colIndexes['nom'] !== null
                ? trim((string) ($row[$colIndexes['nom']] ?? ''))
                : '';
            $prenom = $colIndexes['prenom'] !== null
                ? trim((string) ($row[$colIndexes['prenom']] ?? ''))
                : '';
            $classe = $colIndexes['classe'] !== null
                ? trim((string) ($row[$colIndexes['classe']] ?? ''))
                : '';
            $telEleve = $colIndexes['tel_eleve'] !== null
                ? $this->normaliserTelephone((string) ($row[$colIndexes['tel_eleve']] ?? ''))
                : '';

            $rowErrors = [];

            // Validations obligatoires
            if (empty($nom)) {
                $rowErrors[] = 'Nom manquant';
            }
            if (empty($prenom)) {
                $rowErrors[] = 'Prénom manquant';
            }

            // Validation téléphone : si présent, vérifier le format
            // Téléphone anormal : avertissement non bloquant
            // La ligne est quand même importée, sans parent lié
            $telAnormal = false;
            if (! empty($telEleve) && ! $this->estTelephoneValide($telEleve)) {
                $rowErrors[] = "⚠ N° GSM anormal conservé tel quel : '$telEleve'";
                $telAnormal = true;
                // On NE vide PAS $telEleve : il sera stocké tel quel sur l'élève
            }

            // Erreurs bloquantes (nom/prénom manquants) → ignorer la ligne
            if (! empty($rowErrors) && (empty($nom) || empty($prenom))) {
                $errors[$lineNumber] = $rowErrors;

                continue;
            }

            // Erreurs non bloquantes → on les signale mais on continue
            if (! empty($rowErrors)) {
                $errors[$lineNumber] = $rowErrors;
            }

            // ------------------------------------------------------------------
            // Prévisualisation SEULEMENT : on vérifie si le parent existe déjà.
            // La création du parent se fait dans store(), jamais ici.
            // Cela évite les doublons si on prévisualise plusieurs fois.
            // ------------------------------------------------------------------
            $parentId = null;
            $parentStatus = 'non renseigné';

            if (! empty($telEleve) && ! $telAnormal) {
                $parent = ParentModel::where('telephone', $telEleve)->first();
                if ($parent) {
                    $parentId = $parent->id;
                    $parentStatus = '✓ Existant : '.$parent->nom.' '.$parent->prenom;
                } else {
                    // Sera créé lors de l'import final (store)
                    $parentStatus = '+ À créer automatiquement';
                }
            } elseif ($telAnormal) {
                $parentStatus = '⚠ Non lié (N° GSM anormal)';
            }

            // ------------------------------------------------------------------
            // Gestion du matricule
            // Format du fichier : "24-0138" → conservé tel quel
            // Si absent : génération automatique avec "01" dans le numéro
            // ------------------------------------------------------------------
            $matriculeWarning = null;

            if (empty($matricule)) {
                $matricule = $this->matriculeService->genererPour($classeAnnee, count($data));
                $matriculeWarning = "Matricule généré : $matricule";
            } else {
                // Formater en s'assurant que le numéro contient "01"
                $matricule = $this->matriculeService->formater($matricule);

                // Vérifier doublon en base
                if (Eleve::where('matricule', $matricule)->exists()) {
                    $original = $matricule;
                    $matricule = $this->matriculeService->genererUnique($matricule);
                    $matriculeWarning = "⚠ Matricule '$original' doublon → remplacé par '$matricule'";
                }
            }

            if ($matriculeWarning) {
                $errors[$lineNumber][] = $matriculeWarning;
            }

            // Ligne valide → prête pour l'import
            $data[] = [
                'index' => $index,
                'line_number' => $lineNumber,
                'matricule' => $matricule,
                'nom' => $nom,
                'prenom' => $prenom,
                'classe_excel' => $classe,
                'tel_eleve' => $telEleve,
                'parent_status' => $parentStatus,
                // Champs internes (non affichés directement)
                '_parent_id' => $parentId,
                '_classe_annee_id' => $classeAnnee->id,
            ];
        }

        // Stocker en session pour l'étape d'import final
        Session::put('import_eleves_data', $data);
        Session::put('import_eleves_errors', $errors);
        Session::put('import_eleves_classe_annee', $classeAnnee->id);

        return view('back.pages.eleves.import-preview', compact('data', 'errors', 'classeAnnee'));
    }

    /**
     * Importe les lignes sélectionnées (après prévisualisation)
     */
    public function store(Request $request)
    {
        $selectedIndices = $request->input('selected', []);
        $data = Session::get('import_eleves_data', []);

        if (empty($selectedIndices)) {
            return redirect()->back()->with('error', 'Aucune ligne sélectionnée.');
        }

        $imported = 0;
        $failed = [];

        foreach ($data as $item) {
            if (! in_array($item['index'], $selectedIndices)) {
                continue;
            }

            try {
                // Résoudre le parent : récupérer l'existant OU créer s'il n'existe pas encore
                // On utilise firstOrCreate pour éviter tout doublon même en cas de double appel
                $parentId = $item['_parent_id'];

                if (empty($parentId) && ! empty($item['tel_eleve'])) {
                    $parent = $this->parentService->rechercherOuCreerParent(
                        $item['tel_eleve'],
                        $item['nom'],
                        $item['prenom']
                    );
                    $parentId = $parent->id;
                }

                Eleve::create([
                    'matricule' => $item['matricule'],
                    'nom' => $item['nom'],
                    'prenom' => $item['prenom'],
                    'sexe' => $item['sexe'] ?? null,
                    'date_naissance' => $item['date_naissance'] ?? null,
                    'photo' => null,
                    'classe_annee_id' => $item['_classe_annee_id'],
                    'parent_id' => $parentId,
                    'telephone' => $item['tel_eleve'] ?: null,
                    'date_inscription' => now()->toDateString(),
                    'statut' => 'actif',
                ]);
                $imported++;
            } catch (\Exception $e) {
                report($e);
                $failed[] = $item['matricule'].' : enregistrement impossible (matricule déjà utilisé ou données invalides)';
            }
        }

        // Nettoyer la session
        Session::forget(['import_eleves_data', 'import_eleves_errors', 'import_eleves_classe_annee']);

        $message = "$imported élève(s) importé(s) avec succès.";
        if (! empty($failed)) {
            $message .= ' Échecs : '.implode('; ', $failed);
        }

        return redirect()->route('admin.eleves.import.create')->with('success', $message);
    }

    // =========================================================================
    // MÉTHODES PRIVÉES
    // =========================================================================

    /**
     * Normalise un numéro de téléphone.
     *
     * Règles appliquées :
     *  - Supprime espaces, tirets, parenthèses, points
     *  - 8 chiffres (format local)       → ajoute "01" devant  → 0197123456
     *  - 11 chiffres commençant par 229  → insère "01" après 229 → 22901XXXXXXXX
     *  - 10 chiffres commençant par "01" → déjà normalisé
     *  - 13 chiffres commençant par "22901" → déjà normalisé
     */
    private function normaliserTelephone(string $tel): string
    {
        $tel = preg_replace('/[^0-9]/', '', trim($tel));

        if (empty($tel)) {
            return '';
        }

        $len = strlen($tel);

        // 8 chiffres → format local → ajouter "01"
        if ($len === 8) {
            return '01'.$tel;
        }

        // 10 chiffres commençant par "01" → déjà normalisé
        if ($len === 10 && str_starts_with($tel, '01')) {
            return $tel;
        }

        // 11 chiffres avec indicatif 229 → insérer "01" après l'indicatif
        if ($len === 11 && str_starts_with($tel, '229')) {
            return '22901'.substr($tel, 3);
        }

        // 13 chiffres déjà normalisés
        if ($len === 13 && str_starts_with($tel, '22901')) {
            return $tel;
        }

        // Autres cas : retourner tel quel (signalé anomal mais pas bloquant)
        return $tel;
    }

    /**
     * Vérifie si un numéro est valide.
     * Formats acceptés : 01XXXXXXXX (10) ou 22901XXXXXXXX (13).
     * Un téléphone vide est considéré valide (pas de parent lié, pas d'erreur bloquante).
     * Les formats anormaux remontent en avertissement mais ne bloquent PAS l'import.
     */
    private function estTelephoneValide(string $tel): bool
    {
        if (empty($tel)) {
            return true;
        }

        return preg_match('/^(01\d{8}|22901\d{8})$/', $tel) === 1;
    }
}
