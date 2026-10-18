<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type_import', 50);
            $table->string('fichier_source');
            $table->enum('statut', ['succès', 'échec', 'partiel']);
            $table->text('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('import_logs');
    }
};