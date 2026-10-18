<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('classe_matieres', function (Blueprint $table) {
            $table->foreignId('classe_annee_id')->constrained('classe_annees')->onDelete('cascade');
            $table->foreignId('matiere_id')->constrained('matieres')->onDelete('cascade');
            $table->decimal('coefficient', 3, 1)->default(1);
            $table->primary(['classe_annee_id', 'matiere_id']);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('classe_matieres');
    }
};