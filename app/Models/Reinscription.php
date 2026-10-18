<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reinscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'annee_scolaire_id',
        'classe_annee_precedente_id',
        'classe_annee_id',
        'type',
        'moyenne_suggestion',
        'decide_par_user_id',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classeAnneePrecedente()
    {
        return $this->belongsTo(ClasseAnnee::class, 'classe_annee_precedente_id');
    }

    public function classeAnnee()
    {
        return $this->belongsTo(ClasseAnnee::class);
    }

    public function decidePar()
    {
        return $this->belongsTo(User::class, 'decide_par_user_id');
    }
}
