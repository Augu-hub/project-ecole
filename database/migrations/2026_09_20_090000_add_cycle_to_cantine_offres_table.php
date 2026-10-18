<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une dimension "cycle" (primaire/secondaire) à la tarification cantine : jusqu'ici un
 * seul tarif s'appliquait à tous les élèves. Nécessaire pour ouvrir la cantine aux parents du
 * primaire (seule fonctionnalité qui leur est destinée pour l'instant) sans imposer le tarif
 * du secondaire.
 *
 * Rétrocompatible : les lignes existantes ont toutes été créées à une époque où seul le
 * secondaire utilisait l'app — DEFAULT 'secondaire' les rattache donc correctement, sans
 * toucher aux paiements déjà enregistrés (ils pointent vers ces lignes par id, jamais modifié
 * ici). On duplique ensuite chaque tarif secondaire vers le primaire, au même montant pour
 * démarrer (ajustable ensuite dans Admin > Cantine > Tarifs, onglet Primaire) : à ce stade
 * l'école n'a pas confirmé si le tarif diffère selon le cycle.
 *
 * Corrige au passage l'oubli du mois de juin : la génération automatique précédente
 * (CantineTarifService) s'arrêtait à tort en mai alors que l'année scolaire va jusqu'en juin ;
 * on complète les années déjà configurées avec le tarif de mai comme référence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cantine_offres', function (Blueprint $table) {
            $table->enum('cycle', ['primaire', 'secondaire'])->default('secondaire')->after('type');
        });

        // Le nouvel index unique doit être créé AVANT de supprimer l'ancien : MySQL a besoin
        // qu'un index couvrant `annee_scolaire_id` reste présent en permanence pour la clé
        // étrangère vers `annee_scolaires` (sinon "Cannot drop index ... needed in a foreign
        // key constraint").
        Schema::table('cantine_offres', function (Blueprint $table) {
            $table->unique(
                ['annee_scolaire_id', 'mois', 'type', 'cycle'],
                'cantine_offres_slot_unique'
            );
        });

        Schema::table('cantine_offres', function (Blueprint $table) {
            $table->dropUnique('cantine_offres_annee_scolaire_id_mois_type_unique');
        });

        $anneeIds = DB::table('cantine_offres')->distinct()->pluck('annee_scolaire_id');

        foreach ($anneeIds as $anneeId) {
            $secondaire = DB::table('cantine_offres')
                ->where('annee_scolaire_id', $anneeId)
                ->where('cycle', 'secondaire')
                ->get(['mois', 'type', 'montant']);

            // 1) Dupliquer secondaire -> primaire pour les mois déjà configurés.
            foreach ($secondaire as $o) {
                DB::table('cantine_offres')->insertOrIgnore([
                    'annee_scolaire_id' => $anneeId,
                    'mois' => $o->mois,
                    'type' => $o->type,
                    'cycle' => 'primaire',
                    'montant' => $o->montant,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2) Compléter juin (mois 6), absent jusqu'ici, pour les deux cycles — en reprenant
            // le tarif de mai comme référence, comme le ferait normalement une reconduction.
            if ($secondaire->isNotEmpty() && ! $secondaire->contains(fn ($o) => (int) $o->mois === 6)) {
                $ticketMai = $secondaire->first(fn ($o) => (int) $o->mois === 5 && $o->type === 'ticket')?->montant;
                $dejMai = $secondaire->first(fn ($o) => (int) $o->mois === 5 && $o->type === 'petit_dejeuner')?->montant;

                foreach (['primaire', 'secondaire'] as $cycle) {
                    if ($ticketMai !== null) {
                        DB::table('cantine_offres')->insertOrIgnore([
                            'annee_scolaire_id' => $anneeId, 'mois' => 6, 'type' => 'ticket',
                            'cycle' => $cycle, 'montant' => $ticketMai,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    if ($dejMai !== null) {
                        DB::table('cantine_offres')->insertOrIgnore([
                            'annee_scolaire_id' => $anneeId, 'mois' => 6, 'type' => 'petit_dejeuner',
                            'cycle' => $cycle, 'montant' => $dejMai,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('cantine_offres')->where('cycle', 'primaire')->delete();

        Schema::table('cantine_offres', function (Blueprint $table) {
            $table->unique(['annee_scolaire_id', 'mois', 'type'], 'cantine_offres_annee_scolaire_id_mois_type_unique');
        });

        Schema::table('cantine_offres', function (Blueprint $table) {
            $table->dropUnique('cantine_offres_slot_unique');
        });

        Schema::table('cantine_offres', function (Blueprint $table) {
            $table->dropColumn('cycle');
        });
    }
};
