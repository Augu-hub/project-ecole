<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploiDuTemps extends Model
{
    use HasFactory;

    protected $table = 'emplois_du_temps';

    protected $fillable = [
        'classe_annee_id',
        'matiere_id',
        'jour_semaine',
        'creneau_horaire_id',
        'salle',
    ];

    public function classeAnnee()
    {
        return $this->belongsTo(ClasseAnnee::class);
    }

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function creneauHoraire()
    {
        return $this->belongsTo(CreneauHoraire::class);
    }

    /**
     * classe_matieres a une clé primaire composite (classe_annee_id, matiere_id) et ne peut
     * donc pas être ciblée par une relation belongsTo classique — résolution manuelle.
     */
    public function classeMatiere(): ?ClasseMatiere
    {
        return ClasseMatiere::where('classe_annee_id', $this->classe_annee_id)
            ->where('matiere_id', $this->matiere_id)
            ->first();
    }

    public function enseignant(): ?Enseignant
    {
        return $this->classeMatiere()?->enseignant;
    }
}
