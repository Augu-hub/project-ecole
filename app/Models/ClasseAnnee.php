<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClasseAnnee extends Model
{
    use HasFactory;

    protected $fillable = [
        'classe_id',
        'annee_scolaire_id',
    ];

    /**
     * Relation avec la classe.
     */
    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Relation avec l'année scolaire.
     */
    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Relation many-to-many avec les matières via la table pivot classe_matieres.
     */
    public function matieres()
    {
        return $this->belongsToMany(Matiere::class, 'classe_matieres')
            ->withPivot('coefficient', 'enseignant_id')
            ->withTimestamps();
    }

    /**
     * Relation avec les élèves.
     */
    public function eleves()
    {
        return $this->hasMany(Eleve::class);
    }

    public function scolarite()
    {
        return $this->hasOne(Scolarite::class);
    }

    public function scopePourAnnee($query, ?int $anneeScolaireId)
    {
        return $query->where($query->qualifyColumn('annee_scolaire_id'), $anneeScolaireId);
    }

    /**
     * Classes ouvertes sur l'année scolaire active : base des listes déroulantes.
     * $toujoursInclure garde la classe déjà enregistrée sur une fiche d'une année
     * antérieure, pour que son formulaire d'édition ne la perde pas.
     */
    public function scopeDeLAnneeActive($query, $toujoursInclure = [])
    {
        $ids = array_filter((array) $toujoursInclure);

        return $query->where(function ($q) use ($ids) {
            $q->whereHas('anneeScolaire', fn ($annee) => $annee->active());

            if ($ids) {
                $q->orWhereIn($q->qualifyColumn('id'), $ids);
            }
        });
    }
}
