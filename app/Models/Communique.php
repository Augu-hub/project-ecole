<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Communique extends Model
{
    protected $fillable = [
        'titre', 'contenu', 'type', 'date_publication',
        'date_expiration', 'actif', 'created_by',
    ];

    protected $casts = [
        'date_publication' => 'date',
        'date_expiration'  => 'date',
        'actif'            => 'boolean',
    ];

    public function classesAnnees()
    {
        return $this->belongsToMany(ClasseAnnee::class, 'communique_classe_annee');
    }

    public function createur()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getLabelTypeAttribute(): string
    {
        return match($this->type) {
            'urgent'      => 'Urgent',
            'evenement'   => 'Événement',
            'academique'  => 'Académique',
            default       => 'Général',
        };
    }

    public function getCouleurTypeAttribute(): string
    {
        return match($this->type) {
            'urgent'    => 'danger',
            'evenement' => 'info',
            'academique'=> 'primary',
            default     => 'secondary',
        };
    }
}
