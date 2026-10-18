<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->onDelete('cascade');
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->foreignId('periode_id')->constrained('periodes');
            $table->foreignId('type_note_id')->constrained('type_notes');
            $table->foreignId('enseignant_id')->constrained('enseignants');
            $table->decimal('valeur', 4, 1); // sur 20
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('notes');
    }
};