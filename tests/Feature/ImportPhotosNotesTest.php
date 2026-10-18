<?php

/*
|--------------------------------------------------------------------------
| « Photo → IA » : lecture de plusieurs photos d'une fiche de notes
|--------------------------------------------------------------------------
|
| Aucun de ces tests n'appelle la véritable API : les réponses de l'IA sont simulées
| (Http::fake) et toute requête non simulée fait échouer le test.
|
*/

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\TypeNote;
use App\Models\User;
use App\Services\ImageNoteExtractorService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as RequeteApi;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    // Jamais d'appel réel à l'IA : clé factice, et toute requête non simulée est refusée
    config(['services.mistral.api_key' => 'cle-de-test']);
    Http::preventStrayRequests();

    Sleep::fake(); // l'attente avant une nouvelle tentative n'est pas réellement attendue
    Log::spy();    // les échecs simulés ne remplissent pas le vrai journal

    // Stockage propre à chaque test : les photos à envoyer y sont créées, et le dossier temporaire
    // où l'application les dépose y est vérifié
    $this->stockage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'photos-notes-'.uniqid();
    File::ensureDirectoryExists($this->stockage);
    $this->app->useStoragePath($this->stockage);
});

afterEach(function () {
    File::deleteDirectory($this->stockage);
});

/**
 * Fiche en cours de saisie : la classe « Sixième A » et ses trois élèves, la matière, la période,
 * le type de note, un compte de l'administration et les critères mémorisés en session.
 */
function photosContexte(): array
{
    $classe = Classe::factory()->create(['niveau_id' => Niveau::factory()->create(['nom' => 'Sixième'])->id, 'suffixe' => 'A']);
    $classeAnnee = ClasseAnnee::factory()->create(['classe_id' => $classe->id, 'annee_scolaire_id' => AnneeScolaire::factory()->create()->id]);
    $matiere = Matiere::factory()->create(['nom_matiere' => 'Mathématiques']);
    $periode = Periode::factory()->create(['annee_scolaire_id' => $classeAnnee->annee_scolaire_id]);
    $typeNote = TypeNote::factory()->create(['nom' => 'Interrogation', 'max_par_periode' => 3]);

    $eleves = [
        photosEleve($classeAnnee, '25-0101', 'ABDELHAK', 'Noura'),
        photosEleve($classeAnnee, '25-0102', 'DOSSOU', 'Éric'),
        photosEleve($classeAnnee, '25-0103', 'ZINSOU', 'Paul'),
    ];

    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $criteres = [
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'periode_id' => $periode->id,
        'type_note_id' => $typeNote->id,
        'numero' => 2,
    ];

    return compact('classeAnnee', 'eleves', 'admin', 'criteres');
}

function photosEleve(ClasseAnnee $classeAnnee, string $matricule, string $nom, string $prenom): Eleve
{
    return Eleve::factory()->create(['classe_annee_id' => $classeAnnee->id] + compact('matricule', 'nom', 'prenom'));
}

/** Dossier, propre à chaque test, où les photos sont créées avant leur envoi. */
function photosDossierEnvoi(): string
{
    $dossier = storage_path('photos-a-envoyer');
    File::ensureDirectoryExists($dossier);

    return $dossier;
}

/**
 * Photo de fiche, telle que le formulaire l'envoie. L'image est fabriquée par UploadedFile::fake(), puis
 * copiée dans un fichier ordinaire : sous Windows, la photo factice garde son fichier ouvert, et un fichier
 * ouvert reste listé dans son dossier après sa suppression — on ne pourrait pas vérifier que le dossier
 * temporaire est bien vidé.
 */
function photosPhoto(string $nom = 'page.jpg'): UploadedFile
{
    $modele = UploadedFile::fake()->image($nom, 60, 40);
    $chemin = photosDossierEnvoi().DIRECTORY_SEPARATOR.uniqid('photo-').'-'.$nom;
    copy($modele->getPathname(), $chemin);

    return new UploadedFile($chemin, $nom, $modele->getMimeType(), null, true);
}

/** Envoi du formulaire « Photo → IA » depuis la page de saisie. */
function photosEnvoyer(TestCase $test, array $ctx, array $donnees): TestResponse
{
    return $test->actingAs($ctx['admin'])
        ->withSession(['note_criteres' => $ctx['criteres']])
        ->from(route('admin.notes.preview'))
        ->post(route('admin.notes.import-image'), $donnees);
}

/** Une ligne de la fiche, telle que l'IA la renvoie. */
function photosLigne(string $matricule, string $nom, string $prenom, $note, string $commentaire = ''): array
{
    return compact('matricule', 'nom', 'prenom', 'note', 'commentaire');
}

/** Corps d'une réponse de l'API : le texte de l'IA est dans le message de l'assistant. */
function photosCorps(string $texte, string $fin = 'stop'): array
{
    return ['choices' => [['finish_reason' => $fin, 'message' => ['role' => 'assistant', 'content' => $texte]]]];
}

/** Réponse de l'IA pour une photo lue. */
function photosLecture(array $lignes, string $confiance = 'high', array $remarques = [])
{
    return Http::response(photosCorps(json_encode([
        'success' => true,
        'notes' => $lignes,
        'confidence' => $confiance,
        'warnings' => $remarques,
    ])));
}

/** L'API simulée répond, dans l'ordre, une réponse par appel. */
function photosApi(...$reponses): void
{
    Http::fake(['api.mistral.ai/*' => Http::sequence($reponses)]);
}

/** Lignes du résultat gardées en session, réduites aux colonnes demandées. */
function photosResultat(array $colonnes): array
{
    return array_map(fn (array $ligne) => Arr::only($ligne, $colonnes), session('imported_data'));
}

/** Fichiers présents dans le dossier temporaire des photos. */
function photosFichiersTemporaires(): array
{
    $dossier = storage_path('app/temp/notes-images');

    return is_dir($dossier) ? File::files($dossier) : [];
}

/**
 * Photo JPEG prise avec un téléphone : moitié gauche rouge, moitié droite bleue, et la balise
 * EXIF « Orientation » indiquant comment la tourner pour la voir à l'endroit.
 */
