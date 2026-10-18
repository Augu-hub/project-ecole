<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreneauHoraire extends Model
{
    use HasFactory;

    protected $table = 'creneaux_horaires';

    protected $fillable = ['heure_debut', 'heure_fin', 'ordre'];

    // Pas de cast Carbon/datetime volontairement : heure_debut/heure_fin sont comparées
    // directement dans des requêtes SQL (détection de chevauchement, voir
    // EmploiDuTempsConflitService) et doivent rester au format brut "HH:MM:SS" de la
    // colonne TIME MySQL, sans conversion qui casserait ces comparaisons.

    public function emploisDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    /**
     * Format d'affichage "08:00" à partir de la valeur brute "08:00:00".
     */
    public function getHeureDebutCourteAttribute(): string
    {
        return substr($this->heure_debut, 0, 5);
    }

    public function getHeureFinCourteAttribute(): string
    {
        return substr($this->heure_fin, 0, 5);
    }
}
