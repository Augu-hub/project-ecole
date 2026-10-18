<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Niveau;
use App\Models\Reinscription;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReinscriptionController extends Controller
{
    public function __construct(private BulletinService $bulletinService) {}

    /**
     * Sélection de l'année source (celle des élèves à réinscrire pour l'année active).
     */
    public function index()
    {
        $anneeActive = AnneeScolaire::activeOrFail();

        $anneesSources = AnneeScolaire::where('id', '!=', $anneeActive->id)
            ->orderByDesc('date_debut')
            ->get();

        return view('back.pages.reinscriptions.index', compact('anneeActive', 'anneesSources'));
    }

    /**
     * Étape 1 : choix de la classe source à traiter. La campagne se fait classe par classe
     * (jamais tous les élèves secondaires mélangés ensemble) pour que le staff puisse traiter
     * une classe à la fois, avec une classe cible cohérente pour tout le groupe.
     */
    public function campagne(AnneeScolaire $anneeSource)
    {
        $anneeActive = AnneeScolaire::activeOrFail();

        abort_if($anneeSource->id === $anneeActive->id, 422, "L'année source doit être différente de l'année active.");

        $dejaTraites = Reinscription::where('annee_scolaire_id', $anneeActive->id)->pluck('eleve_id');

        $classesSources = ClasseAnnee::with('classe.niveau')
            ->where('annee_scolaire_id', $anneeSource->id)
            ->whereHas('classe.niveau', fn ($q) => $q->where('cycle', 'secondaire'))
            ->withCount([
                'eleves as effectif_actif' => fn ($q) => $q->where('statut', 'actif'),
                'eleves as non_traites' => fn ($q) => $q->where('statut', 'actif')->whereNotIn('id', $dejaTraites),
            ])
            ->get()
            ->sortBy(fn ($ca) => $ca->classe->niveau->ordre);

        return view('back.pages.reinscriptions.classes', compact('anneeSource', 'anneeActive', 'classesSources'));
    }

    /**
     * Étape 2 : campagne pour une classe source précise — liste de ses élèves, suggestion
     * passe/redouble, et classe cible pré-sélectionnée intelligemment (niveau suivant pour un
     * promu, même niveau pour un redoublant), modifiable au cas par cas.
     */
    public function campagneClasse(AnneeScolaire $anneeSource, ClasseAnnee $classeSource)
    {
        $anneeActive = AnneeScolaire::activeOrFail();

        abort_if($anneeSource->id === $anneeActive->id, 422, "L'année source doit être différente de l'année active.");
        abort_if($classeSource->annee_scolaire_id !== $anneeSource->id, 404);

        $classeSource->load('classe.niveau');

        $dejaTraites = Reinscription::where('annee_scolaire_id', $anneeActive->id)->pluck('eleve_id');

        $eleves = Eleve::where('statut', 'actif')
            ->where('classe_annee_id', $classeSource->id)
            ->whereNotIn('id', $dejaTraites)
            ->orderBy('nom')->orderBy('prenom')
            ->get();

        $suggestions = [];
        foreach ($eleves as $eleve) {
            $bulletin = $this->bulletinService->bulletinComplet($eleve, $anneeSource->id, 'ponderee');
            $moyenne = $bulletin['moyAnnuelle'];
            $suggestions[$eleve->id] = [
                'moyenne' => $moyenne,
                'type' => $moyenne === null ? null : ($moyenne >= 10 ? 'promu' : 'redoublant'),
            ];
        }

        $classesCibles = ClasseAnnee::with('classe.niveau')
            ->where('annee_scolaire_id', $anneeActive->id)
            ->whereHas('classe.niveau', fn ($q) => $q->where('cycle', 'secondaire'))
            ->get();

        $niveauActuel = $classeSource->classe->niveau;
        $niveauSuivant = Niveau::where('cycle', 'secondaire')->where('ordre', $niveauActuel->ordre + 1)->first();

        // Suggestions de classe cible par défaut : même suffixe si possible, sinon la première
        // classe cible disponible pour ce niveau (le staff peut toujours corriger manuellement).
        $classeCibleParDefautPromu = $classesCibles
            ->filter(fn ($ca) => $niveauSuivant && $ca->classe->niveau_id === $niveauSuivant->id)
            ->sortByDesc(fn ($ca) => $ca->classe->suffixe === $classeSource->classe->suffixe)
            ->first();

        $classeCibleParDefautRedoublant = $classesCibles
            ->filter(fn ($ca) => $ca->classe->niveau_id === $niveauActuel->id)
            ->sortByDesc(fn ($ca) => $ca->classe->suffixe === $classeSource->classe->suffixe)
            ->first();

        return view('back.pages.reinscriptions.campagne', compact(
            'anneeSource', 'anneeActive', 'classeSource', 'eleves', 'suggestions', 'classesCibles',
            'classeCibleParDefautPromu', 'classeCibleParDefautRedoublant'
        ));
    }

    /**
     * Traitement groupé des décisions de la campagne d'une classe (un select classe cible + type
     * par élève).
     */
    public function confirmer(Request $request, AnneeScolaire $anneeSource, ClasseAnnee $classeSource)
    {
        $anneeActive = AnneeScolaire::activeOrFail();

        $decisions = $request->input('decisions', []);
        $traites = 0;

        DB::transaction(function () use ($decisions, $anneeActive, $request, &$traites) {
            foreach ($decisions as $eleveId => $decision) {
                $eleve = Eleve::find($eleveId);
                if (! $eleve) {
                    continue;
                }

                // Déjà traité pour cette année cible (évite les doublons en cas de double soumission).
                if (Reinscription::where('eleve_id', $eleve->id)->where('annee_scolaire_id', $anneeActive->id)->exists()) {
                    continue;
                }

                $classeAnneePrecedenteId = $eleve->classe_annee_id;

                if (($decision['action'] ?? null) === 'ne_revient_pas') {
                    $eleve->update(['statut' => 'ancien']);

                    continue;
                }

                $classeAnneeId = $decision['classe_annee_id'] ?? null;
                $type = $decision['type'] ?? null;

                if (! $classeAnneeId || ! in_array($type, ['promu', 'redoublant'], true)) {
                    continue;
                }

                Reinscription::create([
                    'eleve_id' => $eleve->id,
                    'annee_scolaire_id' => $anneeActive->id,
                    'classe_annee_precedente_id' => $classeAnneePrecedenteId,
                    'classe_annee_id' => $classeAnneeId,
                    'type' => $type,
                    'moyenne_suggestion' => $decision['moyenne_suggestion'] ?? null,
                    'decide_par_user_id' => $request->user()->id,
                ]);

                $eleve->update(['classe_annee_id' => $classeAnneeId]);

                $traites++;
            }
        });

        return redirect()->route('admin.reinscriptions.campagne', $anneeSource)
            ->with('success', "$traites élève(s) traité(s) pour ".$classeSource->classe->niveau->nom.' '.$classeSource->classe->suffixe.'.');
    }
}
