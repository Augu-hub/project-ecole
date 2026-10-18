<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Controller;
use App\Models\Communique;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;

class CommuniqueController extends Controller
{


    public function index()
    {
        $communiques = Communique::with(['classesAnnees.classe.niveau', 'createur'])
            ->latest()
            ->paginate(15);

        return view('back.pages.communiques.index', compact('communiques'));
    }

    public function create()
    {
        $classesAnnees = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->deLAnneeActive()->get();
        return view('back.pages.communiques.create', compact('classesAnnees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titre'            => 'required|string|max:255',
            'contenu'          => 'required|string',
            'type'             => 'required|in:urgent,evenement,general,academique',
            'date_publication' => 'required|date',
            'date_expiration'  => 'nullable|date|after:date_publication',
            'classe_annee_ids' => 'required|array|min:1',
            'classe_annee_ids.*' => 'exists:classe_annees,id',
        ]);

        DB::beginTransaction();
        try {
            $communique = Communique::create([
                'titre'            => $request->titre,
                'contenu'          => $request->contenu,
                'type'             => $request->type,
                'date_publication' => $request->date_publication,
                'date_expiration'  => $request->date_expiration,
                'actif'            => true,
            ]);

            $communique->classesAnnees()->attach($request->classe_annee_ids);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', "Le communiqué n'a pas pu être enregistré. Veuillez réessayer.")->withInput();
        }

        // Notifications complètement isolées — une erreur ici ne bloque rien
        try {
            $this->notifierParents($communique, app(Messaging::class));
        } catch (\Exception $e) {
            Log::error('Erreur notification communiqué: ' . $e->getMessage());
            // On continue quand même
        }

        return redirect()->route('admin.communiques.index')
            ->with('success', 'Communiqué publié avec succès.');
    }

    public function show(Communique $communique)
    {
        $communique->load(['classesAnnees.classe.niveau', 'createur']);
        return view('back.pages.communiques.show', compact('communique'));
    }

    public function edit(Communique $communique)
    {
        $selectedIds   = $communique->classesAnnees->pluck('id')->toArray();
        $classesAnnees = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->deLAnneeActive($selectedIds)->get();
        return view('back.pages.communiques.edit', compact('communique', 'classesAnnees', 'selectedIds'));
    }

    public function update(Request $request, Communique $communique)
    {
        $request->validate([
            'titre'            => 'required|string|max:255',
            'contenu'          => 'required|string',
            'type'             => 'required|in:urgent,evenement,general,academique',
            'date_publication' => 'required|date',
            'date_expiration'  => 'nullable|date|after:date_publication',
            'classe_annee_ids' => 'required|array|min:1',
            'classe_annee_ids.*' => 'exists:classe_annees,id',
        ]);

        $communique->update($request->only([
            'titre', 'contenu', 'type', 'date_publication', 'date_expiration',
        ]));

        $communique->classesAnnees()->sync($request->classe_annee_ids);

        return redirect()->route('admin.communiques.index')
            ->with('success', 'Communiqué mis à jour.');
    }

    public function destroy(Communique $communique)
    {
        $communique->delete();
        return redirect()->route('admin.communiques.index')
            ->with('success', 'Communiqué supprimé.');
    }

    public function toggle(Communique $communique)
    {
        $communique->update(['actif' => !$communique->actif]);
        return back()->with('success', 'Statut mis à jour.');
    }

    private function notifierParents(Communique $communique, Messaging $messaging): void
    {
        $notificationController = new NotificationController($messaging);
        $classeAnneeIds = $communique->classesAnnees->pluck('id');

        // Récupérer les parent_id des élèves des classes concernées
        $parentIds = Eleve::whereIn('classe_annee_id', $classeAnneeIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique();

        $users = User::whereHas('parent', function ($q) use ($parentIds) {
            $q->whereIn('id', $parentIds);
        })->whereNotNull('fcm_token')->get();

        $emoji = match($communique->type) {
            'urgent'    => '🚨',
            'evenement' => '📅',
            'academique'=> '📚',
            default     => '📢',
        };

        foreach ($users as $user) {
            if (!$user->fcm_token) {
                continue; // Ce parent n'a pas de token FCM
            }
            $notificationController->sendFCMNotification(
                $user->fcm_token,
                "$emoji {$communique->titre}",
                substr(strip_tags($communique->contenu), 0, 100) . '...',
                ['route' => '/communiques', 'communique_id' => (string) $communique->id]
            );
        }
    }
}
