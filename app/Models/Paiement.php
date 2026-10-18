<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'tranche_id',
        'payable_type',
        'payable_id',
        'montant',
        'date_paiement',
        'mode_paiement',
        'reference',
        'parent_id',
        'recu_path',
        'commentaire',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function tranche()
    {
        return $this->belongsTo(Tranche::class);
    }

    /**
     * Cible générique du paiement (Tranche de scolarité, FraisDossier, et futurs modules
     * cantine/uniformes). Pour les paiements de scolarité, tranche_id reste renseigné en
     * parallèle (double écriture) pour la rétrocompatibilité des lectures existantes.
     */
    public function payable()
    {
        return $this->morphTo();
    }

    public function parent()
    {
        return $this->belongsTo(Parents::class, 'parent_id');
    }

    /**
     * Paiements rattachés à une année scolaire, d'après ce qui est payé (tranche, frais de
     * dossier, cantine) et non d'après la classe actuelle de l'élève : un élève réinscrit
     * ne doit pas faire basculer ses anciens paiements sur la nouvelle année. Les uniformes
     * n'ont pas d'année propre, ils suivent donc la classe de l'élève.
     */
    public function scopePourAnnee($query, ?int $anneeScolaireId)
    {
        $classeDeLAnnee = fn ($q) => $q->pourAnnee($anneeScolaireId);

        return $query->where(function ($q) use ($anneeScolaireId, $classeDeLAnnee) {
            $q->whereHas('tranche.scolarite.classeAnnee', $classeDeLAnnee)
                ->orWhereHasMorph('payable', [FraisDossier::class], fn ($p) => $p->whereHas('classeAnnee', $classeDeLAnnee))
                ->orWhereHasMorph('payable', [CantineOffre::class], fn ($p) => $p->where('annee_scolaire_id', $anneeScolaireId))
                ->orWhereHasMorph('payable', [UniformeVente::class], fn ($p) => $p->whereHas('eleve.classeAnnee', $classeDeLAnnee));
        });
    }
}
