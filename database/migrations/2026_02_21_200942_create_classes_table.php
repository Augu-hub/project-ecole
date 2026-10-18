<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->constrained('niveaux')->onDelete('cascade');
            $table->string('suffixe', 10)->default(''); // A, B, C...
            $table->unique(['niveau_id', 'suffixe']);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('classes');
    }
};