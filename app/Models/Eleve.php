<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eleve extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'sexe',
        'date_naissance',
        'photo',
        'classe_annee_id',
        'parent_id', // parent principal
        'date_inscription',
        'statut',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_inscription' => 'date',
    ];

    /**
     * Relation avec la classe-année (la classe dans une année scolaire donnée)
     */
    public function classeAnnee()
    {
        return $this->belongsTo(ClasseAnnee::class);
    }

    /**
     * Parent principal (responsable principal)
     */
    public function parentPrincipal()
    {
        return $this->belongsTo(Parents::class, 'parent_id');
    }

    /**
     * Autres parents (via table pivot eleve_parent)
     */
    public function parent()
    {
        return $this->belongsTo(Parents::class, 'parent_id');
    }

    /**
     * Notes de l'élève
     */
    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Paiements effectués pour cet élève
     */
    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    /**
     * Fichiers associés (photos, bulletins, etc.)
     */
    public function fichiers()
    {
        return $this->hasMany(Fichier::class);
    }

    /**
     * Historique des inscriptions/réinscriptions (nouveau, promu, redoublant).
     */
    public function reinscriptions()
    {
        return $this->hasMany(Reinscription::class);
    }

    /**
     * Certificats de scolarité générés pour cet élève (historique).
     */
    public function certificatsScolarite()
    {
        return $this->hasMany(CertificatScolarite::class);
    }

    /**
     * Scope pour filtrer par statut actif
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Élèves inscrits dans une classe de l'année scolaire donnée
     */
    public function scopePourAnnee($query, ?int $anneeScolaireId)
    {
        return $query->whereHas('classeAnnee', fn ($q) => $q->pourAnnee($anneeScolaireId));
    }

    /**
     * Accesseur pour le nom complet
     */
    public function getFullNameAttribute()
    {
        return $this->prenom.' '.$this->nom;
    }

    /**
     * Cycle de tarification cantine de l'élève ('primaire' ou 'secondaire' uniquement, ce sont
     * les deux seules valeurs supportées par cantine_offres.cycle). La maternelle est traitée
     * comme le primaire : mêmes offres/tarifs, aucune distinction demandée pour l'instant.
     * Repli sur 'primaire' si le niveau n'est pas renseigné, par prudence (ne jamais exposer
     * par erreur les fonctionnalités secondaire à un élève dont le cycle est inconnu).
     */
    public function getCycleCantineAttribute(): string
    {
        $cycleNiveau = $this->classeAnnee?->classe?->niveau?->cycle;

        return $cycleNiveau === 'secondaire' ? 'secondaire' : 'primaire';
    }
}
