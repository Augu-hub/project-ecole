<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'classe_annee_id',
        'matiere_id',
        'periode_id',
        'type_note_id',
        'numero',
        'enseignant_id',
        'valeur',
        'commentaire',
    ];

    protected $casts = [
        'valeur' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONS DE BASE
    // ─────────────────────────────────────────────────────────────

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function periode()
    {
        return $this->belongsTo(Periode::class);
    }

    public function typeNote()
    {
        return $this->belongsTo(TypeNote::class);
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class);
    }

    // ─────────────────────────────────────────────────────────────
    // RELATIONS SECOND AIRES (pour les filtres avancés)
    // ─────────────────────────────────────────────────────────────

    /**
     * Classe/année dans laquelle cette note précise a été prise (colonne classe_annee_id).
     * Ne PAS déduire cela de la classe courante de l'élève : un élève promu doit garder
     * l'historique de ses notes rattaché à la classe/année où elles ont été saisies,
     * avec les coefficients qui étaient en vigueur à ce moment-là.
     */
    public function classeAnnee()
    {
        return $this->belongsTo(ClasseAnnee::class);
    }

    /**
     * Accès à la classe via l'élève
     */
    public function classe()
    {
        return $this->hasOneThrough(
            Classe::class,
            Eleve::class,
            'id',
            'id',
            'eleve_id',
            'classe_annee_id'
        )->through('classeAnnee');
    }

    /**
     * Accès à l'année scolaire via l'élève
     */
    public function anneeScolaire()
    {
        return $this->hasOneThrough(
            AnneeScolaire::class,
            Eleve::class,
            'id',
            'id',
            'eleve_id',
            'classe_annee_id'
        )->through('classeAnnee');
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES POUR LES FILTRES
    // ─────────────────────────────────────────────────────────────

    /**
     * Scope pour filtrer par année scolaire
     */
    public function scopeByAnneeScolaire($query, $anneeScolaireId)
    {
        if ($anneeScolaireId) {
            return $query->whereHas('eleve.classeAnnee', function ($q) use ($anneeScolaireId) {
                $q->where('annee_scolaire_id', $anneeScolaireId);
            });
        }

        return $query;
    }

    /**
     * Scope pour filtrer par classe
     */
    public function scopeByClasse($query, $classeId)
    {
        if ($classeId) {
            return $query->whereHas('eleve.classeAnnee', function ($q) use ($classeId) {
                $q->where('classe_id', $classeId);
            });
        }

        return $query;
    }

    /**
     * Scope pour filtrer par période
     */
    public function scopeByPeriode($query, $periodeId)
    {
        if ($periodeId) {
            return $query->where('periode_id', $periodeId);
        }

        return $query;
    }

    /**
     * Scope pour filtrer par matière
     */
    public function scopeByMatiere($query, $matiereId)
    {
        if ($matiereId) {
            return $query->where('matiere_id', $matiereId);
        }

        return $query;
    }

    /**
     * Scope pour filtrer par type de note
     */
    public function scopeByTypeNote($query, $typeNoteId)
    {
        if ($typeNoteId) {
            return $query->where('type_note_id', $typeNoteId);
        }

        return $query;
    }

    /**
     * Scope pour filtrer par plage de dates
     */
    public function scopeByDateRange($query, $range)
    {
        if (! $range) {
            return $query;
        }

        switch ($range) {
            case 'today':
                return $query->whereDate('created_at', today());
            case 'yesterday':
                return $query->whereDate('created_at', today()->subDay());
            case 'this_week':
                return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            case 'last_week':
                return $query->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);
            case 'this_month':
                return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
            case 'last_month':
                return $query->whereMonth('created_at', now()->subMonth()->month)
                    ->whereYear('created_at', now()->subMonth()->year);
            default:
                return $query;
        }
    }

    /**
     * Scope pour rechercher par élève (nom, prénom, matricule)
     */
    public function scopeSearchEleve($query, $search)
    {
        if ($search) {
            return $query->whereHas('eleve', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Scope pour filtrer par note minimale
     */
    public function scopeNoteMin($query, $min)
    {
        if ($min !== null && $min !== '') {
            return $query->where('valeur', '>=', (float) $min);
        }

        return $query;
    }

    /**
     * Scope pour filtrer par note maximale
     */
    public function scopeNoteMax($query, $max)
    {
        if ($max !== null && $max !== '') {
            return $query->where('valeur', '<=', (float) $max);
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES POUR LES TRIS
    // ─────────────────────────────────────────────────────────────

    /**
     * Scope pour trier par date de création
     */
    public function scopeOrderByDate($query, $direction = 'desc')
    {
        return $query->orderBy('created_at', $direction);
    }

    /**
     * Scope pour trier par classe (niveau + suffixe)
     */
    public function scopeOrderByClasse($query, $direction = 'asc')
    {
        return $query->join('eleves', 'notes.eleve_id', '=', 'eleves.id')
            ->join('classe_annees', 'eleves.classe_annee_id', '=', 'classe_annees.id')
            ->join('classes', 'classe_annees.classe_id', '=', 'classes.id')
            ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
            ->orderBy('niveaux.ordre', $direction)
            ->orderBy('classes.suffixe', $direction)
            ->select('notes.*');
    }

    /**
     * Scope pour trier par élève (nom + prénom)
     */
    public function scopeOrderByEleve($query, $direction = 'asc')
    {
        return $query->join('eleves', 'notes.eleve_id', '=', 'eleves.id')
            ->orderBy('eleves.nom', $direction)
            ->orderBy('eleves.prenom', $direction)
            ->select('notes.*');
    }

    /**
     * Scope pour trier par matière
     */
    public function scopeOrderByMatiere($query, $direction = 'asc')
    {
        return $query->join('matieres', 'notes.matiere_id', '=', 'matieres.id')
            ->orderBy('matieres.nom_matiere', $direction)
            ->select('notes.*');
    }

    /**
     * Scope pour trier par note
     */
    public function scopeOrderByNote($query, $direction = 'desc')
    {
        return $query->orderBy('valeur', $direction);
    }

    /**
     * Scope pour trier par période
     */
    public function scopeOrderByPeriode($query, $direction = 'asc')
    {
        return $query->join('periodes', 'notes.periode_id', '=', 'periodes.id')
            ->orderBy('periodes.created_at', $direction)
            ->select('notes.*');
    }

    // ─────────────────────────────────────────────────────────────
    // ACCESSORS
    // ─────────────────────────────────────────────────────────────

    /**
     * Note formatée avec /20
     */
    public function getFormattedNoteAttribute()
    {
        return number_format($this->valeur, 2).' / 20';
    }

    /**
     * Classe de couleur pour la note
     */
    public function getNoteColorClassAttribute()
    {
        if ($this->valeur >= 16) {
            return 'success';
        }
        if ($this->valeur >= 14) {
            return 'info';
        }
        if ($this->valeur >= 12) {
            return 'primary';
        }
        if ($this->valeur >= 10) {
            return 'warning';
        }

        return 'danger';
    }

    /**
     * Badge HTML pour la note
     */
    public function getNoteBadgeAttribute()
    {
        $class = $this->note_color_class;

        return "<span class='badge bg-{$class}-soft text-{$class} px-3 py-2'>{$this->formatted_note}</span>";
    }

    /**
     * Accès direct à la classe de l'élève
     */
    public function getClasseNameAttribute()
    {
        return $this->classeAnnee?->classe?->full_name ?? 'N/A';
    }

    /**
     * Accès direct à l'année scolaire
     */
    public function getAnneeScolaireLibelleAttribute()
    {
        return $this->classeAnnee?->anneeScolaire?->libelle ?? 'N/A';
    }
}
