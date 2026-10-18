<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->onDelete('cascade');
            $table->string('nom', 50); // "Trimestre 1", etc.
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unique(['annee_scolaire_id', 'nom']);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('periodes');
    }
};