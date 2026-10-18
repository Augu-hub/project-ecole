<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Niveau extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'ordre', 'cycle'];

    public function classes()
    {
        return $this->hasMany(Classe::class);
    }

    public function scopeCycle($query, string $cycle)
    {
        return $query->where('cycle', $cycle);
    }
}
