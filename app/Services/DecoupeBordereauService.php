<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Découpe d'une photo du bordereau de saisie des notes, avant sa lecture par l'IA.
 *
 * Sur la photo d'une fiche entière, une case fait quelques millimètres : l'IA confond les colonnes
 * ou glisse d'une ligne à l'autre. Ce service retrouve la grille du bordereau sur la photo (traits
 * verticaux, puis traits horizontaux) et ne garde, pour chaque élève, que son numéro et son
 * matricule imprimés et la case de la note à lire. L'IA n'a plus de colonne à choisir : elle lit
 * des paires « matricule | note ».
 *
 * La photo peut être un peu penchée ou prise de biais : chaque ligne d'élève est découpée à
 * l'endroit où elle se trouve vraiment. Seule l'extension GD est utilisée. Si la grille n'est pas
 * reconnue (autre modèle de fiche, photo trop penchée, floue ou coupée), decouper() renvoie null
 * et l'appelant lit la photo entière.
 */
class DecoupeBordereauService
{
    /** Nombre de cases de notes par élève sur le bordereau (Int. 1 à Moy. Coef.). */
    public const NB_CASES = 12;

    /** Traits verticaux de la grille : bord gauche, N°, matricule, nom, puis un trait après chaque case. */
    protected const NB_TRAITS = 16;

    /** La grille est cherchée sur une copie de la photo ramenée à cette largeur, en pixels. */
    protected const LARGEUR_TRAVAIL = 1100;

    /** Un trait est un pixel plus sombre que ses voisins situés à cette distance, de cet écart au moins. */
    protected const ECART = 3;

    protected const CONTRASTE = 12;

    /** Les traits verticaux sont cherchés par bandes horizontales de cette hauteur, en pixels. */
    protected const HAUTEUR_BANDE = 24;

    /** Image produite : hauteur d'une ligne d'élève, largeur du bloc « N° + matricule » et de la case. */
    protected const HAUTEUR_LIGNE = 46;

    protected const LARGEUR_MATRICULE = 230;

    protected const LARGEUR_CASE = 120;

    protected const LIGNES_PAR_IMAGE = 14;

    /** Marge gardée de chaque côté de la case à lire, en part de sa largeur. */
    protected const MARGE_GAUCHE = 0.03;

    protected const MARGE_DROITE = 0.18;

    /**
     * Découpe la photo en images « N° + matricule | case de la note », du haut vers le bas de la fiche.
     *
     * @param  int  $case  rang de la case à lire parmi les 12 cases de notes, à partir de 0
     * @return array{images: list<string>, lignes: int}|null  fichiers JPEG temporaires (à supprimer par
     *                                                         l'appelant) et nombre de lignes découpées, en-tête compris
     */
    public function decouper(string $cheminPhoto, int $case): ?array
    {
        if (! extension_loaded('gd') || $case < 0 || $case >= self::NB_CASES) {
            return null;
        }

        try {
            $source = @imagecreatefromstring((string) @file_get_contents($cheminPhoto));

            return $source ? $this->decouperImage($source, $case) : null;
        } catch (\Throwable $e) {
            Log::warning('Découpe du bordereau impossible : '.$e->getMessage());

            return null;
        }
    }

