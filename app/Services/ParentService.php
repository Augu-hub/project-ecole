<?php

namespace App\Services;

use App\Models\Parents;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Recherche/création de la fiche Parent (et de son User associé) à partir d'un numéro de
 * téléphone. Extrait de ImportEleveController pour être réutilisé par la création manuelle
 * d'élève (EleveController) sans dupliquer la logique.
 */
class ParentService
{
    public function rechercherOuCreerParent(string $telephone, string $nomEleve, string $prenomEleve): Parents
    {
        $parent = Parents::where('telephone', $telephone)->first();
        if ($parent) {
            return $parent;
        }

        $user = User::create([
            'name' => 'Parent de '.$prenomEleve.' '.$nomEleve,
            'email' => $this->generateEmail('parent.'.$prenomEleve, $nomEleve),
            'password' => Hash::make('password123'),
            'role' => 'parent',
            'actif' => true,
        ]);

        return Parents::create([
            'user_id' => $user->id,
            'nom' => $nomEleve,
            'prenom' => 'Parent de '.$prenomEleve,
            'telephone' => $telephone,
            'whatsapp' => null,
            'profession' => null,
            'adresse' => null,
        ]);
    }

    /**
     * Génère un email unique pour un utilisateur parent.
     */
    public function generateEmail(string $prenom, string $nom): string
    {
        $base = strtolower(trim($prenom).'.'.trim($nom));
        $base = preg_replace('/[^a-z0-9.]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $base));
        $email = $base.'@example.com';
        $i = 1;
        while (User::where('email', $email)->exists()) {
            $email = $base.$i.'@example.com';
            $i++;
        }

        return $email;
    }
}
