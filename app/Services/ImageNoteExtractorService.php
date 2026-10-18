<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * 🤖 SERVICE IA — Lecture des notes sur la photo d'une fiche
 * API Mistral, modèle « vision » (config services.mistral.vision_model)
 *
 * Deux façons de lire une photo :
 *  1. Bordereau de saisie des notes reconnu (DecoupeBordereauService) : la photo est découpée et
 *     l'IA ne reçoit, pour chaque élève, que son matricule imprimé et la case de la note à lire.
 *     C'est de loin la lecture la plus fiable : sur la fiche entière, le modèle confond les colonnes.
 *  2. Autre fiche, ou grille non reconnue : la photo entière est envoyée (en bandes horizontales
 *     quand elle est haute), avec la liste de la classe.
 *
 * FIXES :
 *  - ?string  → évite TypeError quand MISTRAL_API_KEY absent
 *  - Réduction GD avant envoi → évite timeout 30s
 *  - set_time_limit(120) → étend PHP pour requêtes longues
 *  - max_tokens 4000 + mode JSON → la réponse d'une classe entière n'est plus coupée
 *  - Nouvelles tentatives quand le service est saturé ou injoignable
 *  - Modèle de secours quand le modèle configuré est refusé par le service
 *
 * Les messages renvoyés dans « errors » sont affichés tels quels à l'utilisateur :
 * des phrases simples, jamais de détail technique (celui-ci va dans le journal).
 */
class ImageNoteExtractorService
{
    protected ?string $apiKey;                        // ← ?string OBLIGATOIRE (nullable)
    protected string  $baseUrl = 'https://api.mistral.ai/v1';
    protected string  $model;

    const MODELE_PAR_DEFAUT = 'ministral-14b-latest';
    const MODELE_DE_SECOURS = 'pixtral-12b-2409'; // ancien nom, encore accepté par le service

    const MAX_IMAGE_PX = 2000; // pixels max (côté le plus long) de la photo de travail
    const MAX_BANDE_PX = 1600; // largeur max d'une image envoyée à l'IA
    const JPEG_QUALITY = 85;   // qualité JPEG après redim

    const MAX_TOKENS       = 4000;         // longueur de réponse : une classe de 40 élèves et plus (1500 la coupait)
    const RETRY_DELAYS_MS  = [2000, 6000]; // attente avant chaque nouvelle tentative (le service sature par à-coups)
    const RETRY_MAX_WAIT_S = 20;           // connexion perdue après une attente plus longue : délai dépassé, pas de relance

    /** Intitulé de chacune des 12 cases de notes du bordereau, dans l'ordre (voir NotesPdfService::COLONNES_BORDEREAU). */
    const CASES_BORDEREAU = [
        'Int. 1', 'Int. 2', 'Int. 3', 'Int. 4', 'Int. 5', 'Moy. Int.',
        'Devoirs, première case (Devoir 1)', 'Devoirs, seconde case (Devoir 2)',
        'Moy. Dv.', 'Compo', 'Moy. Excel', 'Moy. Coef.',
    ];

