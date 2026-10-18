<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matiere extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_matiere',
        'description',
    ];

    // Relation avec les classes (via table pivot classe_matiere)
    public function classes()
    {
        return $this->belongsToMany(Classe::class, 'classe_matieres')
            ->withPivot('coefficient', 'enseignant_id')
            ->withTimestamps();
    }

    // Relation avec les notes
    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
