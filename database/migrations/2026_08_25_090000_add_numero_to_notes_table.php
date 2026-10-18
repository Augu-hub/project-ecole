<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jusqu'ici une seule note pouvait exister par (élève, matière, période, type_note) :
 * saisir une 2e interrogation sur la même période écrasait la 1re silencieusement.
 * Cette colonne distingue les occurrences (interro n°1, n°2... jusqu'à max_par_periode)
 * pour que plusieurs interros/devoirs cohabitent réellement en base.
 *
 * Rétrocompatible : DEFAULT 1 s'applique instantanément aux 359 notes déjà en prod
 * (elles deviennent toutes "occurrence n°1"), aucune donnée n'est perdue ni à modifier
 * manuellement. L'unicité (eleve,matiere,periode,type_note,numero) est garantie car le
 * triplet (eleve,matiere,periode,type_note) était déjà unique avant cette migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->unsignedTinyInteger('numero')->default(1)->after('type_note_id');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->unique(
                ['eleve_id', 'matiere_id', 'periode_id', 'type_note_id', 'numero'],
                'notes_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique('notes_slot_unique');
            $table->dropColumn('numero');
        });
    }
};
