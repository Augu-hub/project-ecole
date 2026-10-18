<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 20)->unique();
            $table->string('nom', 50);
            $table->string('prenom', 50);
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('classe_annee_id')->constrained('classe_annees');
            $table->foreignId('parent_id')->constrained('parents'); // parent principal
            $table->date('date_inscription');
            $table->enum('statut', ['actif', 'inactif', 'ancien'])->default('actif');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('eleves');
    }
};