function photosJpegOriente(int $orientation, int $largeur = 80, int $hauteur = 40): string
{
    $image = imagecreatetruecolor($largeur, $hauteur);
    imagefilledrectangle($image, 0, 0, intdiv($largeur, 2) - 1, $hauteur - 1, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, intdiv($largeur, 2), 0, $largeur - 1, $hauteur - 1, imagecolorallocate($image, 0, 0, 255));

    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = ob_get_clean();

    // Bloc EXIF minimal : un répertoire d'une seule balise, Orientation (0x0112)
    $exif = "Exif\x00\x00"."II*\x00\x08\x00\x00\x00"."\x01\x00"."\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00"."\x00\x00\x00\x00";
    $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    $chemin = photosDossierEnvoi().DIRECTORY_SEPARATOR.uniqid('telephone-').'.jpg';
    file_put_contents($chemin, $jpeg);

    return $chemin;
}

/** Couleur dominante d'un point de l'image : « rouge », « bleu » ou « autre ». */
function photosCouleur(GdImage $image, int $x, int $y): string
{
    $rvb = imagecolorsforindex($image, imagecolorat($image, $x, $y));

    return match (true) {
        $rvb['red'] > 180 && $rvb['blue'] < 90 => 'rouge',
        $rvb['blue'] > 180 && $rvb['red'] < 90 => 'bleu',
        default => 'autre',
    };
}

// ==================== PLUSIEURS PHOTOS ====================

test("deux photos sont lues l'une après l'autre et réunies en un seul résultat", function () {
    $ctx = photosContexte();
    [$noura, $eric, $paul] = $ctx['eleves'];

    photosApi(
        photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12), photosLigne('25-0102', 'DOSSOU', 'Éric', 8.5, 'Peut mieux faire')]),
        photosLecture([photosLigne('25-0103', 'ZINSOU', 'Paul', 17)], 'medium', ['Bas de page flou']),
    );

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto('page-1.jpg'), photosPhoto('page-2.jpg')]])
        ->assertOk()
        ->assertViewIs('back.pages.notes.import_preview')
        ->assertViewHas('hasErrors', false)
        ->assertSee('Lecture de 2 photos par IA réussie')
        ->assertSee('3 notes lues sur 3 élèves.')
        // Les remarques que le modèle ajoute de lui-même ne sont pas montrées
        ->assertDontSee('Bas de page flou')
        ->assertDontSee('Confiance')
        ->assertSee('Appliquer ces notes dans le formulaire')
        ->assertDontSee('Mistral')
        ->assertDontSee('ri-robot-line', false);

    Http::assertSentCount(2);

    expect(session('imported_data'))->toEqual([
        ['matricule' => '25-0101', 'nom' => 'ABDELHAK', 'prenom' => 'Noura', 'note' => 12, 'commentaire' => '', 'eleve_id' => $noura->id, 'errors' => [], 'matched_by' => 'ia_vision'],
        ['matricule' => '25-0102', 'nom' => 'DOSSOU', 'prenom' => 'Éric', 'note' => 8.5, 'commentaire' => 'Peut mieux faire', 'eleve_id' => $eric->id, 'errors' => [], 'matched_by' => 'ia_vision'],
        ['matricule' => '25-0103', 'nom' => 'ZINSOU', 'prenom' => 'Paul', 'note' => 17, 'commentaire' => '', 'eleve_id' => $paul->id, 'errors' => [], 'matched_by' => 'ia_vision'],
    ])
        ->and(session('ia_meta'))->toBe(['photos' => 2, 'lues' => 3, 'effectif' => 3, 'unmatched' => 0, 'warnings' => []]);

    // « Appliquer ces notes dans le formulaire » : la saisie est préremplie avec les trois notes
    $saisie = $this->actingAs($ctx['admin'])->get(route('admin.notes.preview'))->assertOk();

    expect($saisie->viewData('importedNotes'))->toEqual([
        $noura->id => ['valeur' => 12, 'commentaire' => ''],
        $eric->id => ['valeur' => 8.5, 'commentaire' => 'Peut mieux faire'],
        $paul->id => ['valeur' => 17, 'commentaire' => ''],
    ]);
});

test("un élève présent sur deux photos n'a qu'une ligne : la première note lue est gardée et l'écart est signalé", function () {
    $ctx = photosContexte();
    [$noura, $eric, $paul] = $ctx['eleves'];

    photosApi(
        photosLecture([
            photosLigne('25-0101', 'ABDELHAK', 'Noura', 12),
            photosLigne('25-0102', 'DOSSOU', 'Éric', null),       // note illisible sur la première photo
            photosLigne('25-0103', 'ZINSOU', 'Paul', 14),
        ]),
        photosLecture([
            photosLigne('25-0101', 'ABDELHAK', 'Noura', 15),      // autre note pour la même élève
            photosLigne('25-0102', 'DOSSOU', 'Éric', 9.5, 'Rattrapage'),
            photosLigne('25-0103', 'ZINSOU', 'Paul', 14),         // même note : rien à signaler
        ]),
    );

    $avertissement = 'ABDELHAK Noura : deux notes différentes lues (12 et 15). Vérifiez.';

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto(), photosPhoto()]])
        ->assertOk()
        ->assertViewHas('hasErrors', false)
        ->assertSee($avertissement);

    expect(photosResultat(['eleve_id', 'note', 'commentaire']))->toEqual([
        ['eleve_id' => $noura->id, 'note' => 12, 'commentaire' => ''],
        ['eleve_id' => $eric->id, 'note' => 9.5, 'commentaire' => 'Rattrapage'],   // première note lisible
        ['eleve_id' => $paul->id, 'note' => 14, 'commentaire' => ''],
    ])
        ->and(session('ia_meta.warnings'))->toBe([$avertissement])
        ->and(session('ia_meta.photos'))->toBe(2);
});

