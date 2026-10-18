<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('classe_annee_id')->nullable()->after('eleve_id')
                ->constrained('classe_annees')->nullOnDelete();
        });

        // Backfill : on suppose que chaque note a été prise dans la classe ACTUELLE de l'élève
        // (c'est déjà le comportement implicite du code existant, donc pas de régression visible).
        // Ne corrige pas rétroactivement les bulletins d'élèves déjà promus avant cette migration.
        // Sous-requête plutôt qu'un UPDATE...JOIN (syntaxe MySQL) pour rester portable vers SQLite (tests).
        DB::statement(
            'UPDATE notes
             SET classe_annee_id = (SELECT classe_annee_id FROM eleves WHERE eleves.id = notes.eleve_id)
             WHERE classe_annee_id IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classe_annee_id');
        });
    }
};
