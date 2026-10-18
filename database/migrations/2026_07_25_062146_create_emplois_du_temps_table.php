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
        Schema::create('emplois_du_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_annee_id')->constrained('classe_annees');
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->enum('jour_semaine', ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']);
            $table->foreignId('creneau_horaire_id')->constrained('creneaux_horaires');
            $table->string('salle')->nullable();
            $table->timestamps();

            $table->index(['classe_annee_id', 'jour_semaine']);
            $table->index('creneau_horaire_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emplois_du_temps');
    }
};
