<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('nom', 50);
            $table->string('prenom', 50);
            $table->string('telephone', 20)->unique();
            $table->string('whatsapp', 20)->nullable();
            $table->string('profession', 100)->nullable();
            $table->text('adresse')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('parents');
    }
};