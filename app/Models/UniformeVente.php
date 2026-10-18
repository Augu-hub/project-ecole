<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniformeVente extends Model
{
    use HasFactory;

    protected $table = 'uniforme_ventes';

    protected $fillable = [
        'uniforme_id',
        'eleve_id',
        'quantite',
        'prix_unitaire',
        'montant_total',
        'date_vente',
    ];

    public function uniforme()
    {
        return $this->belongsTo(Uniforme::class);
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function paiements()
    {
        return $this->morphMany(Paiement::class, 'payable');
    }

    public function getMontantPayeAttribute(): float
    {
        return (float) $this->paiements()->sum('montant');
    }

    public function getStatutAttribute(): string
    {
        $paye = $this->montant_paye;

        if ($paye <= 0) {
            return 'impaye';
        }

        return $paye >= $this->montant_total ? 'paye' : 'partiel';
    }
}
