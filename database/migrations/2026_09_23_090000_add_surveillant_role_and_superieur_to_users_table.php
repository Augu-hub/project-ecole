<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La fonctionnalité "Équipe du préfet" (Admin\EquipeController, User::equipe()/superieur())
 * a été déployée en code sans cette migration : ni le rôle 'surveillant' ni la colonne
 * superieur_id n'existaient réellement en base, la fonctionnalité était donc inutilisable
 * (un préfet ne pouvait pas créer de surveillant : valeur d'enum invalide + colonne absente).
 *
 * Rétrocompatible : l'ENUM ne fait qu'ajouter une valeur (aucune ligne existante n'est
 * concernée), superieur_id est nullable (toutes les lignes existantes restent NULL).
 *
 * Rejouable : sur un poste où la colonne existe déjà (ancienne migration locale
 * 2026_09_17_100000, jamais publiée et remplacée par celle-ci), l'ajout de superieur_id est ignoré.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','enseignant','parent','directeur','censeur','econome','prefet','saisisseur','surveillant') NOT NULL DEFAULT 'parent'");
        } else {
            // SQLite (tests) : l'enum d'origine est une contrainte CHECK limitée à admin/enseignant/parent,
            // qui empêcherait de tester les rôles du personnel.
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->default('parent')->change();
            });
        }

        if (! Schema::hasColumn('users', 'superieur_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Si le supérieur est supprimé, les membres de son équipe restent mais ne sont plus rattachés
                $table->foreignId('superieur_id')->nullable()->after('role')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['superieur_id']);
            $table->dropColumn('superieur_id');
        });

        // ⚠️ Attention : les utilisateurs ayant le rôle surveillant seront en erreur si on revient en arrière
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','enseignant','parent','directeur','censeur','econome','prefet','saisisseur') NOT NULL DEFAULT 'parent'");
        }
    }
};
