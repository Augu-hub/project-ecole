<?php

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// ==================== MENU LATÉRAL (MenuParRoleTest, AccesMenusTest) ====================

function membreMenu(string $role, array $attributs = []): User
{
    return User::factory()->create(array_merge(['role' => $role, 'actif' => true], $attributs));
}

/**
 * Menu latéral tel qu'il est affiché à cet utilisateur (le partiel est rendu seul, sans passer par une page).
 * Sans utilisateur : menu vu par un visiteur non connecté.
 */
function xpathMenuDe(?User $user): DOMXPath
{
    if ($user) {
        test()->actingAs($user);
    }

    $dom = new DOMDocument;
    // Le préfixe impose l'UTF-8 ; les balises que libxml ne connaît pas (aside, iconify-icon) sont tolérées
    $dom->loadHTML('<?xml encoding="UTF-8">'.view('back.layouts.partials.aside')->render(), LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($dom);
}

/**
 * Rubriques affichées à cet utilisateur, chacune avec les entrées de son sous-menu
 * (liste vide pour un lien simple).
 */
function menuDe(?User $user): array
{
    $xpath = xpathMenuDe($user);
    $libelle = fn (DOMNode $lien) => trim(preg_replace('/\s+/u', ' ', $lien->textContent));

    $menu = [];
    foreach ($xpath->query('//ul[@id="sidebar-menu"]/li') as $rubrique) {
        $entrees = [];
        foreach ($xpath->query('ul/li/a', $rubrique) as $entree) {
            $entrees[] = $libelle($entree);
        }

        $menu[$libelle($xpath->query('.//a', $rubrique)->item(0))] = $entrees;
    }

    return $menu;
}

/**
 * Liens du menu qui mènent à une page de l'application (ni « javascript: » ni « # »).
 */
function liensDuMenuDe(User $user): array
{
    $liens = [];
    foreach (xpathMenuDe($user)->query('//ul[@id="sidebar-menu"]//a/@href') as $href) {
        if (str_starts_with($href->value, url('/'))) {
            $liens[] = $href->value;
        }
    }

    return array_values(array_unique($liens));
}

/**
 * Remplace l'action de chaque route par une réponse vide : une requête ne traverse plus que
 * les vrais middlewares (auth, role:...), sans dépendre du contenu des contrôleurs.
 */
function neutraliserControleurs(): void
{
    foreach (Route::getRoutes() as $route) {
        $route->setAction(['uses' => fn () => 'ok'] + Arr::except($route->getAction(), 'controller'));
    }
}

// ==================== PHOTO D'UN BORDEREAU (DecoupeBordereauServiceTest, ImportPhotosNotesTest) ====================

/**
 * Dessine un bordereau de saisie des notes comme sur une photo : les 16 traits verticaux du
 * bordereau, une ligne d'en-tête et une ligne par élève.
 *
 * Dans la case à lire, un élève sur deux reçoit un pavé noir (en commençant par le premier élève) ;
 * les deux cases voisines en reçoivent un pour tous les élèves. Une découpe décalée d'une colonne ou
 * d'une ligne se voit donc tout de suite.
 */
function decoupeFausseFiche(int $eleves, int $case, float $angle = 0.0): string
{
    [$largeur, $hauteur] = [1400, 320 + 44 * $eleves];
    $image = imagecreatetruecolor($largeur, $hauteur);
    $blanc = imagecolorallocate($image, 255, 255, 255);
    $noir = imagecolorallocate($image, 20, 20, 20);
    imagefill($image, 0, 0, $blanc);

    [$gauche, $droite, $haut, $enTete, $ligne] = [70, 1330, 150, 70, 44];
    $parts = [0, .03, .11, .48, .522, .564, .606, .648, .69, .733, .776, .819, .862, .912, .956, 1];
    $traits = array_map(fn (float $part) => (int) round($gauche + $part * ($droite - $gauche)), $parts);
    $bas = $haut + $enTete + $ligne * $eleves;

    imagesetthickness($image, 2);
    foreach ($traits as $x) {
        imageline($image, $x, $haut, $x, $bas, $noir);
    }
    imageline($image, $gauche, $haut, $droite, $haut, $noir);
    for ($i = 0; $i <= $eleves; $i++) {
        $y = $haut + $enTete + $ligne * $i;
        imageline($image, $gauche, $y, $droite, $y, $noir);
    }

    // Un titre souligné au-dessus du tableau, comme sur la vraie fiche
    imagestring($image, 5, 560, 90, 'BORDEREAU DE SAISIE DES NOTES', $noir);
    imageline($image, 560, 110, 830, 110, $noir);

    for ($i = 0; $i < $eleves; $i++) {
        $y = $haut + $enTete + $ligne * $i;
        imagestring($image, 5, $traits[0] + 10, $y + 14, (string) ($i + 1), $noir);
        imagestring($image, 5, $traits[1] + 10, $y + 14, sprintf('26-%04d', $i + 1), $noir);
        imagestring($image, 5, $traits[2] + 10, $y + 14, 'NOM'.($i + 1).' Prenom Exemple', $noir);

        foreach ([$case - 1, $case, $case + 1] as $c) {
            if ($c < 0 || $c > 11 || ($c === $case && $i % 2 === 1)) {
                continue;
            }
            [$x0, $x1] = [$traits[3 + $c], $traits[4 + $c]];
            imagefilledrectangle($image, $x0 + 14, $y + 12, $x1 - 14, $y + $ligne - 12, $noir);
        }
    }

    if ($angle !== 0.0) {
        $image = imagerotate($image, $angle, $blanc);
    }

    $chemin = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('fiche', true).'.jpg';
    imagejpeg($image, $chemin, 85);

    return $chemin;
}

/**
 * Pour chaque ligne découpée : la case de la note contient-elle un pavé noir en son centre ?
 *
 * @return list<bool>
 */
function decoupePavesLus(array $images): array
{
    $paves = [];
    foreach ($images as $chemin) {
        $image = imagecreatefromjpeg($chemin);
        for ($y = 23; $y < imagesy($image); $y += 46) {
            // 238 = début de la case dans l'image produite (bloc « N° + matricule » de 230 px, puis 8 px de blanc)
            $paves[] = (imagecolorat($image, 238 + 60, $y) & 0xFF) < 110;
        }
    }

    return $paves;
}
