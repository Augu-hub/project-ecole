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
        Schema::table('classe_matieres', function (Blueprint $table) {
            $table->foreignId('enseignant_id')->nullable()->after('coefficient')
                ->constrained('enseignants')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classe_matieres', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enseignant_id');
        });
    }
};
