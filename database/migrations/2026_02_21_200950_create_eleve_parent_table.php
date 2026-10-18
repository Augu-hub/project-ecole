<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('eleve_parent', function (Blueprint $table) {
            $table->foreignId('eleve_id')->constrained('eleves')->onDelete('cascade');
            $table->foreignId('parent_id')->constrained('parents')->onDelete('cascade');
            $table->string('relation', 30)->nullable(); // père, mère, tuteur
            $table->primary(['eleve_id', 'parent_id']);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('eleve_parent');
    }
};