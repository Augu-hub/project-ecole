<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Services\RepriseMatieresService;
use Illuminate\Http\Request;

class ClasseMatiereController extends Controller
{
    /** Message affiché si la reprise échoue : le détail technique part dans les journaux, jamais à l'écran. */
    private const REPRISE_IMPOSSIBLE = "Les matières n'ont pas pu être reprises et rien n'a été modifié. Veuillez réessayer.";

    /**
     * Affiche la liste des associations pour une classe donnée.
     */
    public function index(ClasseAnnee $classeAnnee, RepriseMatieresService $repriseMatieres)
    {
        $matieres = Matiere::all();
        // Récupérer les matières associées avec leur coefficient (via pivot)
        $coefficients = $classeAnnee->matieres()->get();
        $enseignants = Enseignant::orderBy('nom')->get();

        // Classe encore sans matière : on propose de reprendre celles qu'elle avait une année précédente
        $reprise = $coefficients->isEmpty() ? $repriseMatieres->apercu([$classeAnnee])->first() : null;
        $matieresAReprendre = $reprise
            ? $reprise['source']->matieres()->orderBy('nom_matiere')->pluck('nom_matiere')
            : collect();

        return view('back.pages.classe-matieres.index', compact(
            'classeAnnee', 'matieres', 'coefficients', 'enseignants', 'reprise', 'matieresAReprendre'
        ));
    }

    /**
     * Enregistre une nouvelle association (ou met à jour le coefficient).
     */
    public function store(Request $request, ClasseAnnee $classeAnnee)
    {
        $request->validate([
            'matiere_id' => 'required|exists:matieres,id',
            'coefficient' => 'required|numeric|min:0.1|max:10',
            'enseignant_id' => 'nullable|exists:enseignants,id',
        ]);

        // Vérifier si l'association existe déjà
        $exists = ClasseMatiere::where('classe_annee_id', $classeAnnee->id)
            ->where('matiere_id', $request->matiere_id)
            ->exists();
        if ($exists) {
            return back()->with('error', 'Cette matière est déjà associée à cette classe.');
        }

        ClasseMatiere::create([
            'classe_annee_id' => $classeAnnee->id,
            'matiere_id' => $request->matiere_id,
            'coefficient' => $request->coefficient,
            'enseignant_id' => $request->enseignant_id,
        ]);

        return back()->with('success', 'Matière ajoutée avec succès.');
    }

    /**
     * Met à jour le coefficient et l'enseignant assigné.
     */
    public function update(Request $request, ClasseAnnee $classeAnnee, Matiere $matiere)
    {
        $request->validate([
            'coefficient' => 'required|numeric|min:0.1|max:10',
            'enseignant_id' => 'nullable|exists:enseignants,id',
        ]);

        // La clé de classe_matieres a deux colonnes (classe + matière) : Eloquent ne sait pas enregistrer
        // un tel modèle (erreur 500), la ligne est donc modifiée directement par une requête
        $association = ClasseMatiere::where('classe_annee_id', $classeAnnee->id)
            ->where('matiere_id', $matiere->id);
        abort_unless($association->exists(), 404);

        $association->update([
            'coefficient' => $request->coefficient,
            'enseignant_id' => $request->enseignant_id,
        ]);

        return back()->with('success', 'Coefficient mis à jour.');
    }

    /**
     * Supprime une association.
     */
    public function destroy(ClasseAnnee $classeAnnee, Matiere $matiere)
    {
        // Même raison que dans update() : suppression par une requête, pas par le modèle
        $association = ClasseMatiere::where('classe_annee_id', $classeAnnee->id)
            ->where('matiere_id', $matiere->id);
        abort_unless($association->exists(), 404);
        $association->delete();

        return back()->with('success', 'Matière retirée de la classe.');
    }

    /**
     * Reprend, pour cette classe encore sans matière, les matières qu'elle avait l'année précédente
     * (avec leurs coefficients et leurs enseignants). Lancée depuis la page des matières de la classe.
     */
    public function reprendre(ClasseAnnee $classeAnnee, RepriseMatieresService $repriseMatieres)
    {
        $page = redirect()->route('admin.classe-matieres.index', $classeAnnee);

        try {
            $resultat = $repriseMatieres->reprendre([$classeAnnee]);
        } catch (\Throwable $e) {
            report($e);

            return $page->with('error', self::REPRISE_IMPOSSIBLE);
        }

        if ($resultat['matieres'] === 0) {
            return $page->with('info', $classeAnnee->matieres()->exists()
                ? 'Rien à reprendre : cette classe a déjà des matières, et elles ne sont jamais remplacées.'
                : "Rien à reprendre : cette classe n'avait pas de matières les années précédentes.");
        }

        return $page->with('success', sprintf(
            'Reprise terminée : %s de l\'année %s, avec les coefficients et les enseignants. Vérifiez-les et ajustez-les si besoin.',
            $this->nombre($resultat['matieres'], 'matière reprise', 'matières reprises'),
            implode(', ', $resultat['annees'])
        ));
    }

    /**
     * Reprend d'un coup les matières de toutes les classes de l'année active qui n'en ont aucune.
     * Lancée depuis la page de saisie des notes, sur laquelle on revient.
     */
    public function reprendrePourAnneeActive(RepriseMatieresService $repriseMatieres)
    {
        $page = redirect()->route('admin.notes.create');

        if (! AnneeScolaire::active()) {
            return $page->with('info', "Rien à reprendre : aucune année scolaire n'est active.");
        }

        try {
            $resultat = $repriseMatieres->reprendre(ClasseAnnee::deLAnneeActive()->get());
        } catch (\Throwable $e) {
            report($e);

            return $page->with('error', self::REPRISE_IMPOSSIBLE);
        }

        if ($resultat['classes'] === 0) {
            $classesEncoreVides = ClasseAnnee::deLAnneeActive()->whereDoesntHave('matieres')->exists();

            return $page->with('info', $classesEncoreVides
                ? "Rien à reprendre : les classes sans matière n'en avaient pas non plus les années précédentes."
                : "Rien à reprendre : toutes les classes de l'année ont déjà des matières.");
        }

        return $page->with('success', sprintf(
            'Reprise terminée : %s, %s (coefficients et enseignants compris).',
            $this->nombre($resultat['classes'], 'classe complétée', 'classes complétées'),
            $this->nombre($resultat['matieres'], 'matière rattachée', 'matières rattachées')
        ));
    }

    /** « 1 classe complétée », « 22 classes complétées ». */
    private function nombre(int $nombre, string $singulier, string $pluriel): string
    {
        return $nombre.' '.($nombre > 1 ? $pluriel : $singulier);
    }
}
