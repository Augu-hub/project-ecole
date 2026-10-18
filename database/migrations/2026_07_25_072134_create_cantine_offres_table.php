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
        Schema::create('cantine_offres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires');
            $table->unsignedTinyInteger('mois'); // 1 (janvier) à 12 (décembre)
            $table->enum('type', ['ticket', 'petit_dejeuner']);
            $table->decimal('montant', 10, 2);
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'mois', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cantine_offres');
    }
};
