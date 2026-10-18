<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConnexionLog;
use Illuminate\Http\Request;

class ConnexionLogController extends Controller
{
    /**
     * Audit log des connexions parents à l'app mobile (Api\AuthController::login).
     */
    public function index(Request $request)
    {
        $query = ConnexionLog::with('parent')->orderByDesc('created_at');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->whereHas('parent', function ($p) use ($q) {
                $p->where('nom', 'like', "%{$q}%")
                    ->orWhere('prenom', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%");
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('back.pages.connexion-logs.index', compact('logs'));
    }
}
