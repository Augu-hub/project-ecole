<?php

use App\Services\DecoupeBordereauService;

afterEach(function () {
    foreach (glob(storage_path('app/temp/notes-images/decoupe_*.jpg')) ?: [] as $fichier) {
        @unlink($fichier);
    }
});

test('la découpe isole le matricule et la case à lire de chaque élève, sans glisser de colonne ni de ligne', function (float $angle) {
    $photo = decoupeFausseFiche(30, 6, $angle);

    $resultat = app(DecoupeBordereauService::class)->decouper($photo, 6);
    unlink($photo);

    // L'en-tête du tableau puis une ligne par élève ; 14 lignes par image
    expect($resultat)->not->toBeNull()
        ->and($resultat['lignes'])->toBe(31)
        ->and($resultat['images'])->toHaveCount(3);

    // En-tête sans pavé, puis un élève sur deux : exactement ce qui a été dessiné dans la case visée
    $attendu = array_merge([false], array_map(fn (int $i) => $i % 2 === 0, range(0, 29)));
    expect(decoupePavesLus($resultat['images']))->toBe($attendu);

    foreach ($resultat['images'] as $image) {
        expect(getimagesize($image)[0])->toBe(358);
    }
})->with([
    'photo droite' => [0.0],
    'photo penchée à gauche' => [2.5],
    'photo penchée à droite' => [-4.0],
]);

test('chaque case du bordereau se découpe à sa place, de la première à la dernière', function (int $case) {
    $photo = decoupeFausseFiche(12, $case, 1.5);

    $resultat = app(DecoupeBordereauService::class)->decouper($photo, $case);
    unlink($photo);

    expect($resultat)->not->toBeNull()
        ->and(decoupePavesLus($resultat['images']))
        ->toBe(array_merge([false], array_map(fn (int $i) => $i % 2 === 0, range(0, 11))));
})->with([0, 4, 9, 11]);

test('chaque type de note a sa case sur le bordereau, que son rang soit dans le nom ou dans le numéro', function (string $nom, int $numero, ?int $case) {
    expect((new App\Models\TypeNote(['nom' => $nom]))->caseDuBordereau($numero))->toBe($case);
})->with([
    'Interrogation n°1' => ['Interrogation', 1, 0],
    'Interrogation n°5' => ['Interrogation', 5, 4],
    'Interrogation 2' => ['Interrogation 2', 1, 1],
    'INTERROGATION 4' => ['INTERROGATION 4', 1, 3],
    'Devoir n°1' => ['Devoir', 1, 6],
    'DEVOIR n°2' => ['DEVOIR', 2, 7],
    'Devoir 2' => ['Devoir 2', 1, 7],
    'Composition' => ['Composition', 1, 9],
    'COMPOSITION' => ['COMPOSITION', 1, 9],
    // Pas de case sur le bordereau : la photo sera lue en entier
    'sixième interrogation' => ['Interrogation', 6, null],
    'troisième devoir' => ['Devoir', 3, null],
    'autre type' => ['TP', 1, null],
]);

test('une photo qui n\'est pas un bordereau n\'est pas découpée', function () {
    // Une feuille quadrillée à 8 colonnes égales : ce n'est pas la grille du bordereau
    $image = imagecreatetruecolor(1200, 900);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    $noir = imagecolorallocate($image, 20, 20, 20);
    imagesetthickness($image, 2);
    for ($x = 100; $x <= 1100; $x += 125) {
        imageline($image, $x, 100, $x, 800, $noir);
    }
    for ($y = 100; $y <= 800; $y += 50) {
        imageline($image, 100, $y, 1100, $y, $noir);
    }
    $grille = tempnam(sys_get_temp_dir(), 'grille').'.jpg';
    imagejpeg($image, $grille, 85);

    $service = app(DecoupeBordereauService::class);

    expect($service->decouper($grille, 0))->toBeNull()
        // Fichier illisible, case qui n'existe pas : jamais d'erreur, la photo sera lue en entier
        ->and($service->decouper(__FILE__, 0))->toBeNull()
        ->and($service->decouper('/fichier/absent.jpg', 0))->toBeNull()
        ->and($service->decouper($grille, 12))->toBeNull();

    unlink($grille);
});
