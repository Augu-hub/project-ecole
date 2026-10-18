<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Uniforme;
use App\Models\UniformeVente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UniformeController extends Controller
{
    /**
     * Catalogue des uniformes (type + taille + prix + stock), CRUD libre.
     */
    public function index()
    {
        $uniformes = Uniforme::orderBy('type')->orderBy('taille')->get();

        return view('back.pages.uniformes.index', compact('uniformes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validerArticle($request);

        Uniforme::create($validated);

        return back()->with('success', 'Article ajouté au catalogue.');
    }

    public function update(Request $request, Uniforme $uniforme)
    {
        $validated = $this->validerArticle($request, $uniforme);

        $uniforme->update($validated);

        return back()->with('success', 'Article mis à jour.');
    }

    /**
     * Refuse la suppression si des ventes existent déjà pour cet article, afin de ne jamais
     * casser l'historique (les paiements liés référencent la vente, elle-même liée à l'article).
     */
    public function destroy(Uniforme $uniforme)
    {
        if (UniformeVente::where('uniforme_id', $uniforme->id)->exists()) {
            return back()->with('error', 'Impossible de supprimer : des ventes existent déjà pour cet article.');
        }

        $uniforme->delete();

        return back()->with('success', 'Article supprimé.');
    }

    private function validerArticle(Request $request, ?Uniforme $uniforme = null): array
    {
        return $request->validate([
            'type' => [
                'required', 'string', 'max:100',
                Rule::unique('uniformes', 'type')->ignore($uniforme?->id)->where(fn ($q) => $q->where('taille', $request->input('taille'))),
            ],
            'taille' => 'required|string|max:20',
            'prix' => 'required|numeric|min:0',
            'quantite_stock' => 'required|integer',
        ], [
            'type.unique' => 'Un article avec ce type et cette taille existe déjà.',
        ]);
    }
}
