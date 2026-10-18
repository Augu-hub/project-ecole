<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('payable_type')->nullable()->after('tranche_id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
            $table->index(['payable_type', 'payable_id']);
        });

        // Backfill : les paiements existants sont tous des paiements de scolarité (Tranche).
        // tranche_id est CONSERVÉ (double écriture) pour ne rien casser côté lecture existante
        // ($paiement->tranche continue de fonctionner partout : reçu PDF, API mobile...).
        DB::table('paiements')
            ->whereNotNull('tranche_id')
            ->update([
                'payable_type' => \App\Models\Tranche::class,
                'payable_id' => DB::raw('tranche_id'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropIndex(['payable_type', 'payable_id']);
            $table->dropColumn(['payable_type', 'payable_id']);
        });
    }
};
