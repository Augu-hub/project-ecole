<?php

use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Support\Facades\Broadcast;

/**
 * Autorisation du canal privé d'une conversation : seuls ses deux participants (le parent
 * et le membre du personnel) peuvent s'y abonner. Réutilise la même logique que l'API REST
 * (ConversationService::isParticipant) pour éviter toute divergence entre les deux.
 */
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (! $conversation) {
        return false;
    }

    return app(ConversationService::class)->isParticipant($user, $conversation);
});
