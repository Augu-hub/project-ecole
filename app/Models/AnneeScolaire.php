<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnneeScolaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'libelle',
        'date_debut',
        'date_fin',
        'est_active',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'est_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('est_active', true);
    }

    public function cantineOffres()
    {
        return $this->hasMany(CantineOffre::class);
    }

    public static function active(): ?self
    {
        return static::query()->active()->first();
    }

    public static function activeOrFail(): self
    {
        return static::active() ?? throw new \App\Exceptions\NoActiveAnneeScolaireException;
    }

    /**
     * Désactive toutes les autres années lorsqu'une année est activée.
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($annee) {
            if ($annee->est_active) {
                // Désactiver toutes les autres années
                static::where('id', '!=', $annee->id)->update(['est_active' => false]);
            }
        });

        static::deleting(function ($annee) {
            // Empêcher la suppression de l'année active ?
            if ($annee->est_active) {
                throw new \Exception("Impossible de supprimer l'année active.");
            }
        });
    }
}
