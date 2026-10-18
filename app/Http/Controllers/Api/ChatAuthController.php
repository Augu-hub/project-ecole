<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parents;
use Illuminate\Http\Request;

/**
 * Identité vérifiée pour le serveur de chat Node (chat-server-ccpa) : celui-ci délègue
 * entièrement la vérification des tokens à Laravel plutôt que de réimplémenter Sanctum.
 */
class ChatAuthController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        $parent = $user->role === 'parent' ? Parents::where('user_id', $user->id)->first() : null;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'type' => $user->role === 'parent' ? 'parent' : 'staff',
            'parent_id' => $parent?->id,
        ]);
    }
}