test('une photo illisible parmi deux est signalée et la lecture continue avec l\'autre', function () {
    $ctx = photosContexte();
    [$noura] = $ctx['eleves'];

    photosApi(
        photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)], 'high'),
        Http::response('Service Unavailable', 503),   // seconde photo : le service est en panne…
        Http::response('Service Unavailable', 503),   // … et l'est encore aux deux nouvelles tentatives
        Http::response('Service Unavailable', 503),
    );

    $avertissement = "Photo n° 2 : non lue. Le service de lecture automatique n'a pas répondu. Réessayez dans quelques instants.";

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto('page-1.jpg'), photosPhoto('page-2.jpg')]])
        ->assertOk()
        ->assertViewHas('hasErrors', false)
        ->assertSee('Lecture de la photo par IA réussie')
        ->assertSee('1 note lue sur 3 élèves.')
        ->assertSee('Les autres sont à saisir à la main.')
        ->assertSee('À saisir')
        ->assertSee($avertissement);

    Http::assertSentCount(4);

    // Toute la classe est listée : la note lue, et une case à saisir pour les deux autres élèves
    expect(photosResultat(['eleve_id', 'note']))->toEqual([
        ['eleve_id' => $noura->id, 'note' => 12],
        ['eleve_id' => $ctx['eleves'][1]->id, 'note' => null],
        ['eleve_id' => $ctx['eleves'][2]->id, 'note' => null],
    ])
        ->and(session('ia_meta'))->toBe(['photos' => 1, 'lues' => 1, 'effectif' => 3, 'unmatched' => 0, 'warnings' => [$avertissement]])
        ->and(photosFichiersTemporaires())->toBeEmpty();
});

test("si aucune photo n'est lisible, retour à la saisie avec un message clair et rien n'est retenu", function (string $cas, int $photos, string $message) {
    $ctx = photosContexte();

    match ($cas) {
        'clé refusée' => Http::fake(['api.mistral.ai/*' => Http::response(['message' => 'Unauthorized'], 401)]),
        'réponse coupée' => Http::fake(['api.mistral.ai/*' => Http::response(photosCorps('{"success": true, "notes": [{"matricule": "25-0101", "nom": "ABDEL', 'length'))]),
        'réponse sans JSON' => Http::fake(['api.mistral.ai/*' => Http::response(photosCorps("Je ne vois pas de fiche de notes sur cette image."))]),
        'aucune note' => Http::fake(['api.mistral.ai/*' => photosLecture([])]),
        'service muet' => Http::fake(['api.mistral.ai/*' => Http::failedConnection()]),
        'lecture non configurée' => config(['services.mistral.api_key' => null]),
    };

    photosEnvoyer($this, $ctx, ['image_files' => array_map(fn () => photosPhoto(), range(1, $photos))])
        ->assertRedirect(route('admin.notes.preview'))
        ->assertSessionHas('error', $message)
        ->assertSessionMissing('imported_data')
        ->assertSessionMissing('ia_meta');

    // Des phrases simples : ni symbole, ni mot technique, ni nom du fournisseur
    expect($message)->not->toContain('❌')
        ->not->toContain('Extraction')
        ->not->toContain('Mistral')
        ->not->toContain('JSON')
        ->and(photosFichiersTemporaires())->toBeEmpty();

    // Le retour se fait sur la page de saisie, qui affiche le message
    $this->actingAs($ctx['admin'])->get(route('admin.notes.preview'))->assertOk()->assertSee($message);
})->with([
    'clé refusée par le service' => ['clé refusée', 1, "La photo n'a pas pu être lue : le service de lecture automatique n'a pas répondu. Réessayez dans quelques instants."],
    'réponse coupée (fiche trop longue)' => ['réponse coupée', 1, "La photo n'a pas pu être lue : la photo contient trop de lignes pour être lue en une fois. Photographiez la fiche en deux parties."],
    'réponse qui ne contient pas de notes' => ['réponse sans JSON', 1, "La photo n'a pas pu être lue : les notes n'ont pas pu être reconnues sur cette photo. Réessayez avec une photo plus nette."],
    'photo sans aucune note' => ['aucune note', 1, "La photo n'a pas pu être lue : aucune note n'a été trouvée sur cette photo."],
    'service injoignable' => ['service muet', 1, "La photo n'a pas pu être lue : le service de lecture automatique n'a pas répondu à temps. Réessayez dans quelques instants."],
    'lecture automatique non configurée' => ['lecture non configurée', 1, "La photo n'a pas pu être lue : la lecture automatique des photos n'est pas disponible pour le moment. Saisissez les notes manuellement."],
    'trois photos, toutes illisibles' => ['clé refusée', 3, "Aucune des 3 photos n'a pu être lue : le service de lecture automatique n'a pas répondu. Réessayez dans quelques instants."],
]);

test("l'ancien champ unique « image_file » est toujours accepté", function () {
    $ctx = photosContexte();
    [$noura] = $ctx['eleves'];

    photosApi(photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 13.25)]));

    photosEnvoyer($this, $ctx, ['image_file' => photosPhoto('fiche.png')])
        ->assertOk()
        ->assertViewHas('hasErrors', false)
        ->assertSee('Lecture de la photo par IA réussie');

    Http::assertSentCount(1);

    expect(session('imported_data.0'))->toMatchArray(['eleve_id' => $noura->id, 'note' => 13.25])
        ->and(session('ia_meta.photos'))->toBe(1);
});

// ==================== CONTRÔLE DES FICHIERS ENVOYÉS ====================