    public function __construct(protected DecoupeBordereauService $decoupe)
    {
        $this->apiKey = config('services.mistral.api_key') ?: null;
        $this->model  = config('services.mistral.vision_model') ?: self::MODELE_PAR_DEFAUT;

        if (!$this->apiKey) {
            Log::error('🚨 MISTRAL_API_KEY manquante — vérifiez .env + php artisan config:clear');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // LECTURE D'UNE PHOTO
    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  array  $context  classe, matiere, periode, type_note (libellés) ; « case » : rang de la case
     *                          à lire sur le bordereau (0 à 11, absent si le bordereau n'en a pas) ;
     *                          « eleves » : liste de la classe dans l'ordre de la fiche (num, matricule, nom, prenom)
     */
    public function extractNotesFromImage(string $imagePath, array $context = []): array
    {
        @set_time_limit(120); // étendre PHP à 120s pour cette requête

        $temporaires = []; // images fabriquées pour cette lecture, supprimées à la fin

        try {
            if (!$this->apiKey) {
                Log::error('❌ ImageNoteExtractor: MISTRAL_API_KEY absente du .env (puis php artisan config:clear)');
                return $this->error("La lecture automatique des photos n'est pas disponible pour le moment. Saisissez les notes manuellement.");
            }

            if (!file_exists($imagePath)) {
                Log::error("❌ ImageNoteExtractor: image introuvable $imagePath");
                return $this->error("La photo n'a pas été reçue correctement. Veuillez l'envoyer à nouveau.");
            }

            // Photo remise à l'endroit et ramenée à une taille raisonnable
            $this->assurerMemoire();
            $photo = $this->compressImage($imagePath);
            if ($photo !== $imagePath) $temporaires[] = $photo;

            // 1. Bordereau reconnu : l'IA ne reçoit que « matricule | case à lire » de chaque élève
            $decoupe = isset($context['case'])
                ? $this->decoupe->decouper($photo, (int) $context['case'])
                : null;

            if ($decoupe) {
                $images      = $decoupe['images'];
                $temporaires = array_merge($temporaires, $images);
                $prompt      = $this->buildPairsPrompt($context, count($images));
            } else {
                // 2. Photo entière, en bandes si elle est haute
                $images      = $this->bandes($photo);
                $temporaires = array_merge($temporaires, array_diff($images, [$photo]));
                $prompt      = $this->buildPrompt($context, count($images));
            }

            $content = [];
            foreach ($images as $image) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . base64_encode(file_get_contents($image))]];
            }
            $content[] = ['type' => 'text', 'text' => $prompt];

            Log::info('📷 Lecture ' . ($decoupe ? 'du bordereau découpé (' . $decoupe['lignes'] . ' lignes)' : 'de la photo entière') . ' : ' . count($images) . ' image(s)');

            $response = $this->send([
                'model'           => $this->model,
                'max_tokens'      => self::MAX_TOKENS,
                'temperature'     => 0,
                'response_format' => ['type' => 'json_object'], // mode JSON : pas de texte autour de la réponse
                'messages'        => [
                    [
                        'role'    => 'system',
                        'content' => 'Tu es un assistant expert en lecture de documents scolaires manuscrits. Réponds UNIQUEMENT en JSON valide brut, sans markdown ni backticks.',
                    ],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('❌ Mistral HTTP ' . $response->status() . ': ' . $response->body());
                return $this->error("Le service de lecture automatique n'a pas répondu. Réessayez dans quelques instants.");
            }

            // Réponse coupée par la limite de longueur : le JSON est incomplet, inutile de chercher à le lire
            if ($response->json('choices.0.finish_reason') === 'length') {
                Log::warning('⚠️ Mistral: réponse coupée (finish_reason=length, max_tokens=' . self::MAX_TOKENS . ')');
                return $this->error('La photo contient trop de lignes pour être lue en une fois. Photographiez la fiche en deux parties.');
            }

            $answer = $response->json('choices.0.message.content');
            $answer = is_string($answer) ? $answer : '';
            Log::info('📝 Mistral réponse: ' . substr($answer, 0, 300));

            // « mode » : comment la photo a été lue (« decoupe » : bordereau reconnu ; « entiere » sinon)
            return $this->parseResponse($answer) + ['mode' => $decoupe ? 'decoupe' : 'entiere'];

        } catch (ConnectionException $e) {
            Log::error('❌ Mistral injoignable ou délai dépassé: ' . $e->getMessage());
            return $this->error("Le service de lecture automatique n'a pas répondu à temps. Réessayez dans quelques instants.");
        } catch (\Throwable $e) {
            Log::error('❌ ImageNoteExtractor: ' . $e->getMessage());
            return $this->error('Une erreur est survenue pendant la lecture. Réessayez.');
        } finally {
            foreach ($temporaires as $temporaire) {
                @unlink($temporaire);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // ENVOI À L'API (nouvelles tentatives si l'échec est passager, modèle de secours)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Envoie la demande. Deux sortes d'échec se rattrapent :
     *  - passager — service saturé (429), erreur du service (5xx), connexion impossible : nouvelle
     *    tentative après une attente, deux fois au plus ;
     *  - modèle refusé — nom inconnu du service, ou modèle absent de la formule du compte : la
     *    demande repart aussitôt avec le modèle suivant.
     * Une autre erreur 4xx (clé refusée, demande invalide) n'est jamais relancée : elle échouerait à l'identique.
     */
    protected function send(array $payload): Response
    {
        $modeles = array_values(array_unique([$payload['model'], self::MODELE_PAR_DEFAUT, self::MODELE_DE_SECOURS]));
        $attentes = self::RETRY_DELAYS_MS;

        while (true) {
            $payload['model'] = array_shift($modeles);
            $startedAt = now();

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . trim($this->apiKey),
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(90)
                ->post("{$this->baseUrl}/chat/completions", $payload);

                if ($this->modeleRefuse($response) && $modeles) {
                    Log::warning('⚠️ Mistral: modèle « ' . $payload['model'] . ' » refusé (HTTP ' . $response->status() . ') — essai avec « ' . $modeles[0] . ' »');
                    continue;
                }

                if (($response->status() !== 429 && !$response->serverError()) || !$attentes) {
                    return $response;
                }

                Log::warning('⚠️ Mistral HTTP ' . $response->status() . ' — nouvelle tentative');
            } catch (ConnectionException $e) {
                // Après une longue attente, c'est un délai dépassé : relancer ferait patienter deux fois plus
                if (!$attentes || $startedAt->diffInSeconds(now()) >= self::RETRY_MAX_WAIT_S) {
                    throw $e;
                }

                Log::warning('⚠️ Mistral injoignable — nouvelle tentative : ' . $e->getMessage());
            }

            // Même modèle, après une attente
            array_unshift($modeles, $payload['model']);
            Sleep::for(array_shift($attentes))->milliseconds();
            @set_time_limit(120); // chaque tentative dispose elle aussi de son délai
        }
    }

    /**
     * Le service refuse-t-il ce modèle ? Nom qu'il ne connaît pas (ou plus), ou modèle que la
     * formule du compte n'ouvre pas (429 avec une limite de zéro demande par minute).
     */
    protected function modeleRefuse(Response $response): bool
    {
        if (in_array($response->status(), [400, 404], true)) {
            return str_contains(Str::lower((string) $response->json('message')), 'model');
        }

        return $response->status() === 429 && $response->header('x-ratelimit-limit-req-minute') === '0';
    }

    // ─────────────────────────────────────────────────────────────────
    // COMPRESSION IMAGE via GD
    // ─────────────────────────────────────────────────────────────────

    /**
     * Une photo de téléphone arrivée sans avoir été réduite par le navigateur (12 millions de points)
     * occupe près de 100 Mo le temps d'être redressée : avec les 128 Mo habituels, la lecture
     * s'arrêterait sur une page d'erreur. La limite est relevée pour cette seule demande.
     */
    protected function assurerMemoire(): void
    {
        $limite = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($limite > 0 && $limite < 384 * 1024 * 1024) {
            @ini_set('memory_limit', '384M');
        }
    }

    protected function compressImage(string $src): string
    {
        if (!extension_loaded('gd')) {
            Log::warning('⚠️ GD absent — image envoyée sans compression');
            return $src;
        }

        try {
            $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION));
            $img  = match($ext) {
                'png'  => @imagecreatefrompng($src),
                'gif'  => @imagecreatefromgif($src),
                'webp' => @imagecreatefromwebp($src),
                default => @imagecreatefromjpeg($src),
            };

            if (!$img) return $src;

            // Photo de téléphone envoyée telle quelle : la remettre à l'endroit avant de la réduire
            $img = $this->applyExifOrientation($img, $src);

            $w = imagesx($img);
            $h = imagesy($img);

            if ($w > self::MAX_IMAGE_PX || $h > self::MAX_IMAGE_PX) {
                $ratio = min(self::MAX_IMAGE_PX / $w, self::MAX_IMAGE_PX / $h);
                $nw    = (int)($w * $ratio);
                $nh    = (int)($h * $ratio);
                $dst   = imagecreatetruecolor($nw, $nh);
                // fond blanc pour PNG transparents
                imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocate($dst, 255, 255, 255));
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $dst;
            }

            // tempnam() crée un fichier vide : il sert seulement à réserver un nom, on ne le laisse pas derrière soi
            $reserved = tempnam(sys_get_temp_dir(), 'ccpa_');
            $tmp      = $reserved . '.jpg';
            imagejpeg($img, $tmp, self::JPEG_QUALITY);
            imagedestroy($img);
            @unlink($reserved);

            Log::info("✅ Image compressée → " . round(filesize($tmp) / 1024) . ' KB');
            return $tmp;

        } catch (\Throwable $e) {
            Log::warning('⚠️ Compression échouée: ' . $e->getMessage());
            return $src;
        }
    }

    /**
     * Remet à l'endroit une photo JPEG prise avec un téléphone tenu de côté ou à l'envers
     * (balise EXIF « Orientation » 3, 6 ou 8). Sans l'extension exif, sans balise ou en cas
     * d'échec, l'image est rendue telle quelle.
     *
     * @param  \GdImage  $img
     * @return \GdImage
     */
    protected function applyExifOrientation($img, string $src)
    {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
            return $img;
        }

        $exif        = @exif_read_data($src);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // imagerotate() tourne dans le sens inverse des aiguilles d'une montre
        $angle = match ($orientation) {
            3       => 180,
            6       => -90, // photo à tourner d'un quart de tour vers la droite
            8       => 90,  // photo à tourner d'un quart de tour vers la gauche
            default => 0,
        };

        if ($angle === 0) return $img;

        $rotated = @imagerotate($img, $angle, 0);
        if (!$rotated) return $img;

        imagedestroy($img);

        return $rotated;
    }

