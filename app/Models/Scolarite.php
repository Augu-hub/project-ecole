<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scolarite extends Model
{
    use HasFactory;

    protected $fillable = [
        'classe_annee_id',
        'montant_annuel',
        'description',
    ];

    public function classeAnnee()
    {
        return $this->belongsTo(ClasseAnnee::class);
    }

    public function tranches()
    {
        return $this->hasMany(Tranche::class);
    }
}