test('un envoi refusé donne une erreur de formulaire en français et rien n\'est envoyé à l\'IA', function (array $donnees, string $champ, string $message) {
    $ctx = photosContexte();
    Http::fake();

    photosEnvoyer($this, $ctx, $donnees)
        ->assertRedirect(route('admin.notes.preview'))
        ->assertSessionHasErrors([$champ => $message])
        ->assertSessionMissing('imported_data');

    Http::assertNothingSent();
})->with([
    // Les fichiers sont créés au moment du test (fonctions appelées par Pest après la préparation)
    'six photos' => [
        fn () => ['image_files' => array_map(fn () => photosPhoto(), range(1, 6))],
        'image_files', 'Le champ photos ne doit pas contenir plus de 5 éléments.',
    ],
    'aucune photo' => [
        fn () => [],
        'image_files', 'Le champ photos est obligatoire.',
    ],
    'un fichier qui n\'est pas une image' => [
        fn () => ['image_files' => [photosPhoto(), UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf')]],
        'image_files.1', 'Le champ photo doit être une image.',
    ],
    'une image d\'un format non accepté' => [
        fn () => ['image_files' => [UploadedFile::fake()->image('fiche.gif')]],
        'image_files.0', 'Le fichier photo doit être de type : jpeg, jpg, png, webp.',
    ],
    'une photo que le serveur n\'a pas pu recevoir (trop lourde)' => [
        fn () => ['image_files' => [
            photosPhoto(),
            new UploadedFile(photosJpegOriente(1), 'grande.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true),
        ]],
        'image_files.1', "La photo n° 2 n'a pas pu être envoyée : elle est sans doute trop lourde.",
    ],
]);

// ==================== RECONNAISSANCE DES ÉLÈVES ====================

test('un élève dont le matricule est mal lu est retrouvé par son nom', function () {
    $ctx = photosContexte();
    [$noura, $eric, $paul] = $ctx['eleves'];
    $marie = photosEleve($ctx['classeAnnee'], 'ab-77', 'DE SOUZA', 'Marie-Ange');

    photosApi(photosLecture([
        photosLigne('ZZ-9999', 'Dossou', 'ERIC', 11),              // matricule inconnu ; nom sans accent, autre casse
        photosLigne('', 'ZINSOU  PAUL', '', 7),                    // nom et prénom dans la même cellule
        photosLigne('', 'DE', 'Souza Marie-Ange', 16),             // nom composé coupé au mauvais endroit
        photosLigne(' AB-77 ', '', '', 18),                        // matricule seul, autre casse, espaces autour
        photosLigne('25-0101', 'Nom illisible', '', 9),            // le matricule l'emporte sur le nom
    ]));

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])->assertOk()->assertViewHas('hasErrors', false);

    // Chaque élève reconnu est affiché avec son identité en base, pas avec ce qui a été lu,
    // et dans l'ordre de la classe (celui de la fiche), pas dans l'ordre de lecture
    expect(photosResultat(['matricule', 'nom', 'prenom', 'note', 'eleve_id']))->toEqual([
        ['matricule' => '25-0101', 'nom' => 'ABDELHAK', 'prenom' => 'Noura', 'note' => 9, 'eleve_id' => $noura->id],
        ['matricule' => 'ab-77', 'nom' => 'DE SOUZA', 'prenom' => 'Marie-Ange', 'note' => 16, 'eleve_id' => $marie->id],
        ['matricule' => '25-0102', 'nom' => 'DOSSOU', 'prenom' => 'Éric', 'note' => 11, 'eleve_id' => $eric->id],
        ['matricule' => '25-0103', 'nom' => 'ZINSOU', 'prenom' => 'Paul', 'note' => 7, 'eleve_id' => $paul->id],
    ])
        // La 3e et la 4e ligne désignent la même élève : une seule ligne, et l'écart de note est signalé
        ->and(session('ia_meta.warnings'))->toBe(['DE SOUZA Marie-Ange : deux notes différentes lues (16 et 18). Vérifiez.'])
        ->and(session('ia_meta.unmatched'))->toBe(0);
});

test('deux élèves de même nom ne sont jamais départagés par le nom seul', function () {
    $ctx = photosContexte();
    $homonyme = photosEleve($ctx['classeAnnee'], '25-0199', 'ZINSOU', 'Paul');

    photosApi(photosLecture([
        photosLigne('', 'ZINSOU', 'Paul', 10),          // lequel des deux ? aucun n'est retenu
        photosLigne('25-0199', 'ZINSOU', 'Paul', 12),   // le matricule désigne le second
    ]));

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])->assertOk()->assertViewHas('hasErrors', false);

    $notes = collect(session('imported_data'))->whereNotNull('eleve_id')->pluck('note', 'eleve_id');

    // La ligne sans matricule n'est rattachée à aucun des deux : elle vient après la classe, signalée
    expect($notes->get($homonyme->id))->toEqual(12)
        ->and($notes->get($ctx['eleves'][2]->id))->toBeNull()
        ->and(collect(photosResultat(['eleve_id', 'note', 'errors']))->last())
        ->toEqual(['eleve_id' => null, 'note' => 10, 'errors' => ['Élève non reconnu']]);
});

test("une ligne non reconnue ne bloque pas les autres : elle est signalée et n'est pas reprise", function () {
    $ctx = photosContexte();
    [$noura] = $ctx['eleves'];
    $inconnue = photosLigne('99-0000', 'INCONNU', 'Jean', 11);

    photosApi(
        photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12), $inconnue]),
        photosLecture([$inconnue]),   // la même ligne, lue aussi sur la seconde photo : gardée une seule fois
    );

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto(), photosPhoto()]])
        ->assertOk()
        ->assertViewHas('hasErrors', false)
        ->assertSee('1 élève(s) non reconnu(s) : non repris')
        ->assertSee('Élève non reconnu')
        ->assertSee('Appliquer ces notes dans le formulaire');

    $lignes = session('imported_data');

    // Les trois élèves de la classe, puis la ligne non reconnue
    expect($lignes)->toHaveCount(4)
        ->and($lignes[3])->toEqual(['matricule' => '99-0000', 'nom' => 'INCONNU', 'prenom' => 'Jean', 'note' => 11, 'commentaire' => '', 'eleve_id' => null, 'errors' => ['Élève non reconnu'], 'matched_by' => 'ia_vision'])
        // Le nombre annoncé est celui des lignes sans élève
        ->and(session('ia_meta.unmatched'))->toBe(collect($lignes)->whereNull('eleve_id')->count())
        ->and(session('ia_meta.unmatched'))->toBe(1);

    // Seule la note de l'élève reconnue arrive dans le formulaire de saisie
    $saisie = $this->actingAs($ctx['admin'])->get(route('admin.notes.preview'))->assertOk();

    expect($saisie->viewData('importedNotes'))->toEqual([$noura->id => ['valeur' => 12, 'commentaire' => '']]);
});

test("si aucun élève n'est reconnu, les notes ne peuvent pas être appliquées", function () {
    $ctx = photosContexte();

    photosApi(photosLecture([photosLigne('99-0000', 'INCONNU', 'Jean', 11), photosLigne('99-0001', 'AUTRE', 'Classe', 8)]));

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])
        ->assertOk()
        ->assertViewHas('hasErrors', true)
        ->assertSee("Aucune note n'a pu être rattachée à un élève de cette classe.", false)
        ->assertDontSee('réussie')
        ->assertSee('2 élève(s) non reconnu(s) : non repris')
        ->assertDontSee('Appliquer ces notes dans le formulaire')
        ->assertDontSee('Corrigez le fichier');

    expect(session('ia_meta.unmatched'))->toBe(2);
});

