<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

// ShouldBroadcastNow (et non ShouldBroadcast) : diffuse de façon synchrone, sans passer par la
// queue. Un message de chat doit arriver en temps réel immédiatement ; passer par la queue
// aurait exigé un worker (php artisan queue:work) supervisé en plus de reverb:start, en local
// comme sur le VPS, pour un gain qui ne se justifie pas ici (payload léger, faible fréquence).
class MessageEnvoye implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'new_message';
    }

    /**
     * Même forme de payload que l'ancien serveur Node, pour limiter les changements côté client.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_type' => $this->message->sender_type,
            'sender_name' => $this->message->sender?->name,
            'body' => $this->message->body,
            'is_read' => $this->message->is_read,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
