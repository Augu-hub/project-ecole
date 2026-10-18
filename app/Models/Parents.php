<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parents extends Model
{
    use HasFactory;

    protected $table = 'parents'; // si la table s'appelle parents (pluriel)

    protected $fillable = [
        'user_id',
        'nom',
        'prenom',
        'telephone',
        'whatsapp',
        'profession',
        'adresse',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


   public function eleves()
    {
        return $this->hasMany(Eleve::class, 'parent_id');
    }
}