test("une ligne réduite au matricule et à la note est acceptée, et le reste d'une réponse imparfaite est ignoré", function () {
    $ctx = photosContexte();
    [$noura, $eric] = $ctx['eleves'];

    Http::fake(['api.mistral.ai/*' => Http::response(photosCorps(json_encode([
        'notes' => [
            ['matricule' => '25-0101', 'note' => 13],            // ni nom, ni prénom, ni commentaire
            ['matricule' => '25-0102', 'note' => '12,5'],        // note écrite à la française
            ['note' => 10],                                      // ne désigne personne : ignorée
            'ligne illisible',                                   // pas une ligne : ignorée
            ['matricule' => 250103, 'nom' => null, 'prenom' => ['Paul'], 'note' => 'absent', 'commentaire' => null],
        ],
        'confidence' => 'très bonne',                            // valeur inattendue
        'warnings' => ['Écriture difficile', '', ['x'], null],
    ])))]);

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])
        ->assertOk()
        ->assertViewHas('hasErrors', false)
        ->assertSee('ABDELHAK')
        ->assertSee('Noura');

    expect(photosResultat(['matricule', 'nom', 'prenom', 'note', 'commentaire', 'eleve_id']))->toEqual([
        ['matricule' => '25-0101', 'nom' => 'ABDELHAK', 'prenom' => 'Noura', 'note' => 13, 'commentaire' => '', 'eleve_id' => $noura->id],
        ['matricule' => '25-0102', 'nom' => 'DOSSOU', 'prenom' => 'Éric', 'note' => 12.5, 'commentaire' => '', 'eleve_id' => $eric->id],
        // Élève de la classe que la photo n'a pas permis de noter : à saisir à la main
        ['matricule' => '25-0103', 'nom' => 'ZINSOU', 'prenom' => 'Paul', 'note' => null, 'commentaire' => '', 'eleve_id' => $ctx['eleves'][2]->id],
        ['matricule' => '250103', 'nom' => '', 'prenom' => '', 'note' => null, 'commentaire' => '', 'eleve_id' => null],
    ])
        // Ni la « confiance » ni les remarques du modèle ne sont reprises
        ->and(session('ia_meta'))->toBe(['photos' => 1, 'lues' => 2, 'effectif' => 3, 'unmatched' => 1, 'warnings' => []]);
});

// ==================== DEMANDE ENVOYÉE À L'IA ====================

test("la demande envoyée à l'IA laisse la place à une classe entière et exige une réponse en JSON", function () {
    $ctx = photosContexte();
    photosApi(photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)]));

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])->assertOk();

    Http::assertSent(function (RequeteApi $requete) {
        [$image, $consigne] = $requete['messages'][1]['content'];

        return $requete->url() === 'https://api.mistral.ai/v1/chat/completions'
            && $requete->hasHeader('Authorization', 'Bearer cle-de-test')
            && $requete['max_tokens'] >= 4000
            && $requete['temperature'] === 0
            && $requete['response_format'] === ['type' => 'json_object']
            && str_starts_with($image['image_url']['url'], 'data:image/jpeg;base64,')
            // Le contexte de la fiche accompagne la photo
            && str_contains($consigne['text'], 'Sixième A')
            && str_contains($consigne['text'], 'Mathématiques')
            && str_contains($consigne['text'], 'Interrogation n°2');
    });
});

test('un échec passager du service est relancé après une courte attente', function (string $premierEssai) {
    $appels = 0;
    Http::fake(['api.mistral.ai/*' => function () use (&$appels, $premierEssai) {
        if (++$appels > 1) {
            return photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)]);
        }

        return match ($premierEssai) {
            'service saturé' => Http::response('Too Many Requests', 429),
            'erreur du service' => Http::response('Bad Gateway', 502),
            'connexion impossible' => throw new ConnectionException('cURL error 7: Failed to connect'),
        };
    }]);

    $resultat = app(ImageNoteExtractorService::class)->extractNotesFromImage(photosJpegOriente(1));

    expect($resultat['success'])->toBeTrue()
        ->and($resultat['notes'])->toHaveCount(1)
        ->and($appels)->toBe(2);

    Sleep::assertSequence([Sleep::for(2000)->milliseconds()]);
})->with(['service saturé', 'erreur du service', 'connexion impossible']);

test('le service saturé est relancé deux fois au plus, en attendant plus longtemps la seconde fois', function (int $echecs, bool $lue) {
    $appels = 0;
    Http::fake(['api.mistral.ai/*' => function () use (&$appels, $echecs) {
        return ++$appels > $echecs
            ? photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)])
            // Réponse réelle du service quand il manque de capacité
            : Http::response(['message' => 'Not enough capacity available for this request, please retry later.', 'type' => 'backend_out_of_capacity'], 429);
    }]);

    $resultat = app(ImageNoteExtractorService::class)->extractNotesFromImage(photosJpegOriente(1));

    expect($resultat['success'])->toBe($lue)
        ->and($appels)->toBe(3);

    Sleep::assertSequence([Sleep::for(2000)->milliseconds(), Sleep::for(6000)->milliseconds()]);
})->with([
    'deux échecs puis une réponse' => [2, true],
    'trois échecs : abandon' => [3, false],
]);

test("une demande refusée n'est pas relancée : elle échouerait à l'identique", function (int $statut) {
    $appels = 0;
    Http::fake(['api.mistral.ai/*' => function () use (&$appels, $statut) {
        $appels++;

        return Http::response(['message' => 'refus'], $statut);
    }]);

    $resultat = app(ImageNoteExtractorService::class)->extractNotesFromImage(photosJpegOriente(1));

    expect($resultat['success'])->toBeFalse()
        ->and($resultat['errors'])->toBe(["Le service de lecture automatique n'a pas répondu. Réessayez dans quelques instants."])
        ->and($appels)->toBe(1);

    Sleep::assertNeverSlept();
})->with(['clé refusée' => [401], 'demande invalide' => [400], 'photo refusée' => [422]]);

test("après une longue attente sans réponse, la demande n'est pas relancée", function () {
    $appels = 0;
    Http::fake(['api.mistral.ai/*' => function () use (&$appels) {
        $appels++;
        $this->travel(90)->seconds();   // le service n'a rien répondu pendant tout le délai accordé

        throw new ConnectionException('cURL error 28: Operation timed out');
    }]);

    $resultat = app(ImageNoteExtractorService::class)->extractNotesFromImage(photosJpegOriente(1));

    expect($resultat['errors'])->toBe(["Le service de lecture automatique n'a pas répondu à temps. Réessayez dans quelques instants."])
        ->and($appels)->toBe(1);

    Sleep::assertNeverSlept();
});