    // ─────────────────────────────────────────────────────────────────
    // PHOTO ENTIÈRE EN BANDES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Images à envoyer quand la photo n'est pas découpée case par case : la photo telle quelle, ou
     * en bandes horizontales qui se recouvrent un peu quand elle est haute (fiche photographiée en
     * entier). Chaque bande garde assez de détail pour les chiffres manuscrits.
     *
     * @return list<string>  chemins des images, du haut vers le bas ; la photo elle-même s'il n'y a pas de bande
     */
    protected function bandes(string $photo): array
    {
        $taille = extension_loaded('gd') ? @getimagesize($photo) : false;
        if (!$taille) return [$photo];

        [$w, $h] = $taille;
        $nombre  = match (true) {
            $h < 1100       => 1, // petite photo : rien à gagner
            $h > 1.15 * $w  => 3,
            $h > 0.75 * $w  => 2,
            default         => 1,
        };

        $img = $nombre > 1 ? @imagecreatefromjpeg($photo) : false;
        if (!$img) return [$photo];

        $ratio  = min(1, self::MAX_BANDE_PX / $w);
        $bandes = [];
        for ($i = 0; $i < $nombre; $i++) {
            // 6 % de recouvrement : une ligne coupée par le bord d'une bande est entière dans la suivante
            $haut = (int) max(0, ($i / $nombre - 0.06) * $h);
            $bas  = (int) min($h, (($i + 1) / $nombre + 0.06) * $h);

            $bande = imagecreatetruecolor((int) round($w * $ratio), (int) round(($bas - $haut) * $ratio));
            imagecopyresampled($bande, $img, 0, 0, 0, $haut, imagesx($bande), imagesy($bande), $w, $bas - $haut);

            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('ccpa_bande_', true) . '.jpg';
            imagejpeg($bande, $tmp, self::JPEG_QUALITY);
            imagedestroy($bande);
            $bandes[] = $tmp;
        }
        imagedestroy($img);

        return $bandes;
    }

