<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('scolarites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_annee_id')->unique()->constrained('classe_annees')->onDelete('cascade');
            $table->decimal('montant_annuel', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('scolarites');
    }
};