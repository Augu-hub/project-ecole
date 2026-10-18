<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deux écoles/déploiements peuvent modéliser "plusieurs interrogations par période"
 * différemment : soit un seul type "Interrogation" avec plusieurs notes (via notes.numero),
 * soit plusieurs types distincts "Interrogation 1/2/3/4" (chacun avec une seule note par
 * période). Ces deux colonnes couvrent les deux cas sans supposer lequel est en place :
 *
 * - pre_moyenner : les valeurs de ce type doivent être moyennées ENTRE ELLES avant d'entrer
 *   dans le calcul de la moyenne de matière (au lieu de compter chaque valeur individuellement).
 * - groupe_moyenne : quand PLUSIEURS types (ex. les 4 lignes "Interrogation N") doivent être
 *   combinés en UNE seule moyenne d'interro, ils partagent la même valeur ici. Un type seul
 *   dans son groupe (Devoir, Composition...) n'a pas besoin de cette colonne (NULL).
 *
 * Détection par le NOM ("Interrogation%") plutôt que par un code fixe : les codes existants
 * varient beaucoup selon comment chaque type a été créé (INT, INT2, "INT 3", "INT 4"...).
 *
 * Rétrocompatible : DEFAULT false / NULL ne change le comportement d'aucun type tant qu'on ne
 * l'active pas explicitement ici. L'ajout du type "Composition" est conditionnel : on ne
 * l'ajoute QUE si aucun type de ce nom n'existe déjà (protège les bases qui l'ont déjà, sous
 * un nom ou un code différent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_notes', function (Blueprint $table) {
            $table->boolean('pre_moyenner')->default(false)->after('max_par_periode');
            $table->string('groupe_moyenne')->nullable()->after('pre_moyenner');
        });

        $interros = DB::table('type_notes')->where('nom', 'LIKE', 'Interrogation%')->get();

        if ($interros->isNotEmpty()) {
            DB::table('type_notes')->whereIn('id', $interros->pluck('id'))
                ->update(['pre_moyenner' => true]);
        }

        // Un seul groupe partagé seulement si PLUSIEURS lignes représentent des interros
        // (sinon un type seul n'a besoin d'aucun groupe pour être pré-moyenné : voir
        // BulletinService::moyenneMatiereDetail, qui traite un type isolé comme son propre
        // groupe implicite).
        if ($interros->count() > 1) {
            DB::table('type_notes')->whereIn('id', $interros->pluck('id'))
                ->update(['groupe_moyenne' => 'interrogation']);
        }

        $aDejaComposition = DB::table('type_notes')
            ->where(fn ($q) => $q->where('nom', 'LIKE', 'Composition%')->orWhere('code', 'LIKE', 'COMP%'))
            ->exists();

        if (! $aDejaComposition) {
            DB::table('type_notes')->insert([
                'nom' => 'Composition', 'code' => 'COMPO', 'max_par_periode' => 1,
                'pre_moyenner' => false, 'groupe_moyenne' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('type_notes', function (Blueprint $table) {
            $table->dropColumn(['pre_moyenner', 'groupe_moyenne']);
        });
    }
};
