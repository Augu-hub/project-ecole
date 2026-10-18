<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fichier extends Model
{
    protected $fillable = [
        'nom_fichier',
        'chemin_fichier',
        'type_fichier',
        'taille',
        'user_id',
        'eleve_id',
        'libelle',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
