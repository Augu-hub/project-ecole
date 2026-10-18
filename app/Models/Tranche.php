<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tranche extends Model
{
    use HasFactory;

    protected $fillable = [
        'scolarite_id',
        'libelle',
        'date_echeance',
        'montant',
        'ordre',
    ];

    protected $casts = [
        'date_echeance' => 'date',
    ];

    public function scolarite()
    {
        return $this->belongsTo(Scolarite::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
}