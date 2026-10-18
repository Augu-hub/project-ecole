<?php

namespace App\Services;

use App\Events\MessageEnvoye;
use App\Http\Controllers\Api\NotificationController;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Logique métier de la messagerie parent ↔ personnel, portée depuis l'ancien serveur Node
 * (chat-server-ccpa/index.js) vers Laravel/Reverb. Utilisée à la fois par l'API mobile
 * (Api\ChatController, token Sanctum) et par l'admin web (Admin\MessagerieController, session).
 */
class ConversationService
{
    public function isParticipant(User $user, Conversation $conversation): bool
    {
        if ($user->role === 'parent') {
            $parent = $user->parent;

            return $parent && $conversation->parent_id === $parent->id;
        }

        return $conversation->admin_id === $user->id;
    }

    /**
     * Liste des conversations de l'utilisateur connecté (parent ou staff), avec dernier
     * message et compteur de non-lus.
     */
    public function listForUser(User $user)
    {
        if ($user->role === 'parent') {
            $parent = $user->parent;
            if (! $parent) {
                return collect();
            }

            return Conversation::where('parent_id', $parent->id)
                ->with('admin')
                ->get()
                ->map(fn ($c) => $this->formatConversation($c, 'parent'))
                ->sortByDesc('last_message_time')
                ->values();
        }

        return Conversation::where('admin_id', $user->id)
            ->with('parent')
            ->get()
            ->map(fn ($c) => $this->formatConversation($c, 'staff'))
            ->sortByDesc('last_message_time')
            ->values();
    }

    private function formatConversation(Conversation $c, string $viewerType): array
    {
        $dernier = Message::where('conversation_id', $c->id)->orderByDesc('id')->first();
        $senderTypeNonLu = $viewerType === 'parent' ? 'admin' : 'parent';
        $nonLus = Message::where('conversation_id', $c->id)
            ->where('sender_type', $senderTypeNonLu)
            ->where('is_read', false)
            ->count();

        $base = [
            'id' => $c->id,
            'parent_id' => $c->parent_id,
            'admin_id' => $c->admin_id,
            'last_message' => $dernier?->body,
            'last_message_time' => $dernier?->created_at?->toIso8601String(),
            'unread_count' => $nonLus,
        ];

        if ($viewerType === 'parent') {
            $base['admin_name'] = $c->admin?->name;
            $base['admin_role'] = $c->admin?->role;
        } else {
            $base['parent_nom'] = $c->parent?->nom;
            $base['parent_prenom'] = $c->parent?->prenom;
        }

        return $base;
    }

    public function getMessages(Conversation $conversation, ?int $beforeId = null, int $limit = 30)
    {
        $query = Message::where('conversation_id', $conversation->id)->with('sender')->orderByDesc('id');
        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($limit)->get()->reverse()->values()->map(fn ($m) => $this->formatMessage($m));
    }

    private function formatMessage(Message $m): array
    {
        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'sender_id' => $m->sender_id,
            'sender_type' => $m->sender_type,
            'sender_name' => $m->sender?->name,
            'body' => $m->body,
            'is_read' => $m->is_read,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * @throws HttpException si l'appelant n'a pas de profil parent (parent) ou si la contrepartie
     *                        visée n'existe pas / n'est pas valide.
     */
    public function createOrGetConversation(User $user, int $counterpartId): Conversation
    {
        if ($user->role === 'parent') {
            $parent = $user->parent;
            if (! $parent) {
                throw new HttpException(403, 'Profil parent introuvable.');
            }

            $staff = User::where('id', $counterpartId)->where('role', '!=', 'parent')->where('actif', true)->first();
            if (! $staff) {
                throw new HttpException(404, 'Membre du personnel introuvable.');
            }

            $parentId = $parent->id;
            $adminId = $counterpartId;
        } else {
            $targetParent = Parents::find($counterpartId);
            if (! $targetParent) {
                throw new HttpException(404, 'Parent introuvable.');
            }

            $parentId = $counterpartId;
            $adminId = $user->id;
        }

        return Conversation::firstOrCreate(['parent_id' => $parentId, 'admin_id' => $adminId]);
    }

    public function sendMessage(Conversation $conversation, User $user, string $body): Message
    {
        $senderType = $user->role === 'parent' ? 'parent' : 'admin';

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => $senderType,
            'body' => trim($body),
        ]);
        $message->load('sender');

        broadcast(new MessageEnvoye($message));

        if ($senderType === 'admin') {
            $this->notifierParent($conversation, $user, $message);
        }

        return $message;
    }

    /**
     * Notification push (FCM) au parent quand un membre du staff lui écrit : l'admin utilise le
     * dashboard web (pas de push nécessaire), le parent est sur mobile et doit être averti même
     * hors de l'app. Même mécanisme que CommuniqueController::notifierParents().
     */
    private function notifierParent(Conversation $conversation, User $admin, Message $message): void
    {
        $parentUser = $conversation->parent?->user;
        if (! $parentUser || ! $parentUser->fcm_token) {
            return;
        }

        (new NotificationController(app(Messaging::class)))->sendFCMNotification(
            $parentUser->fcm_token,
            $admin->name,
            Str::limit($message->body, 100),
            ['route' => '/messages', 'conversation_id' => (string) $conversation->id],
        );
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $readerType = $user->role === 'parent' ? 'parent' : 'admin';
        $senderType = $readerType === 'parent' ? 'admin' : 'parent';

        Message::where('conversation_id', $conversation->id)
            ->where('sender_type', $senderType)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
