<?php

namespace App\Http\Middleware;

use App\Support\AccesMenu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Usage dans les routes :
     *
     *   ->middleware('role:admin')
     *   ->middleware('role:admin,directeur')        ← OU logique
     *
     * Un utilisateur inactif est toujours refusé, de même qu'un compte auquel un administrateur
     * a fermé la rubrique du menu dont la page fait partie (Paramètres > Accès aux menus).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Non connecté
        if (! $user) {
            return redirect()->route('login');
        }

        // Compte désactivé
        if (! $user->actif) {
            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Votre compte a été désactivé.']);
        }

        // Les rôles peuvent être passés comme 'admin,directeur' ou plusieurs arguments
        $allowed = collect($roles)
            ->flatMap(fn($r) => explode(',', $r))
            ->map(fn($r) => trim($r))
            ->filter()
            ->all();

        $message = 'Vous n\'avez pas accès à cette section.';

        if (in_array($user->role, $allowed)) {
            // Rubrique du menu fermée à ce compte : la page est refusée même par son adresse directe
            if (! AccesMenu::fermee($request->route()?->getName(), $user)) {
                return $next($request);
            }

            $message = 'Ce menu n\'est pas activé pour votre compte.';
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
