<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\Request;

/**
 * Messagerie parent ↔ personnel côté mobile (remplace les appels au serveur Node
 * chat-server-ccpa). Le temps réel passe par Laravel Reverb (canal privé
 * "conversation.{id}", voir routes/channels.php) ; ce contrôleur ne gère que le REST.
 */
class ChatController extends Controller
{
    public function __construct(private ConversationService $conversations) {}

    public function mesConversations(Request $request)
    {
        return response()->json($this->conversations->listForUser($request->user()));
    }

    /**
     * Liste du personnel (non-parents actifs), pour qu'un parent choisisse à qui écrire.
     */
    public function staff()
    {
        return response()->json(
            User::where('role', '!=', 'parent')->where('actif', true)->orderBy('name')->get(['id', 'name', 'role', 'actif'])
        );
    }

    public function messages(Request $request, Conversation $conversation)
    {
        if (! $this->conversations->isParticipant($request->user(), $conversation)) {
            return response()->json(['error' => 'Accès refusé à cette conversation'], 403);
        }

        $beforeId = $request->filled('before_id') ? (int) $request->input('before_id') : null;
        $limit = (int) $request->input('limit', 20);

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
