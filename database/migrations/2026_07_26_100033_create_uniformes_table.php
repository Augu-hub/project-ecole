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
        Schema::create('uniformes', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('taille');
            $table->decimal('prix', 10, 2);
            $table->integer('quantite_stock')->default(0); // informatif, peut devenir négatif
            $table->timestamps();

            $table->unique(['type', 'taille']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uniformes');
    }
};
