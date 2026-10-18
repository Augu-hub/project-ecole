<?php

namespace App\Services;

use App\Models\ClasseAnnee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reprise des matières d'une classe depuis une année scolaire précédente.
 *
 * À chaque rentrée les classes sont rouvertes (classe_annees), mais leurs matières, coefficients
 * et enseignants (classe_matieres) ne suivent pas : tant qu'une classe n'a aucune matière, aucune
 * note ne peut y être saisie. La reprise recopie ces lignes depuis la même classe (même classe_id)
 * dans l'année scolaire antérieure la plus récente où elle avait des matières.
 *
 * Seule une classe SANS AUCUNE matière est complétée : rien n'est jamais remplacé ni mélangé à une
 * configuration déjà commencée, et rejouer la reprise ne crée donc aucun doublon.
 */
class RepriseMatieresService
{
    /**
     * Ce que la reprise ferait pour ces classes, sans rien modifier.
     *
     * Ne sont retenues que les classes qui n'ont aucune matière et pour lesquelles une année
     * antérieure a des matières à reprendre. L'état est relu en base : la liste reçue peut dater.
     *
     * @param  iterable<ClasseAnnee>  $classesAnnees
     * @return Collection<int, array{cible: ClasseAnnee, source: ClasseAnnee, matieres: int}> indexée par l'id de la classe à compléter
     */
    public function apercu(iterable $classesAnnees): Collection
    {
        $ids = collect($classesAnnees)->map(fn (ClasseAnnee $classeAnnee) => $classeAnnee->id);

        $cibles = ClasseAnnee::with('classe.niveau', 'anneeScolaire')
            ->whereIn('id', $ids)
            ->whereDoesntHave('matieres')
            ->get();

        if ($cibles->isEmpty()) {
            return collect();
        }

        // Les mêmes classes, toutes années confondues, là où elles ont des matières
        $candidates = ClasseAnnee::with('anneeScolaire')
            ->whereIn('classe_id', $cibles->pluck('classe_id')->unique())
            ->whereHas('matieres')
            ->withCount('matieres')
            ->get()
            ->groupBy('classe_id');

        $apercu = collect();

        // Dans l'ordre reçu, pour un affichage stable
        foreach ($ids as $id) {
            $cible = $cibles->find($id);
            if (! $cible) {
                continue;
            }

            // Année antérieure la plus récente : la plus grande date de début avant celle de la classe à compléter
            $source = $candidates->get($cible->classe_id, collect())
                ->filter(fn (ClasseAnnee $candidate) => $candidate->anneeScolaire->date_debut->lt($cible->anneeScolaire->date_debut))
                ->sortBy([
                    fn (ClasseAnnee $a, ClasseAnnee $b) => $b->anneeScolaire->date_debut <=> $a->anneeScolaire->date_debut,
                    fn (ClasseAnnee $a, ClasseAnnee $b) => $b->id <=> $a->id,
                ])
                ->first();

            if ($source) {
                $apercu->put($cible->id, ['cible' => $cible, 'source' => $source, 'matieres' => $source->matieres_count]);
            }
        }

        return $apercu;
    }

    /**
     * Recopie, pour chaque classe sans matière, les matières (avec coefficient et enseignant) de sa
     * source. Tout se fait dans une seule transaction : en cas d'erreur, aucune classe n'est modifiée.
     *
     * @param  iterable<ClasseAnnee>  $classesAnnees
     * @return array{classes: int, matieres: int, annees: list<string>} classes complétées, matières
     *                                                                  rattachées, années d'origine
     */
    public function reprendre(iterable $classesAnnees): array
    {
        return DB::transaction(function () use ($classesAnnees) {
            $classes = 0;
            $matieres = 0;
            $annees = [];
            $maintenant = now();

            // L'aperçu est recalculé ici : une classe complétée entre-temps n'est plus touchée
            foreach ($this->apercu($classesAnnees) as $reprise) {
                $lignes = DB::table('classe_matieres')
                    ->where('classe_annee_id', $reprise['source']->id)
                    ->get(['matiere_id', 'coefficient', 'enseignant_id'])
                    ->map(fn ($ligne) => [
                        'classe_annee_id' => $reprise['cible']->id,
                        'matiere_id' => $ligne->matiere_id,
                        'coefficient' => $ligne->coefficient,
                        'enseignant_id' => $ligne->enseignant_id,
                        'created_at' => $maintenant,
                        'updated_at' => $maintenant,
                    ]);

                // « insert or ignore » : même si deux personnes lancent la reprise en même temps,
                // une ligne déjà présente n'est ni dupliquée ni modifiée
                $rattachees = DB::table('classe_matieres')->insertOrIgnore($lignes->all());

                if ($rattachees > 0) {
                    $classes++;
                    $matieres += $rattachees;
                    $annees[] = $reprise['source']->anneeScolaire->libelle;
                }
            }

            return ['classes' => $classes, 'matieres' => $matieres, 'annees' => array_values(array_unique($annees))];
        });
    }
}
