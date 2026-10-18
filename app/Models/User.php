<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'fcm_token',
        'role',
        'actif',
        'superieur_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Rubriques du menu fermées à ce compte (voir App\Support\AccesMenu)
            'menus_desactives' => 'array',
        ];
    }

    /**
     * Libellé de chaque rôle, tel qu'il est affiché à l'utilisateur.
     */
    public const ROLES = [
        'admin' => 'Administrateur',
        'directeur' => 'Directeur',
        'censeur' => 'Censeur',
        'econome' => 'Économe',
        'prefet' => 'Préfet',
        'surveillant' => 'Surveillant',
        'saisisseur' => 'Saisisseur de note',
        'enseignant' => 'Enseignant',
        'parent' => 'Parent',
    ];

    public function getRoleLibelleAttribute(): string
    {
        return self::ROLES[$this->role] ?? 'Utilisateur';
    }

    /**
     * Nom de la route du tableau de bord de ce compte : celui de l'enseignant, sinon celui de l'administration.
     */
    public function routeTableauDeBord(): string
    {
        return $this->role === 'enseignant' ? 'enseignant.dashboard' : 'admin.dashboard';
    }

    public function parent()
    {
        return $this->hasOne(Parents::class, 'user_id');
    }

    public function enseignant()
    {
        return $this->hasOne(Enseignant::class, 'user_id');
    }

    /**
     * Supérieur hiérarchique (ex : le préfet d'un surveillant).
     */
    public function superieur()
    {
        return $this->belongsTo(User::class, 'superieur_id');
    }

    /**
     * Membres du personnel placés sous cet utilisateur (ex : les surveillants d'un préfet).
     */
    public function equipe()
    {
        return $this->hasMany(User::class, 'superieur_id');
    }
}
