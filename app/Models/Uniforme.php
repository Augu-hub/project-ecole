<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uniforme extends Model
{
    use HasFactory;

    protected $table = 'uniformes';

    protected $fillable = ['type', 'taille', 'prix', 'quantite_stock'];

    public function ventes()
    {
        return $this->hasMany(UniformeVente::class);
    }

    public function getEnRuptureAttribute(): bool
    {
        return $this->quantite_stock <= 0;
    }
}
