<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Fichier;
use App\Models\FraisDossier;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\Reinscription;
use App\Models\Uniforme;
use App\Models\UniformeVente;
use App\Services\MatriculeService;
use App\Services\ParentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EleveController extends Controller
{
    public function __construct(
        private ParentService $parentService,
        private MatriculeService $matriculeService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $anneeActive = AnneeScolaire::active();

        if (! $anneeActive) {
            return redirect()->back()->with('error', 'Aucune année scolaire active trouvée.');
        }

        // Consultation d'une année antérieure sans jamais changer l'année active : le sélecteur
        // par défaut sur l'année active, mais reste modifiable indépendamment.
        $anneesScolaires = AnneeScolaire::orderByDesc('date_debut')->get();
        $anneeSelectionneeId = $request->filled('annee_scolaire_id') ? (int) $request->input('annee_scolaire_id') : $anneeActive->id;
        $anneeSelectionnee = $anneesScolaires->firstWhere('id', $anneeSelectionneeId) ?? $anneeActive;

        $classes = ClasseAnnee::with('classe.niveau')
            ->where('annee_scolaire_id', $anneeSelectionnee->id)
            ->get();

        $selectedClasseId = $request->input('classe_annee_id');
        $eleves = collect();
        $fraisDossier = null;

        if ($selectedClasseId) {
            $eleves = Eleve::with('parentPrincipal')
                ->where('classe_annee_id', $selectedClasseId)
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get();

            $fraisDossier = FraisDossier::where('classe_annee_id', $selectedClasseId)->first();

            if ($fraisDossier) {
                $totalPayeParEleve = Paiement::where('payable_type', FraisDossier::class)
                    ->where('payable_id', $fraisDossier->id)
                    ->whereIn('eleve_id', $eleves->pluck('id'))
                    ->selectRaw('eleve_id, SUM(montant) as total')
                    ->groupBy('eleve_id')->pluck('total', 'eleve_id');

                $eleves->each(function ($e) use ($totalPayeParEleve, $fraisDossier) {
                    $e->frais_dossier_en_regle = ($totalPayeParEleve[$e->id] ?? 0) >= $fraisDossier->montant;
                });

                $filtre = $request->input('frais_dossier');
                if ($filtre === 'en_regle') {
                    $eleves = $eleves->filter(fn ($e) => $e->frais_dossier_en_regle)->values();
                } elseif ($filtre === 'non_regle') {
                    $eleves = $eleves->filter(fn ($e) => ! $e->frais_dossier_en_regle)->values();
                }
            }
        }

        return view('back.pages.eleves.index', compact(
            'anneeActive', 'anneesScolaires', 'anneeSelectionnee', 'classes', 'selectedClasseId', 'eleves', 'fraisDossier'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $anneeActive = AnneeScolaire::activeOrFail();
        $classes = $this->classesAnneesDisponibles($anneeActive->id);
        $parents = Parents::orderBy('nom')->get();

        return view('back.pages.eleves.create', compact('anneeActive', 'classes', 'parents'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $anneeActive = AnneeScolaire::activeOrFail();

        $validated = $this->validateEleve($request);

        $parent = $this->resoudreParent($request, $validated);

        $matricule = $validated['matricule'] ?? null;
        $classeAnnee = ClasseAnnee::findOrFail($validated['classe_annee_id']);
        if ($matricule) {
            $matricule = $this->matriculeService->formater($matricule);
        } else {
            $matricule = $this->matriculeService->genererPour($classeAnnee);
        }

        $eleve = Eleve::create([
            'matricule' => $matricule,
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'sexe' => $validated['sexe'] ?? null,
            'date_naissance' => $validated['date_naissance'] ?? null,
            'classe_annee_id' => $validated['classe_annee_id'],
            'parent_id' => $parent->id,
            'date_inscription' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->enregistrerFichiers($request, $eleve);

        Reinscription::create([
            'eleve_id' => $eleve->id,
            'annee_scolaire_id' => $anneeActive->id,
            'classe_annee_precedente_id' => null,
            'classe_annee_id' => $eleve->classe_annee_id,
            'type' => 'nouveau',
            'decide_par_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.eleves.index', ['classe_annee_id' => $eleve->classe_annee_id])
            ->with('success', 'Élève inscrit avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Eleve $eleve)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Eleve $eleve)
    {
        $eleve->load('parentPrincipal', 'fichiers', 'classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire', 'certificatsScolarite');

        $anneeActive = AnneeScolaire::activeOrFail();
        $classes = $this->classesAnneesDisponibles($anneeActive->id);

        // Si l'élève est dans une classe d'une autre année (déjà réinscrit ailleurs, etc.),
        // on l'ajoute à la liste pour ne pas casser l'affichage du select.
        if ($eleve->classeAnnee && ! $classes->contains('id', $eleve->classeAnnee->id)) {
            $classes->push($eleve->classeAnnee);
        }

        $parents = Parents::orderBy('nom')->get();
        $estSecondaire = $eleve->classeAnnee && $eleve->classeAnnee->classe->niveau->cycle === 'secondaire';

        $offresCantine = \App\Models\CantineOffre::where('annee_scolaire_id', $anneeActive->id)
            ->orderBy('mois')->get();
        $totalPayeParOffre = Paiement::where('payable_type', \App\Models\CantineOffre::class)
            ->where('eleve_id', $eleve->id)
            ->whereIn('payable_id', $offresCantine->pluck('id'))
            ->selectRaw('payable_id, SUM(montant) as total')
            ->groupBy('payable_id')
            ->pluck('total', 'payable_id');

        $uniformesCatalogue = Uniforme::orderBy('type')->orderBy('taille')->get();
        $ventesUniformes = UniformeVente::with('uniforme')
            ->where('eleve_id', $eleve->id)
            ->orderByDesc('date_vente')
            ->get();

        return view('back.pages.eleves.edit', compact(
            'eleve', 'anneeActive', 'classes', 'parents', 'estSecondaire', 'offresCantine', 'totalPayeParOffre',
            'uniformesCatalogue', 'ventesUniformes'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Eleve $eleve)
    {
        $anneeActive = AnneeScolaire::activeOrFail();

        $validated = $this->validateEleve($request, $eleve);

        $parent = $this->resoudreParent($request, $validated);

        $eleve->update([
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'sexe' => $validated['sexe'] ?? null,
            'date_naissance' => $validated['date_naissance'] ?? null,
            'classe_annee_id' => $validated['classe_annee_id'],
            'parent_id' => $parent->id,
        ]);

        $this->enregistrerFichiers($request, $eleve);

        return redirect()->route('admin.eleves.edit', $eleve)->with('success', 'Dossier élève mis à jour.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Suppression "douce" uniquement (passage en statut inactif) : une suppression physique
     * casserait les notes/paiements/fichiers déjà liés à cet élève.
     */
    public function destroy(Eleve $eleve)
    {
        $eleve->update(['statut' => 'inactif']);

        return redirect()->route('admin.eleves.index', ['classe_annee_id' => $eleve->classe_annee_id])
            ->with('success', 'Élève marqué comme inactif.');
    }

    private function classesAnneesDisponibles(int $anneeScolaireId)
    {
        return ClasseAnnee::with('classe.niveau', 'anneeScolaire')
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->get()
            ->sortBy(fn ($ca) => $ca->classe->niveau->ordre)
            ->values();
    }

    private function validateEleve(Request $request, ?Eleve $eleve = null): array
    {
        return $request->validate([
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'sexe' => 'nullable|in:M,F',
            'date_naissance' => 'nullable|date',
            'classe_annee_id' => ['required', 'exists:classe_annees,id'],
            'matricule' => [
                'nullable', 'string', 'max:20',
                Rule::unique('eleves', 'matricule')->ignore($eleve?->id),
            ],
            'parent_id' => 'nullable|exists:parents,id',
            'parent_nom' => 'required_without:parent_id|nullable|string|max:50',
            'parent_prenom' => 'required_without:parent_id|nullable|string|max:50',
            'parent_telephone' => 'required_without:parent_id|nullable|string|max:20',
            'fichiers.*' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
            'fichiers_libelles.*' => 'nullable|string|max:150',
        ]);
    }

    private function resoudreParent(Request $request, array $validated): Parents
    {
        if (! empty($validated['parent_id'])) {
            return Parents::findOrFail($validated['parent_id']);
        }

        return $this->parentService->rechercherOuCreerParent(
            $validated['parent_telephone'],
            $validated['parent_nom'],
            $validated['parent_prenom'],
        );
    }

    private function enregistrerFichiers(Request $request, Eleve $eleve): void
    {
        if (! $request->hasFile('fichiers')) {
            return;
        }

        $libelles = $request->input('fichiers_libelles', []);

        foreach ($request->file('fichiers') as $index => $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('eleves/'.$eleve->id, 'documents_eleves');

            Fichier::create([
                'nom_fichier' => $file->getClientOriginalName(),
                'chemin_fichier' => $path,
                'type_fichier' => $file->getClientOriginalExtension(),
                'taille' => $file->getSize(),
                'user_id' => $request->user()->id,
                'eleve_id' => $eleve->id,
                'libelle' => $libelles[$index] ?? null,
            ]);
        }
    }
}
