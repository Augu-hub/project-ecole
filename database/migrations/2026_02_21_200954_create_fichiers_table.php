<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('fichiers', function (Blueprint $table) {
            $table->id();
            $table->string('nom_fichier');
            $table->string('chemin_fichier', 500);
            $table->string('type_fichier', 50)->nullable();
            $table->integer('taille')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('eleve_id')->nullable()->constrained('eleves');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('fichiers');
    }
};