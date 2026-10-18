<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Communique;
use App\Models\Eleve;
use Illuminate\Http\Request;

class CommuniqueApiController extends Controller
{
    public function index(Request $request)
    {
        // Récupérer les classes de l'enfant du parent connecté
        $parent = $request->user()->parent;
        $classeIds = Eleve::where('parent_id', $parent->id)
            ->pluck('classe_annee_id');

        $communiques = Communique::whereHas('classesAnnees', function ($q) use ($classeIds) {
                $q->whereIn('classe_annees.id', $classeIds);
            })
            ->where('actif', true)
            ->where('date_publication', '<=', now())
            ->where(function ($q) {
                $q->whereNull('date_expiration')
                  ->orWhere('date_expiration', '>=', now());
            })
            ->orderByRaw("FIELD(type, 'urgent', 'academique', 'evenement', 'general')")
            ->orderBy('date_publication', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'titre'            => $c->titre,
                'contenu'          => $c->contenu,
                'type'             => $c->type,
                'date_publication' => $c->date_publication->format('Y-m-d'),
                'date_expiration'  => $c->date_expiration?->format('Y-m-d'),
            ]);

        return response()->json($communiques);
    }

    public function show(Communique $communique)
    {
        return response()->json([
            'id'               => $communique->id,
            'titre'            => $communique->titre,
            'contenu'          => $communique->contenu,
            'type'             => $communique->type,
            'date_publication' => $communique->date_publication->format('d/m/Y'),
            'date_expiration'  => $communique->date_expiration?->format('d/m/Y'),
            'classes'          => $communique->classesAnnees->map(fn($ca) => [
                'id'     => $ca->id,
                'libelle'=> $ca->classe->niveau->nom . $ca->classe->suffixe,
            ]),
        ]);
    }
}
