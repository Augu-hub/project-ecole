<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EleveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $anneeActive = AnneeScolaire::where('est_active', true)->first();

        if (!$anneeActive) {
            return redirect()->back()->with('error', 'Aucune année scolaire active trouvée.');
        }

        $classes = ClasseAnnee::with('classe.niveau')
                    ->where('annee_scolaire_id', $anneeActive->id)
                    ->get();

        $selectedClasseId = $request->input('classe_annee_id');
        $eleves = collect();

        if ($selectedClasseId) {
            $eleves = Eleve::with(['parentPrincipal', 'classeAnnee.classe.niveau'])
                    ->where('classe_annee_id', $selectedClasseId)
                    ->orderBy('nom')
                    ->orderBy('prenom')
                    ->get();
        }

        return view('back.pages.eleves.index', compact('anneeActive', 'classes', 'selectedClasseId', 'eleves'));
    }

    public function create() {}
    public function store(Request $request) {}

    /**
     * Display the specified resource.
     */
    public function show(Eleve $eleve)
    {
        return view('back.pages.eleves.show', compact('eleve'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Eleve $eleve)
    {
        return view('back.pages.eleves.edit', compact('eleve'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Eleve $eleve)
    {
        $request->validate([
            'nom'              => 'required|string|max:50',
            'prenom'           => 'required|string|max:50',
            'sexe'             => 'nullable|string|max:1',
            'date_naissance'   => 'nullable|date',
            'matricule'        => 'required|string|max:20|unique:eleves,matricule,' . $eleve->id,
            'telephone_parent' => 'nullable|string|max:20',
            'nom_parent'       => 'nullable|string|max:50',
            'prenom_parent'    => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();

        try {
            // Mettre à jour les informations de l'élève
            $eleve->update([
                'nom'            => $request->nom,
                'prenom'         => $request->prenom,
                'sexe'           => $request->sexe,
                'date_naissance' => $request->date_naissance,
                'matricule'      => $request->matricule,
            ]);

            $flashType    = 'success';
            $flashMessage = 'Élève modifié avec succès.';

            // Gérer la mise à jour du parent
            if ($request->filled('telephone_parent')) {

                $telephone      = $this->formatTelephone($request->telephone_parent);
                $parentActuel   = $eleve->parentPrincipal;
                $parentExistant = Parents::where('telephone', $telephone)->first();

                if ($parentActuel) {
                    // -----------------------------------------------------------
                    // CAS 1 : L'élève a déjà un parent
                    // -----------------------------------------------------------

                    if ($parentExistant && $parentExistant->id !== $parentActuel->id) {
                        // Le téléphone appartient à un AUTRE parent → réassignation
                        $eleve->update(['parent_id' => $parentExistant->id]);
                        $flashType    = 'info';
                        $flashMessage = 'Élève modifié. Un parent existant avec ce numéro a été trouvé (ID #' . $parentExistant->id . ' — ' . $parentExistant->nom . ' ' . $parentExistant->prenom . '). L\'élève lui a été réassigné.';

                    } else {
                        // Même parent ou téléphone nouveau → mise à jour
                        $updates = ['telephone' => $telephone];
                        if ($request->filled('nom_parent'))        $updates['nom']        = $request->nom_parent;
                        if ($request->filled('prenom_parent'))     $updates['prenom']     = $request->prenom_parent;
                        if ($request->filled('whatsapp_parent'))   $updates['whatsapp']   = $request->whatsapp_parent;
                        if ($request->filled('profession_parent')) $updates['profession'] = $request->profession_parent;
                        if ($request->filled('adresse_parent'))    $updates['adresse']    = $request->adresse_parent;

                        $parentActuel->update($updates);
                        $flashMessage = 'Élève et informations du parent modifiés avec succès.';
                    }

                } else {
                    // -----------------------------------------------------------
                    // CAS 2 : L'élève n'a pas encore de parent
                    // -----------------------------------------------------------

                    if ($parentExistant) {
                        // Parent trouvé par téléphone → rattachement
                        $eleve->update(['parent_id' => $parentExistant->id]);
                        $flashType    = 'info';
                        $flashMessage = 'Élève modifié. Parent existant trouvé (ID #' . $parentExistant->id . ' — ' . $parentExistant->nom . ' ' . $parentExistant->prenom . '). L\'élève lui a été rattaché.';

                    } else {
                        // Aucun parent → création
                        $nomParent    = $request->nom_parent    ?: $eleve->nom;
                        $prenomParent = $request->prenom_parent ?: $eleve->prenom;

                        $user = User::create([
                            'name'     => $nomParent . ' ' . $prenomParent,
                            'email'    => $this->generateUniqueEmail($nomParent, $prenomParent),
                            'password' => Hash::make('password123'),
                            'role'     => 'parent',
                            'actif'    => 1,
                        ]);

                        $nouveauParent = Parents::create([
                            'user_id'    => $user->id,
                            'nom'        => $nomParent,
                            'prenom'     => $prenomParent,
                            'telephone'  => $telephone,
                            'whatsapp'   => $request->whatsapp_parent   ?? null,
                            'profession' => $request->profession_parent ?? null,
                            'adresse'    => $request->adresse_parent    ?? null,
                        ]);

                        $eleve->update(['parent_id' => $nouveauParent->id]);
                        $flashMessage = 'Élève modifié. Nouveau parent créé (ID #' . $nouveauParent->id . ') et rattaché.';
                    }
                }
            }

            DB::commit();

            return redirect()->route('admin.eleves.index')->with($flashType, $flashMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Erreur lors de la modification : ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Eleve $eleve)
    {
        try {
            $eleve->delete();
            return redirect()->route('admin.eleves.index')
                ->with('success', 'Élève supprimé avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la suppression');
        }
    }

    /**
     * Formater le numéro de téléphone au format béninois 229XXXXXXXXX
     */
    private function formatTelephone($telephone)
    {
        $telephone = preg_replace('/[^0-9]/', '', $telephone);

        if (strpos($telephone, '229') === 0) {
            return $telephone;
        }

        if (strpos($telephone, '0') === 0) {
            return '229' . substr($telephone, 1);
        }

        return $telephone;
    }

    /**
     * Générer un email unique pour un parent
     */
    private function generateUniqueEmail($nom, $prenom)
    {
        $base    = strtolower(trim($nom)) . '.' . strtolower(trim($prenom)) . '@parent.ecole.com';
        $email   = $base;
        $counter = 1;

        while (User::where('email', $email)->exists()) {
            $email = strtolower(trim($nom)) . '.' . strtolower(trim($prenom)) . $counter . '@parent.ecole.com';
            $counter++;
        }

        return $email;
    }
}