<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CantineOffre extends Model
{
    use HasFactory;

    protected $table = 'cantine_offres';

    protected $fillable = ['annee_scolaire_id', 'mois', 'type', 'cycle', 'montant'];

    public const MOIS_LABELS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    // Mois réellement couverts par la cantine, dans l'ordre de l'année scolaire
    // (septembre à juin) — juillet/août ne sont jamais générés (vacances).
    public const MOIS_ANNEE_SCOLAIRE = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6];

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function paiements()
    {
        return $this->morphMany(Paiement::class, 'payable');
    }

    public function scopeCycle($query, string $cycle)
    {
        return $query->where('cycle', $cycle);
    }

    public function getMoisLabelAttribute(): string
    {
        return self::MOIS_LABELS[$this->mois] ?? (string) $this->mois;
    }
}