    // ─────────────────────────────────────────────────────────────────
    // CONSIGNES DONNÉES À L'IA
    // ─────────────────────────────────────────────────────────────────

    /**
     * Consigne pour le bordereau découpé : chaque image montre, ligne par ligne, le numéro et le
     * matricule imprimés d'un élève et la case de sa note. Seuls les numéros et les matricules de
     * la classe sont transmis : les noms des élèves ne quittent pas l'application.
     */
    protected function buildPairsPrompt(array $context, int $images = 1): string
    {
        $plusieurs = $images > 1
            ? "Les {$images} images se suivent du haut vers le bas de la même fiche.\n"
            : '';
        $liste = $this->listeDeLaClasse($context, false);

        return <<<TXT
Chaque image est un extrait d'une fiche de notes. Sur chaque ligne : à gauche le numéro d'ordre et le matricule imprimés d'un élève, à droite la case où le professeur a écrit sa note à la main. Un trait horizontal sépare deux élèves.
{$plusieurs}
{$liste}
Pour chaque ligne, lis le matricule imprimé, puis la note manuscrite écrite sur la MÊME ligne.

Règles :
- une note est un nombre entre 0 et 20 : « 12,5 » ou « 12.5 » se lit 12.5 ; « 08 » se lit 8 ; « 03 » se lit 3
- case vide : note = null. Écriture illisible : note = null. N'invente jamais une note.
- un trait au bord de la case appartient à la case voisine : ne lis que ce qui est écrit dans la case
- recopie le matricule tel qu'imprimé
- ne donne aucune ligne pour l'en-tête du tableau

Réponds uniquement par ce JSON, une entrée par ligne d'élève, dans l'ordre :
{"notes":[{"matricule":"25-0107","note":12.5},{"matricule":"20-0482","note":null}]}
TXT;
    }