    protected function decouperImage(\GdImage $source, int $case): ?array
    {
        $echelle = min(1, self::LARGEUR_TRAVAIL / imagesx($source));
        $largeur = (int) round(imagesx($source) * $echelle);
        $hauteur = (int) round(imagesy($source) * $echelle);
        if ($largeur < 500 || $hauteur < 200) {
            return null;
        }

        $gris = $this->niveauxDeGris($source, $largeur, $hauteur);

        // 1. Traits verticaux : chaque trait de la grille est une droite x = a + b·y
        $droites = $this->traitsVerticaux($gris, $largeur, $hauteur);
        if ($droites === null) {
            return null;
        }
        ['droites' => $droites, 'haut' => $haut, 'bas' => $bas] = $droites;
        $x = fn (int $trait, float $y): float => $droites[$trait][0] + $droites[$trait][1] * $y;

        // 2. Traits horizontaux, cherchés à deux endroits : sous les matricules et dans la case à lire.
        //    Sur une photo penchée, une même ligne d'élève n'y est pas à la même hauteur.
        $pente = -array_sum(array_column($droites, 1)) / count($droites);
        $marge = 0.08 * $hauteur;
        $gauche = $this->traitsHorizontaux($gris, $hauteur, $x, 0, 2, $haut - $marge, $bas + $marge, $pente);
        $cellule = $this->traitsHorizontaux($gris, $hauteur, $x, 3 + $case, 4 + $case, $haut - 2 * $marge, $bas + 2 * $marge, $pente);
        $gauche = $this->garderSuiteReguliere($gauche);
        if (count($gauche) < 4 || count($cellule) < 4) {
            return null;
        }
        $pas = $this->mediane($this->ecarts($gauche));
        if ($pas < 10) {
            return null;
        }

        // 3. À chaque trait de gauche son trait dans la case : le décalage vertical entre les deux
        //    varie régulièrement du haut vers le bas de la fiche (décalage = alpha + beta·y)
        $ecartHorizontal = fn (float $y): float => ($x(3 + $case, $y) + $x(4 + $case, $y)) / 2 - ($x(0, $y) + $x(2, $y)) / 2;
        $paires = [];
        $loi = null;
        for ($tour = 0; $tour < 3; $tour++) {
            $paires = [];
            foreach ($gauche as $yGauche) {
                $prevu = $yGauche + ($loi === null ? $pente * $ecartHorizontal($yGauche) : $loi[0] + $loi[1] * $yGauche);
                $proche = null;
                foreach ($cellule as $yCase) {
                    if ($proche === null || abs($yCase - $prevu) < abs($proche - $prevu)) {
                        $proche = $yCase;
                    }
                }
                if (abs($proche - $prevu) <= ($loi === null ? 0.45 : 0.3) * $pas) {
                    $paires[] = [$yGauche, $proche];
                }
            }
            if (count($paires) < 4) {
                return null;
            }
            $loi = $this->droite(array_column($paires, 0), array_map(fn (array $p) => $p[1] - $p[0], $paires));
        }

        // 4. Lignes d'élèves : l'espace entre deux traits consécutifs. Un trait non vu laisse un
        //    espace double, partagé en parts égales.
        $rangs = [];
        for ($i = 0; $i + 1 < count($paires); $i++) {
            [$g0, $c0] = $paires[$i];
            [$g1, $c1] = $paires[$i + 1];
            $parts = $g1 - $g0 > 1.6 * $pas ? max(1, (int) round(($g1 - $g0) / $pas)) : 1;
            // La ligne d'en-tête du tableau, plus haute que les autres, reste d'un seul tenant
            if ($i === 0 && $parts === 2) {
                $parts = 1;
            }
            for ($p = 0; $p < $parts; $p++) {
                $rangs[] = [
                    $g0 + ($g1 - $g0) * $p / $parts,
                    $g0 + ($g1 - $g0) * ($p + 1) / $parts,
                    $c0 + ($c1 - $c0) * $p / $parts,
                    $c0 + ($c1 - $c0) * ($p + 1) / $parts,
                ];
            }
        }
        if (count($rangs) < 3) {
            return null;
        }

        // 5. Images « N° + matricule | case », découpées dans la photo d'origine
        return [
            'images' => $this->composer($source, $rangs, $x, $case, $echelle),
            'lignes' => count($rangs),
        ];
    }

    /**
     * Copie de la photo en niveaux de gris, à la largeur de travail : une chaîne d'octets par ligne
     * (un tableau PHP d'entiers prendrait seize fois plus de mémoire).
     *
     * @return list<string>
     */
    protected function niveauxDeGris(\GdImage $source, int $largeur, int $hauteur): array
    {
        $travail = imagecreatetruecolor($largeur, $hauteur);
        imagecopyresampled($travail, $source, 0, 0, 0, 0, $largeur, $hauteur, imagesx($source), imagesy($source));
        imagefilter($travail, IMG_FILTER_GRAYSCALE);

        $gris = [];
        for ($y = 0; $y < $hauteur; $y++) {
            $ligne = '';
            for ($px = 0; $px < $largeur; $px++) {
                $ligne .= chr(imagecolorat($travail, $px, $y) & 0xFF);
            }
            $gris[] = $ligne;
        }
        imagedestroy($travail);

        return $gris;
    }

