<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoublonParentController extends Controller
{
    /**
     * Affiche les parents ayant le même numéro de téléphone (doublons)
     */
    public function index(Request $request)
    {
        // Trouver les téléphones qui apparaissent plus d'une fois
        $telephonesDoublons = Parents::select('telephone', DB::raw('COUNT(*) as total'))
            ->groupBy('telephone')
            ->having('total', '>', 1)
            ->pluck('telephone');

        // Récupérer tous les parents concernés, groupés par téléphone
        $groupes = [];
        foreach ($telephonesDoublons as $telephone) {
            $parents = Parents::with(['eleves.classeAnnee.classe.niveau', 'user'])
                ->where('telephone', $telephone)
                ->orderBy('id')
                ->get();

            $groupes[] = [
                'telephone' => $telephone,
                'parents'   => $parents,
                'total'     => $parents->count(),
            ];
        }

        return view('back.pages.doublons.index', compact('groupes'));
    }

    /**
     * Fusionner deux parents : garder l'un, réassigner les enfants, supprimer l'autre
     */
    public function fusionner(Request $request)
    {
        $request->validate([
            'parent_a_garder_id'    => 'required|exists:parents,id',
            'parent_a_supprimer_id' => 'required|exists:parents,id|different:parent_a_garder_id',
        ]);

        $parentAGarder    = Parents::findOrFail($request->parent_a_garder_id);
        $parentASupprimer = Parents::findOrFail($request->parent_a_supprimer_id);

        // Vérifier qu'ils ont bien le même téléphone
        if ($parentAGarder->telephone !== $parentASupprimer->telephone) {
            return redirect()->back()->with('error', 'Ces deux parents n\'ont pas le même numéro de téléphone.');
        }

        DB::beginTransaction();
        try {
            // Réassigner tous les élèves du parent à supprimer vers le parent à garder
            Eleve::where('parent_id', $parentASupprimer->id)
                ->update(['parent_id' => $parentAGarder->id]);

            // Supprimer le compte user associé au parent doublon
            $userASupprimer = User::find($parentASupprimer->user_id);
            $parentASupprimer->delete();
            if ($userASupprimer) {
                $userASupprimer->delete();
            }

            DB::commit();
            return redirect()->route('admin.doublons.index')
                ->with('success', 'Fusion effectuée avec succès. Les enfants ont été réassignés.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erreur lors de la fusion : ' . $e->getMessage());
        }
    }

    /**
     * Réassigner un seul élève vers un autre parent existant
     */
    public function reassignerEleve(Request $request)
    {
        $request->validate([
            'eleve_id'          => 'required|exists:eleves,id',
            'nouveau_parent_id' => 'required|exists:parents,id',
        ]);

        $eleve = Eleve::findOrFail($request->eleve_id);
        $eleve->update(['parent_id' => $request->nouveau_parent_id]);

        return redirect()->route('admin.doublons.index')
            ->with('success', 'Élève réassigné avec succès.');
    }
}