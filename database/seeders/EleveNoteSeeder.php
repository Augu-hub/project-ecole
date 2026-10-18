<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Parents;
use App\Models\Periode;
use App\Models\TypeNote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Élèves de test (matricules préfixés TEST- pour ne jamais entrer en collision avec les 76
 * élèves réels), dont un élève "PROMU" avec des notes sur DEUX années scolaires différentes :
 * le scénario concret qui permet de vérifier, dans l'admin, que le bulletin d'une année passée
 * garde bien les coefficients de la classe de l'époque (voir BulletinService).
 */
class EleveNoteSeeder extends Seeder
{
    public function run(): void
    {
        $parentUser = User::firstOrCreate(
            ['email' => 'test.parent.eleve@ccpa.test'],
            [
                'name' => 'Test Parent Élève',
                'password' => Hash::make('password'),
                'role' => 'parent',
                'actif' => true,
                'remember_token' => Str::random(10),
                'email_verified_at' => now(),
            ]
        );
        $parent = Parents::firstOrCreate(
            ['user_id' => $parentUser->id],
            ['nom' => 'Test', 'prenom' => 'Parent Élève', 'telephone' => '0100000010']
        );

        $anneeActive = AnneeScolaire::active();
        $anneePassee = AnneeScolaire::where('libelle', '2024-2025')->first();

        $sixiemeB = Classe::whereHas('niveau', fn ($q) => $q->where('nom', 'Sixième'))
            ->where('suffixe', 'B')->first();
        $sixiemeA = Classe::whereHas('niveau', fn ($q) => $q->where('nom', 'Sixième'))
            ->where('suffixe', 'A')->first();

        if (! $anneeActive || ! $anneePassee || ! $sixiemeA || ! $sixiemeB) {
            return; // Dépendances non seedées (AnneeScolaireSeeder / NiveauClasseSeeder doivent tourner avant).
        }

        $classeAnneeActuelle = ClasseAnnee::where('classe_id', $sixiemeB->id)->where('annee_scolaire_id', $anneeActive->id)->first();
        $classeAnneePassee = ClasseAnnee::where('classe_id', $sixiemeA->id)->where('annee_scolaire_id', $anneePassee->id)->first();

        // ── Élève "PROMU" : en Sixième A l'an dernier, en Sixième B cette année ────────────
        $elevePromu = Eleve::firstOrCreate(
            ['matricule' => 'TEST-PROMU-001'],
            [
                'nom' => 'TESTPROMU',
                'prenom' => 'Élève',
                'sexe' => 'M',
                'date_naissance' => '2012-05-10',
                'classe_annee_id' => $classeAnneeActuelle->id,
                'parent_id' => $parent->id,
                'date_inscription' => $anneeActive->date_debut,
                'statut' => 'actif',
            ]
        );

        $this->seedNotesPourAnnee($elevePromu, $classeAnneePassee, $anneePassee);
        $this->seedNotesPourAnnee($elevePromu, $classeAnneeActuelle, $anneeActive);

        // ── Quelques élèves supplémentaires pour peupler les nouveaux niveaux de test ──────
        $niveauxTest = [
            'Petite Section' => 'PS',
            'CP1' => 'CP1',
        ];
        $i = 0;
        foreach ($niveauxTest as $nomNiveau => $code) {
            $i++;
            $niveau = Niveau::where('nom', $nomNiveau)->first();
            if (! $niveau) {
                continue;
            }
            $classe = Classe::where('niveau_id', $niveau->id)->where('suffixe', 'A')->first();
            $classeAnnee = $classe ? ClasseAnnee::where('classe_id', $classe->id)->where('annee_scolaire_id', $anneeActive->id)->first() : null;
            if (! $classeAnnee) {
                continue;
            }

            $eleve = Eleve::firstOrCreate(
                ['matricule' => "TEST-{$code}-001"],
                [
                    'nom' => 'TEST'.strtoupper($code),
                    'prenom' => 'Élève',
                    'sexe' => $i % 2 === 0 ? 'F' : 'M',
                    'date_naissance' => '2019-01-15',
                    'classe_annee_id' => $classeAnnee->id,
                    'parent_id' => $parent->id,
                    'date_inscription' => $anneeActive->date_debut,
                    'statut' => 'actif',
                ]
            );

            $this->seedNotesPourAnnee($eleve, $classeAnnee, $anneeActive);
        }
    }

    private function seedNotesPourAnnee(Eleve $eleve, ?ClasseAnnee $classeAnnee, AnneeScolaire $annee): void
    {
        if (! $classeAnnee) {
            return;
        }

        $periode = Periode::where('annee_scolaire_id', $annee->id)->orderBy('id')->first();
        if (! $periode) {
            return;
        }

        $typeNotes = TypeNote::all();
        $matiereIds = ClasseMatiere::where('classe_annee_id', $classeAnnee->id)->pluck('matiere_id');
        $enseignant = Enseignant::whereHas('user', fn ($q) => $q->where('email', 'test.enseignant@ccpa.test'))->first()
            ?? Enseignant::first();

        foreach ($matiereIds as $matiereId) {
            foreach ($typeNotes as $typeNote) {
                Note::firstOrCreate(
                    [
                        'eleve_id' => $eleve->id,
                        'classe_annee_id' => $classeAnnee->id,
                        'matiere_id' => $matiereId,
                        'periode_id' => $periode->id,
                        'type_note_id' => $typeNote->id,
                    ],
                    [
                        'enseignant_id' => $enseignant->id,
                        'valeur' => rand(80, 190) / 10,
                        'commentaire' => null,
                    ]
                );
            }
        }
    }
}
