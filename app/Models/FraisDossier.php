<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraisDossier extends Model
{
    use HasFactory;

    protected $fillable = ['classe_annee_id', 'montant', 'description'];

    public function classeAnnee()
    {
        return $this->belongsTo(ClasseAnnee::class);
    }

    public function paiements()
    {
        return $this->morphMany(Paiement::class, 'payable');
    }
}
