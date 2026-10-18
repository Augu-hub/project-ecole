<?php

namespace Database\Seeders\Demo\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * État partagé entre les seeders du jeu de démonstration (identifiants créés, élèves simulés en
 * mémoire, générateur aléatoire déterministe) et utilitaires d'insertion en masse.
 *
 * Enregistré comme singleton dans le conteneur par DemoSeeder : chaque seeder de module le reçoit
 * par injection dans son constructeur.
 */
class DemoContext
{
    /** Graine fixe : deux exécutions produisent exactement les mêmes données. */
    public const GRAINE = 20250915;

    /** Mot de passe de tous les comptes de démonstration. */
    public const MOT_DE_PASSE = 'password';

    /** Taille des lots d'insertion (≈ 11 colonnes × 1 000 lignes, sous les limites SQLite/MySQL). */
    public const TAILLE_LOT = 1000;

    public Rng $rng;

    /** Hash bcrypt calculé une seule fois et partagé par tous les comptes (le hachage coûte cher). */
    public string $hashMotDePasse;

    /** Date de référence (« aujourd'hui ») : aucune donnée n'est datée après. */
    public string $aujourdhui;

    /** @var array<string, array{id: int, libelle: string, debut: string, fin: string}> clé 'precedente' | 'active' */
    public array $annees = [];

    /** @var array<int, list<array{id: int, nom: string, debut: string, fin: string}>> periodes[anneeId] */
    public array $periodes = [];

    /** @var array<string, int> code (INT, DEV, COMPO) => type_note_id */
    public array $typesNotes = [];

    /** @var array<string, int> code matière => matiere_id */
    public array $matieres = [];

    /**
     * Classes : clé « Niveau|Suffixe » => description.
     *
     * @var array<string, array{classe_id: int, niveau: string, niveau_id: int, ordre: int, suffixe: string, profil: string, lv2: ?string, effectif: int, salle: string}>
     */
    public array $classes = [];

    /** @var array<int, array<string, int>> classeAnnees[anneeId][cléClasse] => classe_annee_id */
    public array $classeAnnees = [];

    /** @var array<int, array<string, array{coef: int, heures: int, enseignant_id: int}>> programme[classe_annee_id][code matière] */
    public array $programmes = [];

    /** @var array<string, list<int>> rôle => user ids du personnel */
    public array $personnel = [];

    /** @var array<string, int> comptes nommés (admin, directeur, ...) => user id */
    public array $comptes = [];

    /** @var array<int, array{user_id: int, nom: string, prenom: string, matiere: string}> */
    public array $enseignants = [];

    /**
     * Élèves simulés : id => fiche (voir EffectifsSeeder).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $eleves = [];

    /** @var array<int, array<string, mixed>> familles[id] (voir EffectifsSeeder) */
    public array $familles = [];

    /** @var array<int, array<int, float>> moyennesAnnuelles[anneeId][eleveId] (calcul identique à BulletinService) */
    public array $moyennesAnnuelles = [];

    /** @var array<int, array<int, array<int, float>>> moyennesPeriodes[anneeId][eleveId][periodeId] */
    public array $moyennesPeriodes = [];

    /** @var array<string, bool> téléphones déjà attribués */
    private array $telephones = [];

    /** @var array<string, bool> emails déjà attribués */
    private array $emails = [];

    public function __construct()
    {
        $this->rng = new Rng(self::GRAINE);
        $this->hashMotDePasse = Hash::make(self::MOT_DE_PASSE);
    }

    public function anneeId(string $cle): int
    {
        return $this->annees[$cle]['id'];
    }

    // ─────────────────────────────────────────────────────────────────
    // INSERTION EN MASSE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Insère des lignes par lots, sans passer par Eloquent (aucun événement de modèle).
     */
    public function inserer(string $table, iterable $lignes, int $taille = self::TAILLE_LOT): int
    {
        $lot = [];
        $total = 0;

        foreach ($lignes as $ligne) {
            $lot[] = $ligne;
            if (count($lot) >= $taille) {
                DB::table($table)->insert($lot);
                $total += count($lot);
                $lot = [];
            }
        }

        if ($lot) {
            DB::table($table)->insert($lot);
            $total += count($lot);
        }

        return $total;
    }

    /**
     * Tampon d'insertion pour les très gros volumes (notes) : vide le lot dès qu'il est plein.
     */
    public function tampon(string $table, int $taille = self::TAILLE_LOT): InsertBuffer
    {
        return new InsertBuffer($table, $taille);
    }

    // ─────────────────────────────────────────────────────────────────
    // IDENTITÉS
    // ─────────────────────────────────────────────────────────────────

    /** Numéro mobile béninois unique au format enregistré par l'application : « 01XXXXXXXX ». */
    public function telephone(): string
    {
        do {
            $numero = '01'.$this->rng->pick(\Database\Seeders\Demo\Data\BeninNames::PREFIXES_MOBILES)
                .str_pad((string) $this->rng->int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (isset($this->telephones[$numero]));

        $this->telephones[$numero] = true;

        return $numero;
    }

    /** Réserve un numéro précis (comptes de démonstration). */
    public function reserverTelephone(string $numero): string
    {
        $this->telephones[$numero] = true;

        return $numero;
    }

    /** Email unique dérivé du prénom et du nom, sur un domaine réservé aux tests (.test). */
    public function email(string $prenom, string $nom, string $domaine = 'ccpa.test'): string
    {
        $prenom = explode(' ', trim($prenom))[0];
        $base = strtolower($this->ascii($prenom).'.'.$this->ascii($nom));
        $base = preg_replace('/[^a-z0-9.\-]/', '', $base);
        $email = $base.'@'.$domaine;
        $i = 2;
        while (isset($this->emails[$email])) {
            $email = $base.$i.'@'.$domaine;
            $i++;
        }
        $this->emails[$email] = true;

        return $email;
    }

    public function reserverEmail(string $email): string
    {
        $this->emails[$email] = true;

        return $email;
    }

    public function ascii(string $texte): string
    {
        $texte = strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'É' => 'E',
            'È' => 'E', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'À' => 'A', 'Ç' => 'C', 'Ï' => 'I', "'" => '', ' ' => '',
        ]);

        return $texte;
    }

    /** Ligne de compte utilisateur prête à insérer. */
    public function ligneUser(string $nom, string $email, string $role, string $creeLe, array $extra = []): array
    {
        return array_merge([
            'name' => $nom,
            'email' => $email,
            'email_verified_at' => $creeLe,
            'password' => $this->hashMotDePasse,
            'role' => $role,
            'actif' => true,
            'remember_token' => null,
            'created_at' => $creeLe,
            'updated_at' => $creeLe,
        ], $extra);
    }

    /** Arrondi d'une note au quart de point, bornée à [0, 20]. */
    public static function note(float $valeur, float $pas = 0.25): float
    {
        $valeur = max(0.0, min(20.0, $valeur));

        return round(round($valeur / $pas) * $pas, 2);
    }
}
