<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificatScolarite;
use App\Models\Eleve;
use App\Services\CertificatScolaritePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificatScolariteController extends Controller
{
    public function index(Request $request)
    {
        $query = CertificatScolarite::with('eleve', 'classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire');

        if ($recherche = $request->input('q')) {
            $query->where('numero_unique', 'like', "%{$recherche}%")
                ->orWhereHas('eleve', function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('prenom', 'like', "%{$recherche}%")
                        ->orWhere('matricule', 'like', "%{$recherche}%");
                });
        }

        $certificats = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('back.pages.certificats.index', compact('certificats'));
    }

    public function store(Request $request, Eleve $eleve)
    {
        $request->validate([
            'motif' => 'nullable|string|max:255',
        ]);

        $eleve->load('classeAnnee.classe.niveau');

        if ($eleve->statut !== 'actif') {
            return back()->with('error', 'Le certificat ne peut être généré que pour un élève actif.');
        }

        if (! $eleve->classeAnnee || $eleve->classeAnnee->classe->niveau->cycle !== 'secondaire') {
            return back()->with('error', "Le certificat de scolarité n'est disponible que pour le cycle secondaire.");
        }

        $certificat = CertificatScolarite::create([
            'eleve_id' => $eleve->id,
            'classe_annee_id' => $eleve->classe_annee_id,
            'numero_unique' => $this->genererNumeroUnique(),
            'motif' => $request->input('motif'),
            'genere_par_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.certificats.download', $certificat)
            ->with('success', 'Certificat généré avec succès.');
    }

    public function download(CertificatScolarite $certificat, CertificatScolaritePdfService $pdfService)
    {
        return $pdfService->download($certificat);
    }

    /**
     * Page publique de vérification (sans authentification) : confirme la validité d'un
     * certificat par son numéro unique, sans exposer de données sensibles au-delà du nom,
     * de la classe et de l'année scolaire.
     */
    public function verifier(string $numero)
    {
        $certificat = CertificatScolarite::with('eleve', 'classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire')
            ->where('numero_unique', $numero)
            ->first();

        return view('certificats.verifier', compact('certificat', 'numero'));
    }

    private function genererNumeroUnique(): string
    {
        do {
            $numero = 'CCPA-CERT-'.strtoupper(Str::random(8));
        } while (CertificatScolarite::where('numero_unique', $numero)->exists());

        return $numero;
    }
}
