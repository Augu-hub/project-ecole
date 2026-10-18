<?php

use App\Events\MessageEnvoye;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

test('un parent ne peut pas lire les messages d\'une conversation qui n\'est pas la sienne', function () {
    $autreParent = Parents::factory()->create();
    $staff = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $conversation = Conversation::create(['parent_id' => $autreParent->id, 'admin_id' => $staff->id]);

    $moi = Parents::factory()->create();
    Sanctum::actingAs(User::find($moi->user_id));

    $this->getJson("/api/conversations/{$conversation->id}/messages")->assertStatus(403);
});

test('un membre du staff ne peut pas lire les messages d\'une conversation qui n\'est pas la sienne', function () {
    $parent = Parents::factory()->create();
    $autreStaff = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $conversation = Conversation::create(['parent_id' => $parent->id, 'admin_id' => $autreStaff->id]);

    $moi = User::factory()->create(['role' => 'admin', 'actif' => true]);
    Sanctum::actingAs($moi);

    $this->getJson("/api/conversations/{$conversation->id}/messages")->assertStatus(403);
});

test('un parent peut demarrer une conversation avec un membre du staff', function () {
    $parent = Parents::factory()->create();
    $staff = User::factory()->create(['role' => 'admin', 'actif' => true]);

    Sanctum::actingAs(User::find($parent->user_id));

    $response = $this->postJson('/api/conversations', ['counterpart_id' => $staff->id]);

    $response->assertOk();
    expect(Conversation::where('parent_id', $parent->id)->where('admin_id', $staff->id)->exists())->toBeTrue();
});

test('un membre du staff peut demarrer une conversation avec un parent', function () {
    $parent = Parents::factory()->create();
    $staff = User::factory()->create(['role' => 'admin', 'actif' => true]);

    Sanctum::actingAs($staff);

    $response = $this->postJson('/api/conversations', ['counterpart_id' => $parent->id]);

    $response->assertOk();
    expect(Conversation::where('parent_id', $parent->id)->where('admin_id', $staff->id)->exists())->toBeTrue();
});

test('envoyer un message cree la ligne et declenche l\'evenement de diffusion', function () {
    Event::fake([MessageEnvoye::class]);

    $parent = Parents::factory()->create();
    $staff = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $conversation = Conversation::create(['parent_id' => $parent->id, 'admin_id' => $staff->id]);

    Sanctum::actingAs($staff);

    $response = $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Bonjour']);

    $response->assertOk();

    $message = Message::where('conversation_id', $conversation->id)->first();
    expect($message)->not->toBeNull()
        ->and($message->sender_id)->toBe($staff->id)
        ->and($message->sender_type)->toBe('admin')
        ->and($message->body)->toBe('Bonjour');

    Event::assertDispatched(MessageEnvoye::class, fn ($e) => $e->message->id === $message->id);
});

test('marquer lu passe les messages non lus de l\'autre partie a lu', function () {
    $parent = Parents::factory()->create();
    $staff = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $conversation = Conversation::create(['parent_id' => $parent->id, 'admin_id' => $staff->id]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $parent->user_id,
        'sender_type' => 'parent',
        'body' => 'Salut',
        'is_read' => false,
    ]);

    Sanctum::actingAs($staff);

    $this->postJson("/api/conversations/{$conversation->id}/marquer-lu")->assertOk();

    expect(Message::where('conversation_id', $conversation->id)->first()->is_read)->toBeTrue();
});

test('la route d\'autorisation des canaux de diffusion refuse un non-participant', function () {
    // BROADCAST_CONNECTION=null en environnement de test (phpunit.xml) court-circuite
    // l'authentification réelle des canaux ; on force le driver reverb pour ce test précis.
    config(['broadcasting.default' => 'reverb']);

    $parent = Parents::factory()->create();
    $staff = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $conversation = Conversation::create(['parent_id' => $parent->id, 'admin_id' => $staff->id]);

    $intrus = User::factory()->create(['role' => 'admin', 'actif' => true]);
    Sanctum::actingAs($intrus);

    $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-conversation.'.$conversation->id,
        'socket_id' => '123.456',
    ])->assertStatus(403);
});

test('la route d\'autorisation des canaux de diffusion accepte un participant', function () {
    $parent = Parents::factory()->create();
    $staff = User::factory()->create(['role' => 'admin', 'actif' => true]);
    $conversation = Conversation::create(['parent_id' => $parent->id, 'admin_id' => $staff->id]);

    Sanctum::actingAs($staff);

    $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-conversation.'.$conversation->id,
        'socket_id' => '123.456',
    ])->assertOk();
});
