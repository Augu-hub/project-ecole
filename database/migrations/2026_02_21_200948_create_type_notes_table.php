<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('type_notes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50);
            $table->string('code', 20)->unique();
            $table->unsignedTinyInteger('max_par_periode')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('type_notes');
    }
};