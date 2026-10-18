<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificatScolarite extends Model
{
    use HasFactory;

    protected $table = 'certificats_scolarite';

    protected $fillable = [
        'eleve_id',
        'classe_annee_id',
        'numero_unique',
        'motif',
        'genere_par_user_id',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classeAnnee()
    {
        return $this->belongsTo(ClasseAnnee::class);
    }

    public function generePar()
    {
        return $this->belongsTo(User::class, 'genere_par_user_id');
    }
}
