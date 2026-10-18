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
        Schema::create('certificats_scolarite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->foreignId('classe_annee_id')->constrained('classe_annees'); // classe au moment de l'émission
            $table->string('numero_unique', 30)->unique();
            $table->string('motif')->nullable();
            $table->foreignId('genere_par_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificats_scolarite');
    }
};