    /**
     * Retrouve les 16 traits verticaux du bordereau.
     *
     * Dans chaque bande horizontale, un trait de la grille traverse la bande de haut en bas alors
     * qu'un caractère imprimé ou un chiffre manuscrit n'en couvre qu'une partie. Une bande est
     * retenue si elle montre exactement la structure du bordereau : 16 traits, la colonne du nom
     * bien plus large que les 12 cases de notes, elles-mêmes de largeurs voisines.
     *
     * @param  list<string>  $gris
     * @return array{droites: list<array{0: float, 1: float}>, haut: float, bas: float}|null
     */
    protected function traitsVerticaux(array $gris, int $largeur, int $hauteur): ?array
    {
        $e = self::ECART;
        $bandes = [];

        for ($haut = 0; $haut + self::HAUTEUR_BANDE <= $hauteur; $haut += self::HAUTEUR_BANDE) {
            $compte = array_fill(0, $largeur, 0);
            for ($y = $haut; $y < $haut + self::HAUTEUR_BANDE; $y++) {
                $ligne = $gris[$y];
                $vus = [];
                for ($px = $e; $px < $largeur - $e; $px++) {
                    $v = ord($ligne[$px]) + self::CONTRASTE;
                    if ($v < ord($ligne[$px - $e]) && $v < ord($ligne[$px + $e])) {
                        // Tolérance d'un pixel de chaque côté : sur une photo penchée, le trait se décale dans la bande
                        $vus[$px - 1] = $vus[$px] = $vus[$px + 1] = true;
                    }
                }
                foreach ($vus as $px => $_) {
                    $compte[$px]++;
                }
            }

            $pics = $this->pics($compte, 0.75 * self::HAUTEUR_BANDE);
            if ($this->estStructureDuBordereau($pics)) {
                $bandes[] = [$haut + self::HAUTEUR_BANDE / 2, $pics];
            }
        }

        if (count($bandes) < 3) {
            return null;
        }

        // Une droite par trait ; une bande dont un trait s'écarte de sa droite est écartée, puis on recommence
        for ($tour = 0; $tour < 2; $tour++) {
            $ys = array_column($bandes, 0);
            $droites = [];
            for ($t = 0; $t < self::NB_TRAITS; $t++) {
                $droites[] = $this->droite($ys, array_map(fn (array $b) => $b[1][$t], $bandes));
            }
            if ($tour === 1) {
                break;
            }
            $bandes = array_values(array_filter($bandes, function (array $bande) use ($droites) {
                foreach ($bande[1] as $t => $position) {
                    if (abs($position - ($droites[$t][0] + $droites[$t][1] * $bande[0])) > 4) {
                        return false;
                    }
                }

                return true;
            }));
            if (count($bandes) < 3) {
                return null;
            }
        }

        $ys = array_column($bandes, 0);

        return ['droites' => $droites, 'haut' => min($ys), 'bas' => max($ys)];
    }

    /**
     * Positions où le compte atteint le seuil (milieu de chaque suite de positions voisines).
     *
     * @param  list<int>  $compte
     * @return list<float>
     */
    protected function pics(array $compte, float $seuil): array
    {
        $pics = [];
        $debut = null;
        $n = count($compte);
        for ($i = 0; $i <= $n; $i++) {
            if ($i < $n && $compte[$i] >= $seuil) {
                $debut ??= $i;
            } elseif ($debut !== null) {
                $pics[] = ($debut + $i - 1) / 2;
                $debut = null;
            }
        }

        return $pics;
    }

    /**
     * @param  list<float>  $traits
     */
    protected function estStructureDuBordereau(array $traits): bool
    {
        if (count($traits) !== self::NB_TRAITS) {
            return false;
        }

        $ecarts = $this->ecarts($traits);
        $cases = array_slice($ecarts, 3);
        $mediane = $this->mediane($cases);

        return $mediane >= 12
            && $ecarts[2] >= 3 * $mediane
            && min($cases) >= 0.6 * $mediane
            && max($cases) <= 1.6 * $mediane;
    }

