<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Services\CantineTarifService;
use App\Services\PeriodeService;
use Illuminate\Http\Request;

class AnneeScolaireController extends Controller
{
    public function __construct(
        private CantineTarifService $cantineTarifService,
        private PeriodeService $periodeService,
    ) {}

    /**
     * Affiche la liste des années scolaires.
     */
    public function index()
    {
        $annees = AnneeScolaire::orderBy('date_debut', 'desc')->paginate(10);

        return view('back.pages.annees-scolaires.index', compact('annees'));
    }

    /**
     * Affiche le formulaire de création.
     */
    public function create()
    {
        return view('back.pages.annees-scolaires.create');
    }

    /**
     * Enregistre une nouvelle année scolaire.
     */
    public function store(Request $request)
    {
        $request->validate([
            'libelle' => 'required|string|max:9|unique:annee_scolaires',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'est_active' => 'sometimes|boolean',
        ]);

        $data = $request->all();
        $data['est_active'] = $request->has('est_active'); // car checkbox non coché = absent

        $annee = AnneeScolaire::create($data);

        // Initialise les tarifs cantine (repris de l'année précédente, ajustables ensuite).
        $this->cantineTarifService->creerTarifsParDefaut($annee);

        // Initialise les 3 trimestres, nécessaires au cahier de notes/bulletins.
        $this->periodeService->creerPeriodesParDefaut($annee);

        return redirect()->route('admin.annees-scolaires.index')
            ->with('success', 'Année scolaire créée avec succès. Tarifs cantine et trimestres initialisés (modifiables dans Cantine > Tarifs / Pédagogie > Périodes).');
    }

    /**
     * Affiche le formulaire d'édition.
     */
    public function edit(AnneeScolaire $anneeScolaire)
    {
        return view('back.pages.annees-scolaires.edit', compact('anneeScolaire'));
    }

    /**
     * Met à jour l'année scolaire.
     */
    public function update(Request $request, AnneeScolaire $anneeScolaire)
    {
        $request->validate([
            'libelle' => 'required|string|max:9|unique:annee_scolaires,libelle,'.$anneeScolaire->id,
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'est_active' => 'sometimes|boolean',
        ]);

        $data = $request->all();
        $data['est_active'] = $request->has('est_active');

        $anneeScolaire->update($data);

        return redirect()->route('admin.annees-scolaires.index')
            ->with('success', 'Année scolaire mise à jour.');
    }

    /**
     * Supprime une année scolaire (si non active).
     */
    public function destroy(AnneeScolaire $anneeScolaire)
    {
        try {
            $anneeScolaire->delete();

            return redirect()->route('admin.annees-scolaires.index')
                ->with('success', 'Année scolaire supprimée.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->route('admin.annees-scolaires.index')
                ->with('error', 'Suppression impossible : cette année scolaire est encore utilisée (classes, périodes, élèves ou paiements).');
        }
    }
}
