<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TypeNote extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'code', 'max_par_periode', 'pre_moyenner', 'groupe_moyenne'];

    protected $casts = [
        'pre_moyenner' => 'boolean',
    ];

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Ordre d'affichage des types : interrogations (1, 2, 3…), puis devoirs, puis compositions, puis les autres.
     * Le rang se déduit du nom, les codes variant d'une base à l'autre ; à rang égal, « Interrogation 2 »
     * passe avant « Interrogation 10 » (nom le plus court d'abord).
     */
    public function scopeOrdonnes($query)
    {
        return $query
            ->orderByRaw("CASE WHEN LOWER(nom) LIKE 'interro%' THEN 1 WHEN LOWER(nom) LIKE 'devoir%' THEN 2 WHEN LOWER(nom) LIKE 'compo%' THEN 3 ELSE 4 END")
            ->orderByRaw('LENGTH(nom)')
            ->orderBy('nom');
    }

    /**
     * Nom affiché : un nom saisi tout en majuscules (« DEVOIR ») est présenté comme les autres (« Devoir »).
     * Les sigles courts (« TP ») restent tels quels.
     */
    public function getLibelleAttribute(): string
    {
        $nom = (string) $this->nom;

        return mb_strlen($nom) > 3 && $nom === mb_strtoupper($nom)
            ? Str::ucfirst(Str::lower($nom))
            : $nom;
    }

    /**
     * Case du bordereau de saisie des notes où s'écrit ce type de note, comptée à partir de 0 parmi
     * les 12 cases d'un élève : Int. 1 à Int. 5, Moy. Int., Devoir 1, Devoir 2, Moy. Dv., Compo,
     * Moy. Excel, Moy. Coef. (voir NotesPdfService::COLONNES_BORDEREAU).
     *
     * Null quand le bordereau n'a pas de case pour lui (autre type, 6e interrogation, 3e devoir).
     */
    public function caseDuBordereau(int $numero = 1): ?int
    {
        $nom = Str::lower((string) $this->nom);

        // Selon les bases, le rang est dans le nom (« Interrogation 2 ») ou c'est le numéro de
        // l'évaluation dans la période (« Interrogation » n°2)
        $rang = preg_match('/(\d+)\s*$/', $nom, $trouve) ? (int) $trouve[1] : $numero;

        return match (true) {
            str_starts_with($nom, 'interro') && $rang >= 1 && $rang <= 5 => $rang - 1,
            str_starts_with($nom, 'devoir') && $rang >= 1 && $rang <= 2 => 5 + $rang,
            str_starts_with($nom, 'compo') && $rang === 1 => 9,
            default => null,
        };
    }
}