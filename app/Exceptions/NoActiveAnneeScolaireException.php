<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoActiveAnneeScolaireException extends Exception
{
    protected $message = 'Aucune année scolaire active. Veuillez en activer une dans Admin > Années scolaires.';

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
