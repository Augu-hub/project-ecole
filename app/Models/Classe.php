<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    use HasFactory;

    protected $fillable = ['niveau_id', 'suffixe'];

    public function niveau()
    {
        return $this->belongsTo(Niveau::class);
    }

    public function classeAnnees()
    {
        return $this->hasMany(ClasseAnnee::class);
    }

    // Accesseur pour obtenir le nom complet de la classe (ex: "6ème A")
    public function getFullNameAttribute()
    {
        return $this->niveau->nom . ($this->suffixe ? ' ' . $this->suffixe : '');
    }

    /**
     * Scope pour trier par niveau et suffixe
     */
    public function scopeOrderByFullName($query)
    {
        return $query->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
                     ->orderBy('niveaux.ordre', 'asc')
                     ->orderBy('classes.suffixe', 'asc')
                     ->select('classes.*');
    }

    /**
     * Scope pour trier par niveau et suffixe (sans jointure si déjà faite)
     */
    public function scopeOrderByNiveauAndSuffixe($query, $direction = 'asc')
    {
        return $query->with('niveau')
                     ->orderBy(Niveau::select('ordre')->whereColumn('niveaux.id', 'classes.niveau_id'), $direction)
                     ->orderBy('suffixe', $direction);
    }
}