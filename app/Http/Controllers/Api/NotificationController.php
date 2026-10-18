<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Contract\Messaging;

class NotificationController extends Controller
{
    protected Messaging $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Sauvegarder le token FCM d'un utilisateur
     */
    public function saveFCMToken(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|integer',
            'fcm_token' => 'required|string',
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Token FCM sauvegardé avec succès'
        ]);
    }

    /**
     * Envoyer une notification à un utilisateur
     */
    public function sendNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'title'   => 'required|string',
            'body'    => 'required|string',
        ]);

        $user = User::find($request->user_id);

        if (!$user || !$user->fcm_token) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur ou token FCM non trouvé'
            ], 404);
        }

        $result = $this->sendFCMNotification(
            $user->fcm_token,
            $request->title,
            $request->body,
            ['route' => '/notifications']
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification envoyée',
            'result'  => $result,
        ]);
    }

    /**
     * Méthode centrale d'envoi FCM via Firebase Admin SDK
     */
    public function sendFCMNotification(
        string $token,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(
                    Notification::create($title, $body)
                )
                ->withData($data);

            $this->messaging->send($message);
            return true;

        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // Token invalide ou révoqué
            Log::warning('Token FCM invalide pour ' . $token . ': ' . $e->getMessage());
            return false;

        } catch (\Exception $e) {
            Log::error('Erreur envoi notification FCM: ' . $e->getMessage());
            return false;
        }
    }
}
