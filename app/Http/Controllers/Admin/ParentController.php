<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parents;
use App\Models\User;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ParentController extends Controller
{
    /**
     * Affiche la liste des parents avec recherche avancée.
     */
    public function index(Request $request)
    {
        $query = Parents::with(['user', 'eleves.classeAnnee.classe.niveau']);

        // Recherche générale (nom, prénom, téléphone, email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // Recherche par nom/prénom de l'enfant
        if ($request->filled('enfant')) {
            $enfant = $request->enfant;
            $query->whereHas('eleves', function ($q) use ($enfant) {
                $q->where('nom', 'like', "%{$enfant}%")
                  ->orWhere('prenom', 'like', "%{$enfant}%");
            });
        }

        // Recherche par classe de l'enfant
        if ($request->filled('classe_id')) {
            $classeId = $request->classe_id;
            $query->whereHas('eleves', function ($q) use ($classeId) {
                $q->where('classe_annee_id', $classeId);
            });
        }

        $parents = $query->orderBy('created_at', 'desc')->paginate(15)->appends($request->query());

        // Classes de l'année active pour le filtre (plus celle déjà filtrée, si elle est d'une autre année)
        $classes = ClasseAnnee::with('classe.niveau', 'anneeScolaire')->deLAnneeActive($request->integer('classe_id'))->get();

        return view('back.pages.parents.index', compact('parents', 'classes'));
    }

    /**
     * Affiche le formulaire d'import.
     */
    public function showForm()
    {
        return view('back.pages.parents.import');
    }

    /**
     * Traite l'import Excel.
     */
    public function import(Request $request)
    {
        set_time_limit(0);
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Enlever l'en-tête
            array_shift($rows);

            $imported = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                // Ordre attendu : NOM, PRÉNOM, TÉLÉPHONE, WHATSAPP, PROFESSION, ADRESSE
                if (count($row) < 3) continue;

                $nom = trim($row[0] ?? '');
                $prenom = trim($row[1] ?? '');
                $telephone = trim($row[2] ?? '');
                $whatsapp = trim($row[3] ?? '');
                $profession = trim($row[4] ?? '');
                $adresse = trim($row[5] ?? '');

                if (empty($nom) || empty($prenom) || empty($telephone)) {
                    $skipped++;
                    continue;
                }

                // Vérifier l'unicité du téléphone
                if (Parents::where('telephone', $telephone)->exists()) {
                    $skipped++;
                    continue;
                }

                // Générer un email unique
                $email = $this->generateEmail($prenom, $nom);

                // Créer l'utilisateur
                $user = User::create([
                    'name'     => $prenom . ' ' . $nom,
                    'email'    => $email,
                    'password' => Hash::make('password123'),
                    'role'     => 'parent',
                    'actif'    => true,
                ]);

                // Créer le parent
                Parents::create([
                    'user_id'    => $user->id,
                    'nom'        => $nom,
                    'prenom'     => $prenom,
                    'telephone'  => $telephone,
                    'whatsapp'   => $whatsapp ?: null,
                    'profession' => $profession ?: null,
                    'adresse'    => $adresse ?: null,
                ]);

                $imported++;
            }

            return redirect()->back()->with('success', "Import terminé : $imported parents importés, $skipped ignorés.");
        } catch (\Exception $e) {
            Log::error('Erreur import parents : ' . $e->getMessage());
            return redirect()->back()->with('error', "L'import des parents a échoué. Vérifiez que le fichier respecte le modèle (nom, prénom, téléphone…) puis réessayez.");
        }
    }

    /**
     * Affiche le formulaire d'édition.
     */
    public function edit(Parents $parent)
    {
        $parent->load('user');
        return view('back.pages.parents.edit', compact('parent'));
    }

    /**
     * Met à jour les informations du parent.
     */
    public function update(Request $request, Parents $parent)
    {
        $validated = $request->validate([
            'nom'       => 'required|string|max:50',
            'prenom'    => 'required|string|max:50',
            'telephone' => 'required|string|max:20|unique:parents,telephone,' . $parent->id,
            'whatsapp'  => 'nullable|string|max:20',
            'profession'=> 'nullable|string|max:100',
            'adresse'   => 'nullable|string',
            'email'     => 'nullable|email|max:255|unique:users,email,' . $parent->user_id,
        ]);

        $parent->update([
            'nom'       => $validated['nom'],
            'prenom'    => $validated['prenom'],
            'telephone' => $validated['telephone'],
            'whatsapp'  => $validated['whatsapp'],
            'profession'=> $validated['profession'],
            'adresse'   => $validated['adresse'],
        ]);

        if (!empty($validated['email'])) {
            $parent->user->update(['email' => $validated['email']]);
        }

        return redirect()->route('admin.parents.index')->with('success', 'Parent mis à jour avec succès.');
    }

    /**
     * Formulaire de réinitialisation du mot de passe.
     */
    public function resetPasswordForm(Parents $parent)
    {
        return view('back.pages.parents.reset-password', compact('parent'));
    }

    /**
     * Traite la réinitialisation du mot de passe.
     */
    public function resetPassword(Request $request, Parents $parent)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $parent->user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.parents.index')->with('success', 'Mot de passe réinitialisé avec succès.');
    }

    /**
     * Supprime un parent et son utilisateur associé.
     */
    public function destroy(Parents $parent)
    {
        $parent->user()->delete();
        $parent->delete();
        return redirect()->route('admin.parents.index')->with('success', 'Parent supprimé avec succès.');
    }

    /**
     * Génère un email unique.
     */
    private function generateEmail($prenom, $nom)
    {
        $base = strtolower(trim($prenom) . '.' . trim($nom));
        $base = preg_replace('/[^a-z0-9.]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $base));
        $email = $base . '@example.com';
        $i = 1;
        while (User::where('email', $email)->exists()) {
            $email = $base . $i . '@example.com';
            $i++;
        }
        return $email;
    }
}