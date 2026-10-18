<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 20)->unique(); // "6ème", "5ème"...
            $table->integer('ordre'); // pour trier
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('niveaux');
    }
};