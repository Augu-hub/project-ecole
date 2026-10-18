<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    use HasFactory;

    protected $fillable = [
        'annee_scolaire_id',
        'nom',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    // Accesseur pour affichage formaté
    public function getDateDebutFrAttribute()
    {
        return $this->date_debut->locale('fr')->isoFormat('D MMMM YYYY');
    }

    public function getDateFinFrAttribute()
    {
        return $this->date_fin->locale('fr')->isoFormat('D MMMM YYYY');
    }
}