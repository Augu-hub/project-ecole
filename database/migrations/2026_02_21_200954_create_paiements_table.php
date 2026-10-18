<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->foreignId('tranche_id')->nullable()->constrained('tranches');
            $table->decimal('montant', 10, 2);
            $table->date('date_paiement');
            $table->enum('mode_paiement', ['especes', 'mobile_money', 'virement', 'carte', 'cheque']);
            $table->string('reference', 50)->nullable();
            $table->foreignId('parent_id')->constrained('parents');
            $table->string('recu_path')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('paiements');
    }
};