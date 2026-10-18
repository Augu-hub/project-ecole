<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Scolarite;
use App\Models\Tranche;
use Illuminate\Database\Seeder;

/**
 * Scolarité/tranches pour les classe_annees de TEST uniquement (ne touche pas aux 3 scolarités
 * réelles déjà configurées sur l'année active), + un paiement partiel pour l'élève de démo.
 */
class ScolaritePaiementSeeder extends Seeder
{
    public function run(): void
    {
        $anneePassee = AnneeScolaire::where('libelle', '2024-2025')->first();

        $classeAnneesTest = ClasseAnnee::whereHas('classe.niveau', function ($q) {
            $q->whereIn('nom', ['Petite Section', 'Moyenne Section', 'CP1', 'CE1']);
        })->orWhere(function ($q) use ($anneePassee) {
            if ($anneePassee) {
                $q->where('annee_scolaire_id', $anneePassee->id);
            }
        })->get();

        foreach ($classeAnneesTest as $classeAnnee) {
            $scolarite = Scolarite::firstOrCreate(
                ['classe_annee_id' => $classeAnnee->id],
                ['montant_annuel' => 60000, 'description' => 'Scolarité de test']
            );

            if ($scolarite->tranches()->count() === 0) {
                foreach ([
                    ['libelle' => '1ère tranche', 'montant' => 20000, 'ordre' => 1, 'date_echeance' => $classeAnnee->anneeScolaire->date_debut],
                    ['libelle' => '2ème tranche', 'montant' => 20000, 'ordre' => 2, 'date_echeance' => $classeAnnee->anneeScolaire->date_debut->copy()->addMonths(3)],
                    ['libelle' => '3ème tranche', 'montant' => 20000, 'ordre' => 3, 'date_echeance' => $classeAnnee->anneeScolaire->date_debut->copy()->addMonths(6)],
                ] as $data) {
                    Tranche::create(array_merge($data, ['scolarite_id' => $scolarite->id]));
                }
            }
        }

        // Paiement partiel pour l'élève de démo "PROMU" sur sa classe_annee passée.
        $elevePromu = Eleve::where('matricule', 'TEST-PROMU-001')->first();
        if ($elevePromu) {
            $classeAnneePassee = ClasseAnnee::whereHas('anneeScolaire', fn ($q) => $q->where('libelle', '2024-2025'))
                ->whereHas('classe', fn ($q) => $q->where('suffixe', 'A'))
                ->first();
            $scolaritePassee = $classeAnneePassee ? Scolarite::where('classe_annee_id', $classeAnneePassee->id)->first() : null;
            $premiereTranche = $scolaritePassee ? Tranche::where('scolarite_id', $scolaritePassee->id)->orderBy('ordre')->first() : null;

            if ($premiereTranche && $elevePromu->parentPrincipal) {
                Paiement::firstOrCreate(
                    ['eleve_id' => $elevePromu->id, 'tranche_id' => $premiereTranche->id],
                    [
                        'montant' => $premiereTranche->montant,
                        'date_paiement' => $classeAnneePassee->anneeScolaire->date_debut,
                        'mode_paiement' => 'especes',
                        'reference' => 'TEST-PAIEMENT-001',
                        'parent_id' => $elevePromu->parent_id,
                        'commentaire' => 'Paiement de test',
                    ]
                );
            }
        }
    }
}