test('le détail technique d\'un échec va dans le journal, jamais dans le message', function () {
    Http::fake(['api.mistral.ai/*' => Http::response(['message' => 'Invalid model: pixtral'], 400)]);

    $resultat = app(ImageNoteExtractorService::class)->extractNotesFromImage(photosJpegOriente(1));

    expect(implode(' ', $resultat['errors']))->not->toContain('400')
        ->not->toContain('Invalid model')
        ->not->toContain('Mistral');

    Log::shouldHaveReceived('error')->withArgs(fn (string $ligne) => str_contains($ligne, '400') && str_contains($ligne, 'Invalid model'))->once();
});

test('une photo de téléphone prise de côté est remise à l\'endroit avant d\'être envoyée à l\'IA', function (int $orientation, array $taille, array $couleurs) {
    $ctx = photosContexte();
    $photo = photosJpegOriente($orientation);

    expect(exif_read_data($photo)['Orientation'])->toBe($orientation);

    photosApi(photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)]));

    photosEnvoyer($this, $ctx, ['image_files' => [new UploadedFile($photo, 'telephone.jpg', 'image/jpeg', null, true)]])->assertOk();

    // L'image réellement envoyée à l'IA
    $adresse = Http::recorded()->first()[0]['messages'][1]['content'][0]['image_url']['url'];
    $envoyee = imagecreatefromstring(base64_decode(substr($adresse, strlen('data:image/jpeg;base64,'))));
    [$largeur, $hauteur] = [imagesx($envoyee), imagesy($envoyee)];

    // Couleur des coins haut-gauche et bas-droit : la moitié rouge était à gauche sur la photo d'origine
    expect([$largeur, $hauteur])->toBe($taille)
        ->and([photosCouleur($envoyee, 5, 5), photosCouleur($envoyee, $largeur - 6, $hauteur - 6)])->toBe($couleurs);
})->with([
    'photo droite (1)' => [1, [80, 40], ['rouge', 'bleu']],
    'photo à l\'envers (3)' => [3, [80, 40], ['bleu', 'rouge']],
    'quart de tour à droite (6)' => [6, [40, 80], ['rouge', 'bleu']],
    'quart de tour à gauche (8)' => [8, [40, 80], ['bleu', 'rouge']],
])->skip(! function_exists('exif_read_data'), "L'extension exif n'est pas installée.");

// ==================== FICHIERS TEMPORAIRES ====================

test('chaque photo est déposée dans le dossier temporaire le temps de sa lecture, puis supprimée', function () {
    $ctx = photosContexte();
    $presentes = [];

    Http::fake(['api.mistral.ai/*' => function () use (&$presentes) {
        $presentes[] = count(photosFichiersTemporaires());   // pendant l'appel : la photo en cours, et elle seule

        // Deuxième photo illisible : son appel (le 2e) et ses deux nouvelles tentatives (3e et 4e) échouent
        return in_array(count($presentes), [2, 3, 4], true)
            ? Http::response('Internal Server Error', 500)
            : photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)]);
    }]);

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto('a.jpg'), photosPhoto('b.png'), photosPhoto('c.webp')]])->assertOk();

    expect($presentes)->toBe([1, 1, 1, 1, 1])
        ->and(photosFichiersTemporaires())->toBeEmpty()
        ->and(session('ia_meta.photos'))->toBe(2);
});

test('le fichier temporaire est supprimé même si la lecture plante, et l\'utilisateur ne voit aucun détail technique', function () {
    Exceptions::fake();
    $ctx = photosContexte();
    $presentes = null;

    $this->mock(ImageNoteExtractorService::class)
        ->shouldReceive('extractNotesFromImage')->once()
        ->andReturnUsing(function () use (&$presentes) {
            $presentes = count(photosFichiersTemporaires());

            throw new RuntimeException('disk I/O error on /var/www');
        });

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])
        ->assertRedirect(route('admin.notes.preview'))
        ->assertSessionHas('error', "La photo n'a pas pu être lue : une erreur est survenue pendant la lecture. Réessayez.");

    expect($presentes)->toBe(1)
        ->and(photosFichiersTemporaires())->toBeEmpty();

    Exceptions::assertReported(RuntimeException::class);
});

test("la réduction d'une photo ne laisse aucun fichier derrière elle", function () {
    $photo = photosJpegOriente(1, 3000, 1500);

    $reduite = (fn () => $this->compressImage($photo))->call(app(ImageNoteExtractorService::class));

    [$largeur, $hauteur] = getimagesize($reduite);
    unlink($reduite);

    // Le nom réservé pour le fichier réduit (même chemin, sans « .jpg ») n'existe plus
    expect([$largeur, $hauteur])->toBe([2000, 1000])
        ->and(file_exists(substr($reduite, 0, -4)))->toBeFalse();
});

// ==================== FENÊTRE « PHOTO → IA » ====================

test('la fenêtre « Photo → IA » accepte plusieurs photos et ne nomme pas le fournisseur de l\'IA', function () {
    $ctx = photosContexte();

    $this->actingAs($ctx['admin'])
        ->withSession(['note_criteres' => $ctx['criteres']])
        ->get(route('admin.notes.preview'))
        ->assertOk()
        ->assertSee('Photos de la fiche')
        ->assertSee('Une photo par page, 5 au maximum.')
        ->assertSee('id="image_files" name="image_files[]" multiple', false)
        ->assertSee('accept="image/jpeg,image/png,image/webp"', false)
        // Le script qui ajoute, réduit et affiche les photos est bien envoyé au navigateur
        ->assertSee('function installerPhotosFiche()', false)
        ->assertSee('.photos-fiche__retirer', false)
        ->assertDontSee('name="image_file"', false)
        ->assertDontSee('Mistral')
        ->assertDontSee('ri-robot-line', false);
});

// ==================== ENVOI TROP VOLUMINEUX (413) ====================

