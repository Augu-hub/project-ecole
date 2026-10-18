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
        Schema::create('communiques', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('contenu');
            $table->enum('type', ['urgent', 'evenement', 'general', 'academique'])->default('general');
            $table->date('date_publication');
            $table->date('date_expiration')->nullable();
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('communique_classe_annee', function (Blueprint $table) {
            $table->foreignId('communique_id')->constrained('communiques')->onDelete('cascade');
            $table->foreignId('classe_annee_id')->constrained('classe_annees')->onDelete('cascade');
            $table->primary(['communique_id', 'classe_annee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communique_classe_annee');
        Schema::dropIfExists('communiques');
    }
};
