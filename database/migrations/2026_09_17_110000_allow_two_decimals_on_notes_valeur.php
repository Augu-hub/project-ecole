<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La saisie, l'import Excel et l'affichage acceptent 2 décimales (ex : 12.25), mais la colonne
 * n'en stockait qu'une (DECIMAL(4,1)) : MySQL arrondissait silencieusement 12.25 en 12.3,
 * et la fiche de notes imprimée ne correspondait pas à la note saisie.
 *
 * Élargissement sans perte : toutes les notes existantes (1 décimale) restent identiques.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->decimal('valeur', 4, 2)->change();
        });
    }

    public function down(): void
    {
        // ⚠️ Revenir en arrière arrondit les notes à 2 décimales (12.25 → 12.3)
        Schema::table('notes', function (Blueprint $table) {
            $table->decimal('valeur', 4, 1)->change();
        });
    }
};