test("un envoi trop volumineux pour le serveur renvoie à la page précédente avec un message, jamais une page d'erreur", function () {
    $message = 'Les fichiers envoyés sont trop volumineux. Envoyez-en moins à la fois ou des fichiers plus légers.';
    $ctx = photosContexte();

    // Le serveur refuse l'envoi dès l'arrivée de la requête, avant même l'ouverture de la session
    $reponse = $this->withServerVariables(['CONTENT_LENGTH' => 10 ** 12])
        ->from(route('admin.notes.preview'))
        ->post(route('admin.notes.import-image'))
        ->assertRedirect(route('admin.notes.preview'))
        ->assertSessionHas('error', $message)
        ->assertCookie(config('session.cookie'));

    // Le message est réellement enregistré dans la session : il sera encore là à la page suivante
    $session = app('session.store');

    expect($session->getHandler()->read($session->getId()))->toContain('trop volumineux');

    // … et la page de saisie l'affiche
    $this->withServerVariables([])
        ->actingAs($ctx['admin'])
        ->withSession(['note_criteres' => $ctx['criteres']])
        ->get($reponse->headers->get('Location'))
        ->assertOk()
        ->assertSee($message);
})->skip(fn () => (int) ini_get('post_max_size') === 0, 'Aucune limite de taille sur ce serveur (post_max_size = 0).');

test('le même message est donné quand le refus survient dans une page déjà ouverte en session', function () {
    Route::post('/_test/envoi-trop-volumineux', fn () => throw new PostTooLargeException)->middleware('web');

    $this->from('/login')
        ->post('/_test/envoi-trop-volumineux', ['login' => 'admin@ecole.bj'])
        ->assertRedirect('/login')
        ->assertSessionHas('error', 'Les fichiers envoyés sont trop volumineux. Envoyez-en moins à la fois ou des fichiers plus légers.');
});

test("un envoi trop volumineux depuis l'application mobile garde une réponse d'erreur, en français", function () {
    Route::post('/_test/envoi-trop-volumineux', fn () => throw new PostTooLargeException)->middleware('web');

    $this->postJson('/_test/envoi-trop-volumineux')
        ->assertStatus(413)
        ->assertExactJson(['message' => 'Les fichiers envoyés sont trop volumineux.']);

    // Une route de l'API appelée sans en-tête JSON n'est pas redirigée vers une page
    $this->withServerVariables(['CONTENT_LENGTH' => 10 ** 12])
        ->post('/api/route-qui-n-existe-pas')
        ->assertStatus(413);
})->skip(fn () => (int) ini_get('post_max_size') === 0, 'Aucune limite de taille sur ce serveur (post_max_size = 0).');

// ==================== LECTURE CASE PAR CASE (BORDEREAU RECONNU) ====================

/** Images jointes à la demande envoyée à l'IA, décodées. */
function photosImagesEnvoyees(RequeteApi $requete): array
{
    return collect($requete['messages'][1]['content'])
        ->where('type', 'image_url')
        ->map(fn (array $partie) => imagecreatefromstring(base64_decode(substr($partie['image_url']['url'], strlen('data:image/jpeg;base64,')))))
        ->values()->all();
}

/** Consigne (texte) jointe à la demande envoyée à l'IA. */
function photosConsigne(RequeteApi $requete): string
{
    return collect($requete['messages'][1]['content'])->firstWhere('type', 'text')['text'];
}

test("la photo d'un bordereau est découpée : l'IA ne reçoit que le matricule et la case de la note à lire", function () {
    $ctx = photosContexte();
    [$noura, $eric, $paul] = $ctx['eleves'];

    // Bordereau de 12 élèves, un peu penché. « Interrogation n°2 » s'écrit dans la 2e case de notes :
    // un élève sur deux y a un pavé noir, alors que les cases voisines en ont un sur chaque ligne.
    $bordereau = decoupeFausseFiche(12, 1, 1.5);

    // Avec plusieurs images, le modèle répond par une liste : un objet « notes » par image
    Http::fake(['api.mistral.ai/*' => Http::response(photosCorps(json_encode([
        ['notes' => [['matricule' => '25-0101', 'note' => 12.5], ['matricule' => '25-0102', 'note' => null]]],
        ['notes' => [['matricule' => '25-0103', 'note' => '08']]],
    ])))]);

    photosEnvoyer($this, $ctx, ['image_files' => [new UploadedFile($bordereau, 'bordereau.jpg', 'image/jpeg', null, true)]])
        ->assertOk()
        ->assertViewHas('hasErrors', false)
        ->assertSee('2 notes lues sur 3 élèves.');

    Http::assertSentCount(1);
    Http::assertSent(function (RequeteApi $requete) {
        $images = photosImagesEnvoyees($requete);
        $consigne = photosConsigne($requete);

        // En-tête + 12 élèves = 13 lignes de 46 px, dans une image étroite : matricule (230 px) et case (120 px)
        expect($images)->toHaveCount(1)
            ->and([imagesx($images[0]), imagesy($images[0])])->toBe([358, 13 * 46]);

        // C'est bien la 2e case qui a été découpée : pas de pavé dans l'en-tête, puis un élève sur deux
        $paves = [];
        for ($y = 23; $y < imagesy($images[0]); $y += 46) {
            $paves[] = (imagecolorat($images[0], 298, $y) & 0xFF) < 110;
        }
        expect($paves)->toBe(array_merge([false], array_map(fn (int $i) => $i % 2 === 0, range(0, 11))));

        // La consigne donne les numéros et les matricules de la classe, jamais les noms des élèves
        return $requete['model'] === 'ministral-14b-latest'
            && str_contains($consigne, "1 | 25-0101\n2 | 25-0102\n3 | 25-0103")
            && str_contains($consigne, 'la note manuscrite écrite sur la MÊME ligne')
            && ! str_contains($consigne, 'ABDELHAK')
            && ! str_contains($consigne, 'Noura');
    });

    expect(photosResultat(['eleve_id', 'nom', 'note']))->toEqual([
        ['eleve_id' => $noura->id, 'nom' => 'ABDELHAK', 'note' => 12.5],
        ['eleve_id' => $eric->id, 'nom' => 'DOSSOU', 'note' => null],
        ['eleve_id' => $paul->id, 'nom' => 'ZINSOU', 'note' => 8],
    ])
        ->and(photosFichiersTemporaires())->toBeEmpty();
});

