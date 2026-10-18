<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'menus_desactives')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Rubriques du menu fermées à ce compte (clés de App\Support\AccesMenu::RUBRIQUES) ; vide = aucune
            $table->json('menus_desactives')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'menus_desactives')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('menus_desactives');
        });
    }
};