    /**
     * Consigne pour une photo entière (bordereau non reconnu, ou autre fiche).
     */
    protected function buildPrompt(array $context, int $images = 1): string
    {
        $ctx = '';
        if (!empty($context['classe']))    $ctx .= "- Classe : {$context['classe']}\n";
        if (!empty($context['matiere']))   $ctx .= "- Matière : {$context['matiere']}\n";
        if (!empty($context['periode']))   $ctx .= "- Période : {$context['periode']}\n";
        if (!empty($context['type_note'])) $ctx .= "- Type : {$context['type_note']}\n";
        $ctxBlock = $ctx ? "Contexte connu :\n$ctx\n" : '';

        $plusieurs = $images > 1
            ? "Les {$images} images sont des morceaux de la MÊME fiche, du haut vers le bas ; ils se recouvrent un peu : un élève visible sur deux morceaux ne doit apparaître qu'une fois.\n\n"
            : '';

        // Sur le bordereau, la colonne à lire est connue : on la désigne par son intitulé et par son rang
        $case = $context['case'] ?? null;
        $colonne = is_int($case) && isset(self::CASES_BORDEREAU[$case])
            ? 'ne lis QUE la colonne « ' . self::CASES_BORDEREAU[$case] . ' », c\'est-à-dire la ' . ($case + 1) . 'e case de notes après le nom, et ignore toutes les autres, même remplies.'
            : "ne lis QUE la colonne qui correspond au type indiqué dans le contexte et ignore toutes les autres :\n"
                . "- Interrogation 1 à 5 → colonne « Int. 1 » à « Int. 5 »\n"
                . "- Devoir 1 → première case de « Devoirs » ; Devoir 2 → seconde case\n"
                . '- Composition → colonne « Compo »';

        $liste = $this->listeDeLaClasse($context, true);

        return <<<TXT
{$ctxBlock}{$plusieurs}Tu lis la photo d'une fiche de notes scolaires remplie à la main par le professeur : une ligne par élève.

Si la fiche est un « BORDEREAU DE SAISIE DES NOTES » à plusieurs colonnes de notes
(Int. 1 à Int. 5, Moy. Int., Devoirs, Moy. Dv., Compo, Moy. Excel, Moy. Coef.), {$colonne}
Sur ce bordereau, le nom (en MAJUSCULES) et le(s) prénom(s) sont dans la même colonne « Nom & Prénom(s) ».
Sur une autre fiche, lis sa colonne de notes.

{$liste}
Pour chaque ligne d'élève visible, donne son matricule (et son nom si la fiche n'a pas de matricule), puis la note manuscrite écrite sur CETTE ligne, sans glisser sur la ligne voisine.

Réponds UNIQUEMENT avec ce JSON brut (sans backticks, sans explication) :
{"notes":[{"matricule":"25-0107","nom":"ABDELHAK","prenom":"Noura","note":12.5}]}

RÈGLES :
- une note est un nombre entre 0 et 20 : « 12,5 » ou « 12.5 » se lit 12.5 ; « 08 » se lit 8
- note = null si la case est vide ou l'écriture illisible (jamais inventé)
- recopie le matricule tel qu'imprimé
- JSON BRUT UNIQUEMENT
TXT;
    }

    /**
     * Liste de la classe jointe à la consigne, dans l'ordre de la fiche : avec elle, l'IA reconnaît
     * les matricules au lieu de les deviner. Chaîne vide si la liste n'est pas fournie.
     */
    protected function listeDeLaClasse(array $context, bool $avecNoms): string
    {
        $eleves = is_array($context['eleves'] ?? null) ? $context['eleves'] : [];
        if (!$eleves) return '';

        $lignes = [];
        foreach (array_values($eleves) as $i => $eleve) {
            $ligne = ($eleve['num'] ?? $i + 1) . ' | ' . ($eleve['matricule'] ?? '');
            if ($avecNoms) {
                $ligne .= ' | ' . trim(mb_strtoupper((string) ($eleve['nom'] ?? '')) . ' ' . ($eleve['prenom'] ?? ''));
            }
            $lignes[] = $ligne;
        }

        return "Élèves de la classe, dans l'ordre de la fiche (N° | matricule" . ($avecNoms ? ' | nom et prénoms' : '') . ") :\n"
            . implode("\n", $lignes) . "\n";
    }

    // ─────────────────────────────────────────────────────────────────
    // PARSE + NORMALIZE
    // ─────────────────────────────────────────────────────────────────

    protected function parseResponse(string $content): array
    {
        $text = trim($content);

        foreach ([
            fn($t) => json_decode($t, true),
            fn($t) => preg_match('/```(?:json)?\s*(.*?)\s*```/s', $t, $m) ? json_decode($m[1], true) : null,
            fn($t) => preg_match('/\{.*\}/s', $t, $m) ? json_decode($m[0], true) : null,
        ] as $try) {
            $json = $try($text);
            if (is_array($json) && !empty($json)) {
                return $this->normalize($this->reunir($json));
            }
        }

        return $this->error("Les notes n'ont pas pu être reconnues sur cette photo. Réessayez avec une photo plus nette.");
    }

    /**
     * Note lue → note retenue, ou null (case à saisir à la main) quand la valeur ne peut pas être une
     * note : hors de 0 à 20, ou qui ne tombe pas sur un quart de point. « 10,06 » ou « 13,1 », c'est
     * la note de la case voisine lue avec celle-ci, ou une note coupée : mieux vaut une case vide
     * qu'une note fausse.
     */
    protected function noteRetenue($note): ?float
    {
        if (!is_numeric($note) || $note < 0 || $note > 20) return null;

        $quarts = (float) $note * 4;

        return abs($quarts - round($quarts)) < 0.001 ? round((float) $note, 2) : null;
    }

    /**
     * Quand plusieurs images sont envoyées, le modèle répond souvent par une liste — un objet
     * « notes » par image, ou les lignes d'élèves à la suite : le tout est réuni en un seul objet.
     */
    protected function reunir(array $json): array
    {
        if (!array_is_list($json)) return $json;

        $notes = [];
        foreach ($json as $element) {
            if (!is_array($element)) continue;

            if (is_array($element['notes'] ?? null)) {
                $notes = array_merge($notes, $element['notes']);
            } else {
                $notes[] = $element;
            }
        }

        return ['notes' => $notes];
    }

    /**
     * Met la réponse au format attendu par l'application, quoi que l'IA ait renvoyé :
     * une ligne peut n'avoir ni nom, ni prénom, ni commentaire (le matricule suffit à retrouver l'élève).
     */
    protected function normalize(array $data): array
    {
        // Texte d'une cellule : absente, vide ou d'un type inattendu → chaîne vide
        $text = fn ($value): string => is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';

        $notes = [];
        foreach ((array) ($data['notes'] ?? []) as $n) {
            if (!is_array($n)) continue;

            $note = $n['note'] ?? null;
            if (is_string($note)) {
                $note = str_replace(',', '.', trim($note)); // « 12,5 » écrit à la française
            }

            $line = [
                'matricule'   => mb_strtoupper($text($n['matricule'] ?? '')),
                'nom'         => mb_strtoupper($text($n['nom'] ?? '')),
                'prenom'      => mb_convert_case($text($n['prenom'] ?? ''), MB_CASE_TITLE, 'UTF-8'),
                'note'        => $this->noteRetenue($note),
                'commentaire' => $text($n['commentaire'] ?? ''),
            ];

            // Une ligne sans matricule ni nom ne désigne personne
            if ($line['matricule'] === '' && $line['nom'] === '' && $line['prenom'] === '') continue;

            $notes[] = $line;
        }

        // Les remarques et le degré de confiance que le modèle ajoute de lui-même ne sont pas repris :
        // il les écrit souvent en anglais, et se dit « moyennement sûr » quoi qu'il ait lu.
        return [
            'success'    => $data['success'] ?? true,
            'metadata'   => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            'notes'      => $notes,
            'warnings'   => [],
            'errors'     => [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // MATCH AVEC LA BDD
    // ─────────────────────────────────────────────────────────────────

    /**
     * Rapproche chaque ligne lue d'un élève de la classe. Règle unique :
     *  1. le matricule (espaces autour et majuscules/minuscules ignorés) ;
     *  2. sinon le nom complet « NOM PRENOM » exact (majuscules, sans accents, espaces simples),
     *     à condition qu'un seul élève de la classe le porte.
     *
     * Retourne :
     *  - rows      : les lignes lues, dans l'ordre, chacune avec « eleve » (l'élève de la base, ou null)
     *                et « key » (identité de la ligne : la même pour une ligne lue deux fois) ;
     *  - matched   : [id de l'élève => valeur + commentaire] (première lecture de chaque élève) ;
     *  - unmatched : les lignes sans élève — toujours les lignes de « rows » dont « eleve » est null.
     */
    public function matchWithDatabase(array $extractedNotes, array $dbEleves): array
    {
        $byMat  = [];
        $byName = [];
        foreach ($dbEleves as $e) {
            $mat = $this->normalizeMatricule($e['matricule'] ?? '');
            if ($mat !== '') $byMat[$mat] ??= $e;

            $byName[$this->normalizeName($e['nom'] ?? '', $e['prenom'] ?? '')][] = $e;
        }

        $rows      = [];
        $matched   = [];
        $unmatched = [];

        foreach ($extractedNotes as $en) {
            $mat      = $this->normalizeMatricule($en['matricule'] ?? '');
            $name     = $this->normalizeName($en['nom'] ?? '', $en['prenom'] ?? '');
            $sameName = $name !== '' ? ($byName[$name] ?? []) : [];
            $found    = $byMat[$mat] ?? (count($sameName) === 1 ? $sameName[0] : null);

            $rows[] = array_merge($en, [
                'eleve' => $found,
                'key'   => $found ? 'eleve:' . $found['id'] : "lu:{$mat}|{$name}",
            ]);

            if ($found) {
                $matched[$found['id']] ??= [
                    'valeur'      => $en['note'] ?? null,
                    'commentaire' => $en['commentaire'] ?? '',
                ];
            } else {
                $unmatched[] = $en;
            }
        }

        return compact('matched', 'unmatched', 'rows');
    }

    /** Matricule comparable : sans espaces autour, en majuscules. */
    protected function normalizeMatricule($matricule): string
    {
        return mb_strtoupper(trim((string) $matricule));
    }

    /** Nom complet comparable : « NOM PRENOM » en majuscules, sans accents, espaces simples. */
    protected function normalizeName($nom, $prenom): string
    {
        $name = strtoupper(Str::ascii($nom . ' ' . $prenom));

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    protected function error(string $msg): array
    {
        return [
            'success'    => false,
            'metadata'   => [],
            'notes'      => [],
            'warnings'   => [],
            'errors'     => [$msg],
        ];
    }
}