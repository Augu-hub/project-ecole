<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\FraisDossier;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FraisDossierController extends Controller
{
    public function index()
    {
        $fraisDossiers = FraisDossier::with('classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire')->paginate(15);

        return view('back.pages.frais-dossiers.index', compact('fraisDossiers'));
    }

    public function create()
    {
        $classesAnnees = $this->classesAnneesSecondaire();

        return view('back.pages.frais-dossiers.create', compact('classesAnnees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'classe_annee_id' => ['required', 'exists:classe_annees,id', 'unique:frais_dossiers,classe_annee_id'],
            'montant' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        FraisDossier::create($request->only(['classe_annee_id', 'montant', 'description']));

        return redirect()->route('admin.frais-dossiers.index')->with('success', 'Frais de dossier créé avec succès.');
    }

    public function edit(FraisDossier $fraisDossier)
    {
        $fraisDossier->load('classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire');
        $classesAnnees = $this->classesAnneesSecondaire($fraisDossier->classe_annee_id);

        $eleves = Eleve::with('parentPrincipal')
            ->where('classe_annee_id', $fraisDossier->classe_annee_id)
            ->orderBy('nom')->orderBy('prenom')->get();

        $totalPayeParEleve = Paiement::where('payable_type', FraisDossier::class)
            ->where('payable_id', $fraisDossier->id)
            ->selectRaw('eleve_id, SUM(montant) as total')
            ->groupBy('eleve_id')->pluck('total', 'eleve_id');

        return view('back.pages.frais-dossiers.edit', compact('fraisDossier', 'classesAnnees', 'eleves', 'totalPayeParEleve'));
    }

    public function update(Request $request, FraisDossier $fraisDossier)
    {
        $request->validate([
            'classe_annee_id' => [
                'required',
                'exists:classe_annees,id',
                Rule::unique('frais_dossiers')->ignore($fraisDossier->id),
            ],
            'montant' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $fraisDossier->update($request->only(['classe_annee_id', 'montant', 'description']));

        return redirect()->route('admin.frais-dossiers.index')->with('success', 'Frais de dossier mis à jour.');
    }

    public function destroy(FraisDossier $fraisDossier)
    {
        $fraisDossier->delete();

        return redirect()->route('admin.frais-dossiers.index')->with('success', 'Frais de dossier supprimé.');
    }

    /**
     * Saisie manuelle (back-office) d'un paiement de frais de dossier pour un élève.
     */
    public function payer(Request $request, FraisDossier $fraisDossier)
    {
        $request->validate([
            'eleve_id' => 'required|exists:eleves,id',
            'montant' => 'required|numeric|min:1',
            'mode_paiement' => 'required|in:especes,mobile_money,virement,carte,cheque',
        ]);

        $eleve = Eleve::findOrFail($request->eleve_id);

        if (! $eleve->parent_id) {
            return back()->with('error', "Cet élève n'a pas de parent associé, impossible d'enregistrer le paiement.");
        }

        Paiement::create([
            'eleve_id' => $eleve->id,
            'tranche_id' => null,
            'payable_type' => FraisDossier::class,
            'payable_id' => $fraisDossier->id,
            'montant' => $request->montant,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => $request->mode_paiement,
            'parent_id' => $eleve->parent_id,
            'commentaire' => 'Frais de dossier',
        ]);

        return back()->with('success', 'Paiement enregistré.');
    }

    private function classesAnneesSecondaire(?int $toujoursInclure = null)
    {
        return ClasseAnnee::with('classe.niveau', 'anneeScolaire')
            ->deLAnneeActive($toujoursInclure)
            ->whereHas('classe.niveau', fn ($q) => $q->where('cycle', 'secondaire'))
            ->get();
    }
}
