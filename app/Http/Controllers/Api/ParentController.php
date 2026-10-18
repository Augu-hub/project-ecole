<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parents;
use App\Models\Eleve;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function enfants($id)
    {
        $parent = Parents::findOrFail($id);
        $enfants = Eleve::where('parent_id', $id)
            ->with(['classeAnnee.classe.niveau'])
            ->get()
            ->map(function ($eleve) {
                return [
                    'id' => $eleve->id,
                    'matricule' => $eleve->matricule,
                    'nom' => $eleve->nom,
                    'prenom' => $eleve->prenom,
                    'sexe' => $eleve->sexe ?? null,
                    'date_naissance' => $eleve->date_naissance ?? null,
                    'photo' => $eleve->photo,
                    'classe' => $eleve->classeAnnee->classe->suffixe,
                    'niveau' => $eleve->classeAnnee->classe->niveau->nom,
                    // 'secondaire' = accès complet à l'app ; tout le reste (primaire, maternelle,
                    // niveau non renseigné) = accès limité à la cantine côté mobile.
                    'cycle' => $eleve->cycle_cantine,
                    'statut' => $eleve->statut,
                ];
            });

        return response()->json($enfants);
    }
}
