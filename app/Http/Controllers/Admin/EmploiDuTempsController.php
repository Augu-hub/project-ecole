<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\CreneauHoraire;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Services\EmploiDuTempsConflitService;
use Illuminate\Http\Request;

class EmploiDuTempsController extends Controller
{
    public const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    public function classe(ClasseAnnee $classeAnnee)
    {
        $classeAnnee->load('classe.niveau', 'anneeScolaire');

        $classeMatieres = ClasseMatiere::where('classe_annee_id', $classeAnnee->id)
            ->whereNotNull('enseignant_id')
            ->with('matiere', 'enseignant')
            ->get();

        $creneauxHoraires = CreneauHoraire::orderBy('ordre')->get();

        $emploisParJour = EmploiDuTemps::where('classe_annee_id', $classeAnnee->id)
            ->with('matiere', 'creneauHoraire')
            ->get()
            ->groupBy('jour_semaine');

        $jours = self::JOURS;

        return view('back.pages.emploi-du-temps.classe', compact(
            'classeAnnee', 'classeMatieres', 'creneauxHoraires', 'emploisParJour', 'jours'
        ));
    }

    public function store(Request $request, ClasseAnnee $classeAnnee, EmploiDuTempsConflitService $conflitService)
    {
        $request->validate([
            'matiere_id' => 'required|exists:matieres,id',
            'jour_semaine' => 'required|in:'.implode(',', self::JOURS),
            'creneau_horaire_id' => 'required|exists:creneaux_horaires,id',
            'salle' => 'nullable|string|max:50',
        ]);

        $classeMatiere = ClasseMatiere::where('classe_annee_id', $classeAnnee->id)
            ->where('matiere_id', $request->matiere_id)
            ->whereNotNull('enseignant_id')
            ->first();

        if (! $classeMatiere) {
            return back()->with('error', "Cette matière n'a pas d'enseignant assigné dans cette classe.")->withInput();
        }

        $creneau = CreneauHoraire::findOrFail($request->creneau_horaire_id);

        $conflit = $conflitService->detecter($classeAnnee->id, $request->matiere_id, $request->jour_semaine, $creneau);
        if ($conflit) {
            return back()->with('error', $conflit)->withInput();
        }

        EmploiDuTemps::create([
            'classe_annee_id' => $classeAnnee->id,
            'matiere_id' => $request->matiere_id,
            'jour_semaine' => $request->jour_semaine,
            'creneau_horaire_id' => $request->creneau_horaire_id,
            'salle' => $request->salle,
        ]);

        return back()->with('success', 'Créneau ajouté à l\'emploi du temps.');
    }

    public function destroy(EmploiDuTemps $emploiDuTemps)
    {
        $classeAnneeId = $emploiDuTemps->classe_annee_id;
        $emploiDuTemps->delete();

        return redirect()->route('admin.emploi-du-temps.classe', $classeAnneeId)
            ->with('success', 'Créneau retiré de l\'emploi du temps.');
    }

    public function parEnseignant(Enseignant $enseignant)
    {
        $classeMatiereCles = ClasseMatiere::where('enseignant_id', $enseignant->id)
            ->get(['classe_annee_id', 'matiere_id']);

        $emploisParJour = collect();
        if ($classeMatiereCles->isNotEmpty()) {
            $query = EmploiDuTemps::query()->with('classeAnnee.classe.niveau', 'matiere', 'creneauHoraire');
            $query->where(function ($q) use ($classeMatiereCles) {
                foreach ($classeMatiereCles as $cle) {
                    $q->orWhere(function ($sub) use ($cle) {
                        $sub->where('classe_annee_id', $cle->classe_annee_id)
                            ->where('matiere_id', $cle->matiere_id);
                    });
                }
            });
            $emploisParJour = $query->get()->groupBy('jour_semaine');
        }

        $jours = self::JOURS;

        return view('back.pages.emploi-du-temps.enseignant', compact('enseignant', 'emploisParJour', 'jours'));
    }
}
