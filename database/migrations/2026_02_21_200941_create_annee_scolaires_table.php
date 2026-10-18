<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('annee_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 9)->unique(); // ex: 2025-2026
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('est_active')->default(false);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('annee_scolaires');
    }
};