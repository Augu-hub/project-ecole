<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajoute les rôles : directeur, censeur, econome, prefet, saisisseur
     * à l'enum existant qui contenait : admin, enseignant, parent
     */
    public function up(): void
    {
        // ALTER ... MODIFY COLUMN est une syntaxe propre à MySQL : elle casse la suite de tests
        // (SQLite en mémoire, voir phpunit.xml). Sur les drivers non-MySQL, la contrainte plus
        // stricte posée par la migration d'origine des users n'empêche pas les tests d'utiliser
        // les rôles déjà couverts (admin, enseignant, parent) ; seuls admin/directeur/etc. sur
        // une vraie base MySQL (prod/local) ont besoin des rôles étendus.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE users
                MODIFY COLUMN role ENUM(
                    'admin',
                    'enseignant',
                    'parent',
                    'directeur',
                    'censeur',
                    'econome',
                    'prefet',
                    'saisisseur'
                ) NOT NULL DEFAULT 'parent'
            ");
        }
    }

    public function down(): void
    {
        // ⚠️ Attention : les utilisateurs ayant les nouveaux rôles
        // seront en erreur si on revient en arrière
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE users
                MODIFY COLUMN role ENUM(
                    'admin',
                    'enseignant',
                    'parent'
                ) NOT NULL DEFAULT 'parent'
            ");
        }
    }
};
