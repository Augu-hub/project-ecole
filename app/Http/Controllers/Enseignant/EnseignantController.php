<?php

namespace App\Http\Controllers\Enseignant;

use App\Http\Controllers\Admin\EmploiDuTempsController;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\EmploiDuTemps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnseignantController extends Controller
{
    public function dashboard(Request $request)
    {
        $enseignant = Auth::user()->enseignant;
        $anneeActive = AnneeScolaire::active();

        $classeMatieres = collect();
        if ($enseignant) {
            $classeMatieres = $enseignant->classeMatieres()
                ->with(['classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire', 'matiere'])
                ->whereHas('classeAnnee', function ($q) use ($anneeActive) {
                    if ($anneeActive) {
                        $q->where('annee_scolaire_id', $anneeActive->id);
                    }
                })
                ->get();
        }

        return view('enseignant.dashboard', compact('enseignant', 'anneeActive', 'classeMatieres'));
    }

    public function emploiDuTemps()
    {
        $enseignant = Auth::user()->enseignant;

        $emploisParJour = collect();
        if ($enseignant) {
            $classeMatiereCles = $enseignant->classeMatieres()->get(['classe_annee_id', 'matiere_id']);

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
        }

        $jours = EmploiDuTempsController::JOURS;

        return view('enseignant.emploi_du_temps', compact('enseignant', 'emploisParJour', 'jours'));
    }
}
