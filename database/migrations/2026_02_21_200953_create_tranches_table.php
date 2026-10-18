<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('tranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scolarite_id')->constrained('scolarites')->onDelete('cascade');
            $table->string('libelle', 100);
            $table->date('date_echeance');
            $table->decimal('montant', 10, 2);
            $table->integer('ordre');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('tranches');
    }
};