    /**
     * Traits horizontaux qui traversent la colonne comprise entre deux traits verticaux.
     *
     * @param  list<string>  $gris
     * @param  \Closure(int, float): float  $x  position d'un trait vertical à une hauteur donnée
     * @param  float  $pente  inclinaison attendue des traits horizontaux (photo penchée)
     * @return list<float>  hauteur de chaque trait, au milieu de la colonne
     */
    protected function traitsHorizontaux(array $gris, int $hauteur, \Closure $x, int $trait0, int $trait1, float $yMin, float $yMax, float $pente): array
    {
        $e = self::ECART;
        $largeur = strlen($gris[0]);
        $sombre = function (int $px, int $y) use ($gris, $hauteur, $e): bool {
            if ($y < $e || $y >= $hauteur - $e) {
                return false;
            }
            $v = ord($gris[$y][$px]) + self::CONTRASTE;

            return $v < ord($gris[$y - $e][$px]) && $v < ord($gris[$y + $e][$px]);
        };

        $traits = [];
        $debut = null;
        $dernier = null;
        $fin = min($hauteur - $e - 1, (int) $yMax);
        for ($y = max($e + 1, (int) $yMin); $y <= $fin + 1; $y++) {
            $couvert = false;
            if ($y <= $fin) {
                $a = max(0, (int) ceil($x($trait0, $y)) + 3);
                $b = min($largeur - 1, (int) floor($x($trait1, $y)) - 3);
                if ($b - $a >= 6) {
                    $milieu = ($a + $b) / 2;
                    $n = 0;
                    for ($px = $a; $px < $b; $px++) {
                        $yy = $y + (int) round($pente * ($px - $milieu));
                        if ($sombre($px, $yy) || $sombre($px, $yy - 1) || $sombre($px, $yy + 1)) {
                            $n++;
                        }
                    }
                    // Un trait du tableau traverse toute la colonne ; le bas d'une rangée de chiffres
                    // imprimés ou la barre d'un 7 manuscrit n'en couvrent qu'une partie
                    $couvert = $n >= 0.8 * ($b - $a);
                }
            }

            if ($couvert) {
                $debut ??= $y;
                $dernier = $y;
            } elseif ($debut !== null) {
                $traits[] = ($debut + $dernier) / 2;
                $debut = null;
            }
        }

        return $traits;
    }

    /**
     * Ne garde que les traits qui se suivent à intervalles réguliers : ceux du tableau. Un trait isolé
     * au-dessus ou au-dessous (soulignement d'un titre, bord de la feuille) est écarté.
     *
     * @param  list<float>  $traits
     * @return list<float>
     */
    protected function garderSuiteReguliere(array $traits): array
    {
        if (count($traits) < 4) {
            return $traits;
        }

        $pas = $this->mediane($this->ecarts($traits));

        // Un faux trait au milieu d'une ligne d'élève la couperait en deux : il est retiré quand ses
        // deux voisins sont, eux, à un pas l'un de l'autre
        for ($i = 1; $i + 1 < count($traits); $i++) {
            if ($traits[$i] - $traits[$i - 1] < 0.6 * $pas && $traits[$i + 1] - $traits[$i - 1] < 1.4 * $pas) {
                array_splice($traits, $i, 1);
                $i--;
            }
        }

        // La plus longue suite de traits dont aucun intervalle ne dépasse 2,6 fois le pas (l'en-tête du
        // tableau fait à peu près deux lignes de haut)
        $meilleure = [];
        $suite = [$traits[0]];
        for ($i = 1; $i < count($traits); $i++) {
            if ($traits[$i] - $traits[$i - 1] <= 2.6 * $pas) {
                $suite[] = $traits[$i];
            } else {
                $meilleure = count($suite) > count($meilleure) ? $suite : $meilleure;
                $suite = [$traits[$i]];
            }
        }

        return count($suite) > count($meilleure) ? $suite : $meilleure;
    }

