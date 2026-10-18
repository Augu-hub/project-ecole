<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reinscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires'); // année CIBLE de la réinscription
            $table->foreignId('classe_annee_precedente_id')->nullable()->constrained('classe_annees');
            $table->foreignId('classe_annee_id')->nullable()->constrained('classe_annees'); // NULL si l'élève ne se réinscrit pas
            $table->enum('type', ['nouveau', 'promu', 'redoublant']);
            $table->decimal('moyenne_suggestion', 5, 2)->nullable();
            $table->foreignId('decide_par_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reinscriptions');
    }
};
