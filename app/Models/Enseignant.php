<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'nom', 'prenom', 'telephone', 'matiere_principale'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classeMatieres()
    {
        return $this->hasMany(ClasseMatiere::class);
    }
}