    /**
     * @param  list<array{0: float, 1: float, 2: float, 3: float}>  $rangs  [haut, bas] sous les matricules puis dans la case
     * @param  \Closure(int, float): float  $x
     * @return list<string>
     */
    protected function composer(\GdImage $source, array $rangs, \Closure $x, int $case, float $echelle): array
    {
        $dossier = storage_path('app'.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR.'notes-images');
        if (! is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        $h = self::HAUTEUR_LIGNE;
        $debutCase = self::LARGEUR_MATRICULE + 8;
        $largeurImage = $debutCase + self::LARGEUR_CASE;
        $images = [];

        foreach (array_chunk($rangs, self::LIGNES_PAR_IMAGE) as $lot) {
            $feuille = imagecreatetruecolor($largeurImage, $h * count($lot));
            imagefill($feuille, 0, 0, imagecolorallocate($feuille, 255, 255, 255));

            foreach ($lot as $i => [$g0, $g1, $c0, $c1]) {
                // Bloc « N° + matricule »
                $milieu = ($g0 + $g1) / 2;
                $this->copier($feuille, $source, 0, $i * $h, self::LARGEUR_MATRICULE, $h,
                    ($x(0, $milieu) - 2) / $echelle, $g0 / $echelle, ($x(2, $milieu) + 2) / $echelle, $g1 / $echelle);

                // Case de la note. On écrit de gauche à droite : une note longue déborde sur la case de
                // droite, d'où la marge de ce côté ; à gauche, on coupe au trait pour ne pas montrer
                // la fin de la note voisine.
                $milieu = ($c0 + $c1) / 2;
                $largeurCase = $x(4 + $case, $milieu) - $x(3 + $case, $milieu);
                $this->copier($feuille, $source, $debutCase, $i * $h, self::LARGEUR_CASE, $h,
                    ($x(3 + $case, $milieu) - self::MARGE_GAUCHE * $largeurCase) / $echelle, $c0 / $echelle,
                    ($x(4 + $case, $milieu) + self::MARGE_DROITE * $largeurCase) / $echelle, $c1 / $echelle);
            }

            $filet = imagecolorallocate($feuille, 120, 120, 120);
            for ($i = 1; $i < count($lot); $i++) {
                imageline($feuille, 0, $i * $h, $largeurImage, $i * $h, $filet);
            }

            $chemin = $dossier.DIRECTORY_SEPARATOR.uniqid('decoupe_', true).'.jpg';
            imagejpeg($feuille, $chemin, 88);
            imagedestroy($feuille);
            $images[] = $chemin;
        }

        return $images;
    }

    /** Copie un rectangle de la photo d'origine (coins en pixels de la photo) dans l'image produite. */
    protected function copier(\GdImage $feuille, \GdImage $source, int $dx, int $dy, int $dl, int $dh, float $x0, float $y0, float $x1, float $y1): void
    {
        $sx = max(0, (int) round($x0));
        $sy = max(0, (int) round($y0));
        $sl = min(imagesx($source), (int) round($x1)) - $sx;
        $sh = min(imagesy($source), (int) round($y1)) - $sy;

        if ($sl > 0 && $sh > 0) {
            imagecopyresampled($feuille, $source, $dx, $dy, $sx, $sy, $dl, $dh, $sl, $sh);
        }
    }

    /**
     * Droite des moindres carrés v = a + b·u.
     *
     * @param  list<float>  $u
     * @param  list<float>  $v
     * @return array{0: float, 1: float}  [a, b]
     */
    protected function droite(array $u, array $v): array
    {
        $n = count($u);
        $moyenneU = array_sum($u) / $n;
        $moyenneV = array_sum($v) / $n;
        $numerateur = $denominateur = 0.0;
        foreach ($u as $i => $valeur) {
            $numerateur += ($valeur - $moyenneU) * ($v[$i] - $moyenneV);
            $denominateur += ($valeur - $moyenneU) ** 2;
        }
        $b = $denominateur > 0 ? $numerateur / $denominateur : 0.0;

        return [$moyenneV - $b * $moyenneU, $b];
    }

    /**
     * @param  list<float>  $valeurs
     * @return list<float>
     */
    protected function ecarts(array $valeurs): array
    {
        $ecarts = [];
        for ($i = 1; $i < count($valeurs); $i++) {
            $ecarts[] = $valeurs[$i] - $valeurs[$i - 1];
        }

        return $ecarts;
    }

    /**
     * @param  list<float>  $valeurs
     */
    protected function mediane(array $valeurs): float
    {
        if ($valeurs === []) {
            return 0.0;
        }

        sort($valeurs);
        $milieu = intdiv(count($valeurs), 2);

        return count($valeurs) % 2 ? $valeurs[$milieu] : ($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2;
    }
}
