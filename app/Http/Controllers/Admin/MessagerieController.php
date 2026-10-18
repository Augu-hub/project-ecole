<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Eleve;
use App\Models\Parents;
use App\Services\ConversationService;
use Illuminate\Http\Request;

class MessagerieController extends Controller
{
    public function __construct(private ConversationService $conversations) {}

    /**
     * Page de messagerie staff. L'authentification temps réel (Reverb) et les appels REST
     * passent par la session Laravel classique (cookie + CSRF) — aucun token à émettre, la
     * route d'auth des canaux (Broadcast::routes, voir routes/api.php) accepte nativement les
     * sessions de première partie via Sanctum.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return view('back.pages.messagerie.index', [
            'currentUserId' => $user->id,
            'currentUserName' => $user->name,
            'reverbConfig' => [
                'key' => config('reverb.apps.apps.0.key'),
                'host' => config('services.reverb_client.host'),
                'port' => config('services.reverb_client.port'),
                'scheme' => config('services.reverb_client.scheme'),
            ],
        ]);
    }

    /**
     * Recherche d'un parent (pour démarrer une nouvelle conversation), par nom/prénom/téléphone
     * du parent ou par nom/prénom d'un de ses enfants.
     */
    public function rechercherParent(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $parentsParDirect = Parents::where('nom', 'like', "%{$q}%")
            ->orWhere('prenom', 'like', "%{$q}%")
            ->orWhere('telephone', 'like', "%{$q}%")
            ->pluck('id');

        $parentsParEleve = Eleve::where('nom', 'like', "%{$q}%")
            ->orWhere('prenom', 'like', "%{$q}%")
            ->pluck('parent_id')
            ->filter();

        $ids = $parentsParDirect->merge($parentsParEleve)->unique()->take(20);

        $parents = Parents::with('eleves')->whereIn('id', $ids)->get();

        return response()->json($parents->map(fn ($p) => [
            'id' => $p->id,
            'nom_complet' => trim($p->prenom.' '.$p->nom),
            'telephone' => $p->telephone,
            'enfants' => $p->eleves->map(fn ($e) => trim($e->prenom.' '.$e->nom))->values(),
        ])->values());
    }

    public function mesConversations(Request $request)
    {
        return response()->json($this->conversations->listForUser($request->user()));
    }

    public function messages(Request $request, Conversation $conversation)
    {
        if (! $this->conversations->isParticipant($request->user(), $conversation)) {
            return response()->json(['error' => 'Accès refusé à cette conversation'], 403);
        }

        $beforeId = $request->filled('before_id') ? (int) $request->input('before_id') : null;
        $limit = (int) $request->input('limit', 30);

        return response()->json($this->conversations->getMessages($conversation, $beforeId, $limit));
    }

    public function creerConversation(Request $request)
    {
        $request->validate(['counterpart_id' => 'required|integer']);

        $conversation = $this->conversations->createOrGetConversation($request->user(), (int) $request->input('counterpart_id'));

        return response()->json($conversation);
    }

    public function envoyerMessage(Request $request, Conversation $conversation)
    {
        if (! $this->conversations->isParticipant($request->user(), $conversation)) {
            return response()->json(['error' => 'Accès refusé à cette conversation'], 403);
        }

        $request->validate(['body' => 'required|string']);

        $message = $this->conversations->sendMessage($conversation, $request->user(), $request->input('body'));

        return response()->json($message);
    }

    public function marquerLu(Request $request, Conversation $conversation)
    {
        if (! $this->conversations->isParticipant($request->user(), $conversation)) {
            return response()->json(['error' => 'Accès refusé à cette conversation'], 403);
        }

        $this->conversations->markRead($conversation, $request->user());

        return response()->json(['success' => true]);
    }
}