test("une fiche qui n'est pas le bordereau est envoyée en entier, en bandes, avec la liste de la classe", function () {
    $ctx = photosContexte();

    // Photo en hauteur d'une simple liste : aucune grille de bordereau à découper
    $image = imagecreatetruecolor(1300, 1800);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    imagestring($image, 5, 100, 100, 'ABDELHAK Noura 12', imagecolorallocate($image, 20, 20, 20));
    $liste = photosDossierEnvoi().DIRECTORY_SEPARATOR.'liste.jpg';
    imagejpeg($image, $liste, 85);

    photosApi(photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)]));

    photosEnvoyer($this, $ctx, ['image_files' => [new UploadedFile($liste, 'liste.jpg', 'image/jpeg', null, true)]])->assertOk();

    Http::assertSent(function (RequeteApi $requete) {
        $images = photosImagesEnvoyees($requete);
        $consigne = photosConsigne($requete);

        // Trois bandes qui se recouvrent (6 % de la hauteur de chaque côté), à la largeur de la photo
        expect($images)->toHaveCount(3)
            ->and(array_map('imagesx', $images))->toBe([1300, 1300, 1300])
            ->and(array_map('imagesy', $images))->toBe([708, 816, 708]);

        // La colonne à lire est désignée par son intitulé et par son rang ; la classe est jointe avec les noms
        return str_contains($consigne, 'Les 3 images sont des morceaux de la MÊME fiche')
            && str_contains($consigne, 'ne lis QUE la colonne « Int. 2 », c\'est-à-dire la 2e case de notes après le nom')
            && str_contains($consigne, '1 | 25-0101 | ABDELHAK Noura')
            && str_contains($consigne, '3 | 25-0103 | ZINSOU Paul')
            && str_contains($consigne, 'Interrogation n°2');
    });
});

test("une valeur qui ne peut pas être une note est une lecture ratée : la case reste à saisir", function () {
    $ctx = photosContexte();
    [$noura, $eric, $paul] = $ctx['eleves'];
    $marie = photosEleve($ctx['classeAnnee'], '25-0100', 'AAA', 'Marie');
    $jean = photosEleve($ctx['classeAnnee'], '25-0104', 'ZZZ', 'Jean');

    photosApi(photosLecture([
        photosLigne('25-0100', '', '', 13.75),   // quart de point : une note
        photosLigne('25-0101', '', '', 96),      // hors de 0 à 20
        photosLigne('25-0102', '', '', -3),
        photosLigne('25-0103', '', '', 20),
        photosLigne('25-0104', '', '', 10.06),   // deux notes voisines lues comme une seule
    ]));

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])->assertOk();

    expect(photosResultat(['eleve_id', 'note']))->toEqual([
        ['eleve_id' => $marie->id, 'note' => 13.75],
        ['eleve_id' => $noura->id, 'note' => null],
        ['eleve_id' => $eric->id, 'note' => null],
        ['eleve_id' => $paul->id, 'note' => 20],
        ['eleve_id' => $jean->id, 'note' => null],
    ])
        ->and(session('ia_meta.lues'))->toBe(2);
});

test("un modèle refusé par le service est remplacé aussitôt par le modèle suivant", function (array $refus, array $modelesAttendus) {
    config(['services.mistral.vision_model' => 'mistral-medium-latest']);
    $modeles = [];

    Http::fake(['api.mistral.ai/*' => function (RequeteApi $requete) use (&$modeles, $refus) {
        $modeles[] = $requete['model'];

        return count($modeles) <= count($refus['reponses'])
            ? $refus['reponses'][count($modeles) - 1]()
            : photosLecture([photosLigne('25-0101', 'ABDELHAK', 'Noura', 12)]);
    }]);

    $resultat = app(ImageNoteExtractorService::class)->extractNotesFromImage(photosJpegOriente(1));

    expect($resultat['success'])->toBeTrue()
        ->and($modeles)->toBe($modelesAttendus);

    // Changer de modèle n'est pas une nouvelle tentative : aucune attente
    Sleep::assertNeverSlept();
})->with([
    'modèle hors de la formule du compte (limite de zéro demande)' => [
        ['reponses' => [fn () => Http::response(['message' => 'Rate limit exceeded'], 429, ['x-ratelimit-limit-req-minute' => '0'])]],
        ['mistral-medium-latest', 'ministral-14b-latest'],
    ],
    'nom de modèle inconnu du service, puis modèle par défaut retiré à son tour' => [
        ['reponses' => [
            fn () => Http::response(['message' => 'Invalid model: mistral-medium-latest'], 400),
            fn () => Http::response(['message' => 'Invalid model: ministral-14b-latest'], 400),
        ]],
        ['mistral-medium-latest', 'ministral-14b-latest', 'pixtral-12b-2409'],
    ],
]);

// ==================== RETOUR À LA SAISIE ====================

test('après une lecture, la saisie montre les notes lues et, pour les autres élèves, les notes déjà enregistrées', function () {
    $ctx = photosContexte();
    [$noura, $eric, $paul] = $ctx['eleves'];
    $enseignant = App\Models\Enseignant::factory()->create(['user_id' => User::factory()->create(['role' => 'enseignant', 'actif' => true])->id]);

    // Éric et Paul ont déjà une note enregistrée pour cette évaluation
    foreach ([[$eric, 9], [$paul, 14]] as [$eleve, $valeur]) {
        App\Models\Note::create([
            'eleve_id' => $eleve->id,
            'matiere_id' => $ctx['criteres']['matiere_id'],
            'periode_id' => $ctx['criteres']['periode_id'],
            'type_note_id' => $ctx['criteres']['type_note_id'],
            'numero' => 2,
            'classe_annee_id' => $ctx['criteres']['classe_annee_id'],
            'enseignant_id' => $enseignant->id,
            'valeur' => $valeur,
        ]);
    }

    // La photo donne une note à Noura, une autre note à Éric, et rien pour Paul
    photosApi(photosLecture([
        photosLigne('25-0101', '', '', 12),
        photosLigne('25-0102', '', '', 11),
        photosLigne('25-0103', '', '', null),
    ]));

    photosEnvoyer($this, $ctx, ['image_files' => [photosPhoto()]])->assertOk();

    $saisie = $this->actingAs($ctx['admin'])->get(route('admin.notes.preview'))
        ->assertOk()
        ->assertSee('Notes importées')
        ->assertSee('elles ne sont pas encore enregistrées')
        ->assertDontSee('Mode modification');

    $notes = collect($saisie->viewData('importedNotes'))->map(fn (array $note) => (float) $note['valeur']);

    // La note lue l'emporte ; une note non lue ne masque pas la note déjà enregistrée
    expect($notes->all())->toEqual([$noura->id => 12.0, $eric->id => 11.0, $paul->id => 14.0]);
});
