<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnexionLog;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string',
            'password' => 'required',
        ]);

        // Rechercher l'utilisateur via la table parents
        $parent = \App\Models\Parents::where('telephone', $request->telephone)->first();

        if (! $parent) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro de téléphone non trouvé',
            ], 401);
        }

        $user = User::find($parent->user_id);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe incorrect',
            ], 401);
        }

        if (! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        ConnexionLog::create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'actif' => $user->actif,
                'email_verified_at' => $user->email_verified_at,
            ],
            'parent' => [
                'id' => $parent->id,
                'telephone' => $parent->telephone,
                'adresse' => $parent->adresse ?? null,
                'nom' => $parent->nom,
                'prenom' => $parent->prenom,
                // Ajoutez d'autres champs du parent si nécessaire
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'actif' => (bool) $user->actif,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès',
        ]);
    }
}
