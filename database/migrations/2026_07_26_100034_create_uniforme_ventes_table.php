<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uniforme_ventes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uniforme_id')->constrained('uniformes');
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->unsignedInteger('quantite');
            $table->decimal('prix_unitaire', 10, 2); // snapshot du prix catalogue au moment de la vente
            $table->decimal('montant_total', 10, 2);
            $table->date('date_vente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uniforme_ventes');
    }
};
