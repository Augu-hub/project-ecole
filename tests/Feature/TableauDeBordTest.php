<?php

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Communique;
use App\Models\ConnexionLog;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Paiement;
use App\Models\Parents;
use App\Models\Periode;
use App\Models\Reinscription;
use App\Models\Scolarite;
use App\Models\Tranche;
use App\Models\TypeNote;
use App\Models\User;
use App\Services\TableauDeBordService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ==================== JEU DE DONNÉES ====================

/**
 * Rôles qui ouvrent le tableau de bord de l'administration.
 */
function tdbRoles(): array
{
    return ['admin', 'directeur', 'censeur', 'econome', 'prefet', 'surveillant', 'saisisseur'];
}

function tdbMembre(string $role): User
{
    return User::factory()->create(['role' => $role, 'actif' => true]);
}

/**
 * Année scolaire « 2026-2027 » : du 1er septembre 2026 au 31 juillet 2027.
 */
function tdbAnnee(string $libelle, bool $active = false): AnneeScolaire
{
    [$debut, $fin] = explode('-', $libelle);

    return AnneeScolaire::factory()->create([
        'libelle' => $libelle,
        'date_debut' => $debut.'-09-01',
        'date_fin' => $fin.'-07-31',
        'est_active' => $active,
    ]);
}

function tdbClasse(AnneeScolaire $annee, string $niveau, string $suffixe): ClasseAnnee
{
    return ClasseAnnee::factory()->create([
        'classe_id' => Classe::factory()->create([
            'niveau_id' => Niveau::factory()->create(['nom' => $niveau, 'cycle' => 'secondaire'])->id,
            'suffixe' => $suffixe,
        ])->id,
        'annee_scolaire_id' => $annee->id,
    ]);
}

function tdbEleves(ClasseAnnee $classeAnnee, int $nombre, array $attributs = []): Collection
{
    return Eleve::factory()->count($nombre)->create($attributs + [
        'classe_annee_id' => $classeAnnee->id,
        'parent_id' => Parents::factory()->create()->id,
    ]);
}

/**
 * Une saisie de notes : une note par élève, pour la même classe, matière, période, type et numéro.
 * $criteres : matiere_id, periode_id, type_note_id, enseignant_id (et numero si besoin).
 */
function tdbSaisie(array $criteres, ClasseAnnee $classeAnnee, iterable $eleves): void
{
    foreach ($eleves as $eleve) {
        Note::factory()->create($criteres + [
            'eleve_id' => $eleve->id,
            'classe_annee_id' => $classeAnnee->id,
            'valeur' => 12,
        ]);
    }
}

function tdbTranche(ClasseAnnee $classeAnnee, string $libelle = '1ère tranche'): Tranche
{
    return Tranche::factory()->create([
        'scolarite_id' => Scolarite::factory()->create(['classe_annee_id' => $classeAnnee->id])->id,
        'libelle' => $libelle,
    ]);
}

function tdbPaiement(Eleve $eleve, Tranche $tranche, float $montant, string $date): Paiement
{
    return Paiement::factory()->create([
        'eleve_id' => $eleve->id,
        'parent_id' => $eleve->parent_id,
        'tranche_id' => $tranche->id,
        'payable_type' => Tranche::class,
        'payable_id' => $tranche->id,
        'montant' => $montant,
        'date_paiement' => $date,
        'mode_paiement' => 'especes',
    ]);
}

/**
 * Deux années scolaires avec, pour chacune, des élèves, des notes et des encaissements :
 * - 2026-2027 (active) : 1 classe, 4 élèves (dont 1 réinscrit venu de 2025-2026), 3 notes, 12 300 FCFA ;
 * - 2025-2026 : 2 classes, 5 élèves restés + 1 parti par réinscription, 5 notes, 70 000 FCFA.
 * S'y ajoutent un enseignant en poste, un communiqué, un événement à venir et une connexion de parent.
 */
function tdbJeuComplet(): array
{
    $active = tdbAnnee('2026-2027', active: true);
    $archivee = tdbAnnee('2025-2026');

    $sixieme = tdbClasse($active, '6ème', 'A');
    $cinquieme = tdbClasse($archivee, '5ème', 'B');
    $quatrieme = tdbClasse($archivee, '4ème', 'C');

    $elevesSixieme = tdbEleves($sixieme, 3);
    $elevesCinquieme = tdbEleves($cinquieme, 3);
    $elevesQuatrieme = tdbEleves($quatrieme, 2);

    // Élève de 5ème B réinscrit en 6ème A : la réinscription l'a déplacé dans sa nouvelle classe
    $reinscrit = tdbEleves($sixieme, 1)->first();
    Reinscription::create([
        'eleve_id' => $reinscrit->id,
        'annee_scolaire_id' => $active->id,
        'classe_annee_precedente_id' => $cinquieme->id,
        'classe_annee_id' => $sixieme->id,
        'type' => 'redoublant',
        'decide_par_user_id' => tdbMembre('admin')->id,
    ]);

    $matiere = Matiere::factory()->create(['nom_matiere' => 'Mathématiques']);
    $enseignant = Enseignant::factory()->create(['nom' => 'HOUNKPATIN', 'prenom' => 'Marie']);
    ClasseMatiere::create(['classe_annee_id' => $sixieme->id, 'matiere_id' => $matiere->id, 'coefficient' => 2, 'enseignant_id' => $enseignant->id]);

    $trimestreActif = Periode::factory()->create(['annee_scolaire_id' => $active->id, 'nom' => 'Trimestre 1', 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-15']);
    $trimestreArchive = Periode::factory()->create(['annee_scolaire_id' => $archivee->id, 'nom' => 'Trimestre 3', 'date_debut' => '2026-04-01', 'date_fin' => '2026-07-31']);

    // Type saisi en majuscules, comme en production : il doit s'afficher « Devoir »
    $devoir = TypeNote::factory()->create(['nom' => 'DEVOIR', 'code' => 'DEV', 'max_par_periode' => 1]);
    $criteres = ['matiere_id' => $matiere->id, 'type_note_id' => $devoir->id, 'enseignant_id' => $enseignant->id];

    tdbSaisie($criteres + ['periode_id' => $trimestreArchive->id], $cinquieme, $elevesCinquieme);
    tdbSaisie($criteres + ['periode_id' => $trimestreArchive->id], $quatrieme, $elevesQuatrieme);
    tdbSaisie($criteres + ['periode_id' => $trimestreActif->id], $sixieme, $elevesSixieme);

    $trancheActive = tdbTranche($sixieme);
    $trancheArchivee = tdbTranche($cinquieme);
    tdbPaiement($elevesCinquieme[0], $trancheArchivee, 70000, '2026-03-15');
    tdbPaiement($elevesSixieme[0], $trancheActive, 10000, '2026-09-10');
    tdbPaiement($elevesSixieme[1], $trancheActive, 2300, '2026-11-03');

    $communique = Communique::create(['titre' => 'Reunion de rentree', 'contenu' => 'Rendez-vous samedi matin.', 'type' => 'general', 'date_publication' => today(), 'actif' => true]);
    $communique->classesAnnees()->attach($sixieme->id);
    Communique::create(['titre' => 'Fete de fin de trimestre', 'contenu' => 'Spectacle des eleves.', 'type' => 'evenement', 'date_publication' => today()->addDays(10), 'actif' => true]);

    $parentConnecte = Parents::factory()->create(['nom' => 'DOSSOU', 'prenom' => 'Marie']);
    ConnexionLog::create(['user_id' => $parentConnecte->user_id, 'parent_id' => $parentConnecte->id, 'ip_address' => '10.0.0.5', 'user_agent' => 'Test']);

    return compact(
        'active', 'archivee', 'sixieme', 'cinquieme', 'quatrieme', 'elevesSixieme', 'elevesCinquieme',
        'matiere', 'enseignant', 'trimestreActif', 'trimestreArchive', 'devoir', 'criteres', 'trancheActive', 'trancheArchivee'
    );
}

/**
 * Liens du corps de la page (hors menu latéral et barre du haut).
 */
function tdbLiensDeLaPage(string $html): array
{
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $liens = [];
    foreach ((new DOMXPath($dom))->query('//div[contains(@class, "dashboard-main-body")]//a/@href') as $href) {
        $liens[] = $href->value;
    }

    return array_values(array_unique($liens));
}

/**
 * Remplace l'action de chaque route par une réponse vide : une requête ne traverse plus que les
 * vrais middlewares (auth, role:...), sans dépendre du contenu des autres contrôleurs.
 */
function tdbNeutraliserControleurs(): void
{
    foreach (Route::getRoutes() as $route) {
        $route->setAction(['uses' => fn () => 'ok'] + Arr::except($route->getAction(), 'controller'));
    }
}

/**
 * Requêtes SQL exécutées pour afficher le tableau de bord à cet utilisateur.
 */
function tdbRequetesDuTableauDeBord(User $membre): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($membre)->get(route('admin.dashboard'))->assertOk();
    DB::disableQueryLog();

    return array_column(DB::getQueryLog(), 'query');
}

// ==================== ACCÈS ====================

test('le tableau de bord s affiche pour chaque role autorise', function (string $role) {
    tdbJeuComplet();

    $this->actingAs(tdbMembre($role))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('<title>Tableau de bord — ', false)
        ->assertSee('Année scolaire 2026-2027')
        ->assertSee('Chiffres clés')
        ->assertSee('Historique par année scolaire')
        ->assertSee('Reunion de rentree')
        ->assertSee('HOUNKPATIN');
})->with(tdbRoles());

test('le tableau de bord de l administration est refuse aux autres roles', function (string $role) {
    $this->actingAs(tdbMembre($role))->get(route('admin.dashboard'))->assertForbidden();
})->with(['enseignant', 'parent']);

// ==================== HISTORIQUES ====================

test('l historique par annee scolaire donne les eleves, classes, notes et encaissements de chaque annee', function () {
    tdbJeuComplet();

    $reponse = $this->actingAs(tdbMembre('admin'))->get(route('admin.dashboard'))->assertOk();
    $historique = $reponse->viewData('historique');

    // De la plus récente à la plus ancienne, l'année active repérée
    expect($historique->pluck('libelle')->all())->toBe(['2026-2027', '2025-2026'])
        ->and($historique->pluck('active')->all())->toBe([true, false])
        ->and($reponse->viewData('anneesPlusAnciennes'))->toBeFalse();

    expect(Arr::only($historique[0], ['eleves', 'elevesPartis', 'classes', 'notes', 'encaissements']))
        ->toBe(['eleves' => 4, 'elevesPartis' => 0, 'classes' => 1, 'notes' => 3, 'encaissements' => 12300.0]);

    // L'élève réinscrit en 2026-2027 compte toujours dans l'effectif de l'année qu'il a quittée
    expect(Arr::only($historique[1], ['eleves', 'elevesPartis', 'classes', 'notes', 'encaissements']))
        ->toBe(['eleves' => 6, 'elevesPartis' => 1, 'classes' => 2, 'notes' => 5, 'encaissements' => 70000.0]);

    // Les chiffres clés de l'année active disent la même chose que sa ligne dans l'historique
    expect($reponse->viewData('totalEleves'))->toBe(4)
        ->and($reponse->viewData('totalClasses'))->toBe(1)
        ->and($reponse->viewData('anneeCourante')['notes'])->toBe(3)
        ->and($reponse->viewData('paiementsAnnee'))->toBe(12300.0)
        ->and($reponse->viewData('totalPaiements'))->toBe(82300.0);

    $reponse->assertSeeInOrder(['Historique par année scolaire', '2026-2027', 'Année active', '2025-2026'])
        ->assertSee('12 300 FCFA')
        ->assertSee('70 000 FCFA')
        ->assertSee('82 300 FCFA')
        ->assertSee('dont 1 réinscrit ensuite dans une autre année');
});

test('l historique n affiche que les annees les plus recentes et garde toujours l annee active', function () {
    // L'année active est la plus ancienne des huit
    tdbAnnee('2015-2016', active: true);
    foreach (range(2016, 2022) as $debut) {
        tdbAnnee($debut.'-'.($debut + 1));
    }

    $reponse = $this->actingAs(tdbMembre('admin'))->get(route('admin.dashboard'))->assertOk();

    expect($reponse->viewData('historique')->pluck('libelle')->all())
        ->toBe(['2022-2023', '2021-2022', '2020-2021', '2019-2020', '2018-2019', '2015-2016'])
        ->and($reponse->viewData('historique')->last()['active'])->toBeTrue()
        ->and($reponse->viewData('anneesPlusAnciennes'))->toBeTrue();

    $reponse->assertSee('années les plus récentes sont affichées');
});

test('les encaissements de l annee active sont repartis mois par mois', function () {
    $ctx = tdbJeuComplet();

    $reponse = $this->actingAs(tdbMembre('econome'))->get(route('admin.dashboard'))->assertOk();
    $encaissements = $reponse->viewData('encaissementsParMois');

    // Onze mois, de la rentrée à la fin de l'année ; le paiement de l'année archivée n'y figure pas
    expect($encaissements['mois']->pluck('cle')->all())->toBe([
        '2026-09', '2026-10', '2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05', '2027-06', '2027-07',
    ])
        ->and($encaissements['mois']->pluck('total', 'cle')->filter()->all())->toBe(['2026-09' => 10000.0, '2026-11' => 2300.0])
        ->and($encaissements['mois']->firstWhere('cle', '2026-09')['nombre'])->toBe(1)
        ->and($encaissements['total'])->toBe(12300.0)
        ->and($encaissements['nombre'])->toBe(2)
        ->and($encaissements['horsPeriode'])->toBe(0.0);

    $reponse->assertSeeInOrder(['Encaissements par mois', 'Septembre 2026', '10 000 FCFA', 'Novembre 2026', '2 300 FCFA', 'Total 2026-2027', '12 300 FCFA']);

    // Un deuxième paiement le même mois s'ajoute ; un paiement fait avant la rentrée allonge la période
    tdbPaiement($ctx['elevesSixieme'][2], $ctx['trancheActive'], 5000, '2026-09-25');
    tdbPaiement($ctx['elevesSixieme'][2], $ctx['trancheActive'], 1000, '2026-07-20');

    $encaissements = app(TableauDeBordService::class)->encaissementsParMois($ctx['active']);

    expect($encaissements['mois']->first())->toMatchArray(['cle' => '2026-07', 'libelle' => 'juillet 2026', 'total' => 1000.0, 'nombre' => 1])
        ->and($encaissements['mois'])->toHaveCount(13)
        ->and($encaissements['mois']->firstWhere('cle', '2026-08')['total'])->toBe(0.0)
        ->and($encaissements['mois']->firstWhere('cle', '2026-09'))->toMatchArray(['total' => 15000.0, 'nombre' => 2])
        ->and($encaissements['total'])->toBe(18300.0);
});

// ==================== ACTIVITÉ RÉCENTE ====================

test('l activite recente est limitee aux dernieres lignes, de la plus recente a la plus ancienne', function () {
    $ctx = tdbJeuComplet();
    $limite = TableauDeBordService::LIGNES_ACTIVITE;

    // Sept nouveaux élèves, sept paiements, sept saisies de notes et sept connexions, du plus ancien au plus récent
    $nouveaux = collect(range(1, 7))->map(fn (int $i) => tdbEleves($ctx['sixieme'], 1, [
        'nom' => 'NOUVEAU'.$i,
        'created_at' => now()->addMinutes($i),
    ])->first());

    foreach ($nouveaux as $i => $eleve) {
        tdbPaiement($eleve, $ctx['trancheActive'], 1000 * ($i + 1), '2027-01-0'.($i + 1));

        tdbSaisie(
            ['matiere_id' => Matiere::factory()->create(['nom_matiere' => 'Matiere'.($i + 1)])->id, 'periode_id' => $ctx['trimestreActif']->id] + $ctx['criteres'],
            $ctx['sixieme'],
            $ctx['elevesSixieme']->take(2),
        );

        $parent = Parents::factory()->create(['nom' => 'PARENT'.($i + 1)]);
        ConnexionLog::create(['user_id' => $parent->user_id, 'parent_id' => $parent->id]);
    }

    $reponse = $this->actingAs(tdbMembre('admin'))->get(route('admin.dashboard'))->assertOk();

    expect($reponse->viewData('derniersPaiements')->pluck('montant')->all())->toBe([7000.0, 6000.0, 5000.0, 4000.0, 3000.0])
        ->and($reponse->viewData('dernieresInscriptions')->pluck('nom')->all())->toBe(['NOUVEAU7', 'NOUVEAU6', 'NOUVEAU5', 'NOUVEAU4', 'NOUVEAU3'])
        ->and($reponse->viewData('dernieresSaisies')->pluck('matiere')->all())->toBe(['Matiere7', 'Matiere6', 'Matiere5', 'Matiere4', 'Matiere3'])
        ->and($reponse->viewData('dernieresConnexions')->pluck('parent.nom')->all())->toBe(['PARENT7', 'PARENT6', 'PARENT5', 'PARENT4', 'PARENT3']);

    foreach (['derniersPaiements', 'dernieresInscriptions', 'dernieresSaisies', 'dernieresConnexions', 'communiques', 'evenements', 'enseignantsEnPoste', 'meilleuresMoyennes'] as $liste) {
        expect($reponse->viewData($liste)->count())->toBeLessThanOrEqual($limite);
    }

    // Chaque saisie est décrite : classe, matière, type (affiché « Devoir », pas « DEVOIR »), période, nombre de notes
    expect($reponse->viewData('dernieresSaisies')->first())->toMatchArray([
        'classe' => '6ème A', 'matiere' => 'Matiere7', 'type' => 'Devoir', 'periode' => 'Trimestre 1', 'annee' => '2026-2027', 'nombre' => 2,
    ]);
    expect($reponse->viewData('derniersPaiements')->first())->toMatchArray(['objet' => 'Scolarité · 1ère tranche', 'mode' => 'Espèces']);

    $reponse->assertSeeInOrder(['Derniers paiements', '7 000 FCFA', '3 000 FCFA'])
        ->assertSeeInOrder(['Dernières inscriptions', 'NOUVEAU7', 'NOUVEAU3'])
        ->assertSeeInOrder(['Dernières saisies de notes', 'Matiere7', 'Devoir', 'Trimestre 1', '2 notes', 'Matiere3'])
        ->assertSeeInOrder(['Dernières connexions des parents', 'PARENT7', 'PARENT3'])
        ->assertDontSee('2 000 FCFA')
        ->assertDontSee('NOUVEAU2')
        ->assertDontSee('Matiere2')
        ->assertDontSee('PARENT2')
        ->assertDontSee('DEVOIR');
});

test('une saisie de notes completee plus tard affiche son effectif total', function () {
    $ctx = tdbJeuComplet();
    $eleves = tdbEleves($ctx['sixieme'], 5);
    $interrogation = TypeNote::factory()->create(['nom' => 'Interrogation', 'code' => 'INT', 'max_par_periode' => 3]);

    $francais = ['matiere_id' => Matiere::factory()->create(['nom_matiere' => 'Français'])->id, 'type_note_id' => $interrogation->id, 'numero' => 2];
    $anglais = ['matiere_id' => Matiere::factory()->create(['nom_matiere' => 'Anglais'])->id];
    $commun = ['periode_id' => $ctx['trimestreActif']->id] + $ctx['criteres'];

    tdbSaisie($francais + $commun, $ctx['sixieme'], $eleves->take(4));
    tdbSaisie($anglais + $commun, $ctx['sixieme'], $eleves->take(2));
    // La cinquième note de français arrive après la saisie d'anglais
    tdbSaisie($francais + $commun, $ctx['sixieme'], $eleves->skip(4));

    // Seules les trois dernières notes créées sont examinées pour repérer les saisies récentes
    $saisies = (new TableauDeBordService(fenetreNotes: 3))->dernieresSaisiesDeNotes();

    expect($saisies->map(fn (array $saisie) => Arr::only($saisie, ['matiere', 'type', 'nombre']))->all())->toBe([
        ['matiere' => 'Français', 'type' => 'Interrogation n°2', 'nombre' => 5],
        ['matiere' => 'Anglais', 'type' => 'Devoir', 'nombre' => 2],
    ]);
});

// ==================== DROITS PAR RÔLE ====================

test('les blocs finances, connexions, inscriptions et saisies ne s affichent qu aux roles qui ont la page', function (string $role, array $blocsVisibles) {
    tdbJeuComplet();

    $reponse = $this->actingAs(tdbMembre($role))->get(route('admin.dashboard'))->assertOk();

    $titres = [
        'finances' => ['Encaissements de l\'année', 'Encaissements par mois', 'Derniers paiements'],
        'connexions' => ['Dernières connexions des parents'],
        'inscriptions' => ['Dernières inscriptions'],
        'saisies' => ['Dernières saisies de notes'],
    ];

    foreach ($titres as $bloc => $titresDuBloc) {
        foreach ($titresDuBloc as $titre) {
            in_array($bloc, $blocsVisibles) ? $reponse->assertSee($titre, false) : $reponse->assertDontSee($titre, false);
        }
    }

    if (! in_array('finances', $blocsVisibles)) {
        // Aucun montant, ni dans la page ni dans les données préparées pour elle
        $reponse->assertDontSee('FCFA')->assertDontSee('Encaissements');

        expect($reponse->viewData('paiementsAnnee'))->toBeNull()
            ->and($reponse->viewData('totalPaiements'))->toBeNull()
            ->and($reponse->viewData('encaissementsParMois'))->toBeNull()
            ->and($reponse->viewData('derniersPaiements'))->toBeNull()
            ->and($reponse->viewData('historique')->pluck('encaissements')->filter(fn ($montant) => $montant !== null)->all())->toBe([]);
    }

    if (! in_array('connexions', $blocsVisibles)) {
        $reponse->assertDontSee('DOSSOU');

        expect($reponse->viewData('dernieresConnexions'))->toBeNull();
    } else {
        $reponse->assertSee('Marie DOSSOU');
    }
})->with([
    'admin : tout' => ['admin', ['finances', 'connexions', 'inscriptions', 'saisies']],
    'directeur : tout' => ['directeur', ['finances', 'connexions', 'inscriptions', 'saisies']],
    'censeur : tout sauf le journal des connexions' => ['censeur', ['finances', 'inscriptions', 'saisies']],
    'econome : tout sauf le journal des connexions' => ['econome', ['finances', 'inscriptions', 'saisies']],
    'saisisseur : les saisies de notes seulement' => ['saisisseur', ['saisies']],
    'prefet : aucun de ces blocs' => ['prefet', []],
    'surveillant : aucun de ces blocs' => ['surveillant', []],
]);

test('sans acces aux finances ni au journal, ni les paiements ni les connexions ne sont lus en base', function () {
    tdbJeuComplet();

    $requetes = implode("\n", tdbRequetesDuTableauDeBord(tdbMembre('saisisseur')));

    expect($requetes)->not->toContain('paiements')
        ->and($requetes)->not->toContain('connexion_logs')
        ->and($requetes)->toContain('notes');
});

test('chaque lien du tableau de bord mene a une page que le role a le droit d ouvrir', function (string $role) {
    tdbJeuComplet();
    $membre = tdbMembre($role);

    $liens = tdbLiensDeLaPage($this->actingAs($membre)->get(route('admin.dashboard'))->assertOk()->getContent());

    // 200 : la requête a franchi les middlewares ; 403 : rôle refusé ; 404 : lien mort
    tdbNeutraliserControleurs();
    $refuses = array_filter($liens, fn (string $lien) => $this->actingAs($membre)->get($lien)->status() !== 200);

    expect($liens)->not->toBeEmpty()
        ->and(array_values($refuses))->toBe([]);
})->with(tdbRoles());

test('les liens vers les communiques ne sont proposes qu aux roles qui y ont acces', function () {
    tdbJeuComplet();
    $communique = Communique::where('titre', 'Reunion de rentree')->firstOrFail();

    $liensDu = fn (string $role) => tdbLiensDeLaPage($this->actingAs(tdbMembre($role))->get(route('admin.dashboard'))->assertOk()->getContent());

    // Le saisisseur lit les communiqués sur le tableau de bord, sans lien vers des pages qui lui sont fermées
    expect($liensDu('saisisseur'))->toBe([route('admin.notes.create'), route('admin.notes.index')]);

    expect($liensDu('surveillant'))
        ->toContain(route('admin.communiques.index'))
        ->toContain(route('admin.communiques.show', $communique));
});

// ==================== ÉTATS VIDES ====================

test('sans annee active la page reste lisible : chiffres a zero, historique sans annee active', function () {
    $ctx = tdbJeuComplet();
    $ctx['active']->update(['est_active' => false]);

    $reponse = $this->actingAs(tdbMembre('admin'))->get(route('admin.dashboard'))->assertOk();

    $reponse->assertSee('Aucune année scolaire n\'est active', false)
        ->assertSee('Gérer les années scolaires')
        ->assertSee('Aucune année scolaire active')
        ->assertSee('Aucune inscription cette année')
        ->assertDontSee('Année active')
        ->assertDontSee('Année scolaire 2026-2027')
        ->assertViewHas('totalEleves', 0)
        ->assertViewHas('totalClasses', 0)
        ->assertViewHas('paiementsAnnee', 0.0)
        ->assertViewHas('anneeCourante', null);

    // L'historique reste consultable, sans année mise en avant
    expect($reponse->viewData('historique')->pluck('libelle')->all())->toBe(['2026-2027', '2025-2026'])
        ->and($reponse->viewData('historique')->pluck('active')->all())->toBe([false, false])
        ->and($reponse->viewData('historique')->pluck('eleves')->all())->toBe([4, 6]);
});

test('une annee active encore vide affiche des messages clairs plutot que des blocs vides', function () {
    tdbAnnee('2026-2027', active: true);

    $reponse = $this->actingAs(tdbMembre('admin'))->get(route('admin.dashboard'))->assertOk();

    foreach ([
        'Aucune classe ouverte cette année',
        'Aucun élève inscrit cette année pour l\'instant.',
        'Aucune note saisie cette année pour l\'instant.',
        'Aucun encaissement cette année pour l\'instant.',
        'Aucun encaissement pour 2026-2027',
        'Aucun paiement enregistré',
        'Aucune inscription cette année',
        'Aucune note saisie',
        'Aucune connexion enregistrée',
        'Aucun communiqué cette année',
        'Aucune note saisie cette année',
        'Aucun enseignant en poste cette année',
    ] as $message) {
        $reponse->assertSee($message, false);
    }

    expect(Arr::only($reponse->viewData('historique')->sole(), ['libelle', 'active', 'eleves', 'classes', 'notes', 'encaissements']))
        ->toBe(['libelle' => '2026-2027', 'active' => true, 'eleves' => 0, 'classes' => 0, 'notes' => 0, 'encaissements' => 0.0]);
});

test('sans aucune annee scolaire la page s affiche quand meme', function (string $role) {
    $this->actingAs(tdbMembre($role))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Aucune année scolaire n\'est active', false)
        ->assertSee('Aucune année scolaire enregistrée');
})->with(['admin', 'saisisseur']);

// ==================== PLUS DE BLOCS FACTICES ====================

test('aucun bloc factice du gabarit ne reste sur le tableau de bord', function () {
    tdbJeuComplet();

    $reponse = $this->actingAs(tdbMembre('admin'))->get(route('admin.dashboard'))->assertOk();

    foreach ([
        'Assiduité', 'Demi-journée', 'Retard', '87%', 'Demandes de congé', 'Calendrier', 'Dépenses', 'Revenus',
        'Aperçu des utilisateurs', 'Meilleurs enseignants', 'School', 'présences', 'trésorerie',
        'revenueStatistic', 'incomeExpense', 'newAdmissions', 'currentMonthYear',
        'email@exemple.com', 'N/A', 'notice-board-img', 'top-teacher-img', 'radial-bg', 'dashboard-icon',
    ] as $vestige) {
        $reponse->assertDontSee($vestige, false);
    }

    foreach (['presentPourcent', 'absentPourcent', 'retardPourcent', 'demiJourneePourcent', 'depenses'] as $variable) {
        $reponse->assertViewMissing($variable);
    }
});

// ==================== REQUÊTES BORNÉES ====================

test('le nombre de requetes ne depend pas du volume de donnees', function () {
    $ctx = tdbJeuComplet();
    $admin = tdbMembre('admin');

    $avant = tdbRequetesDuTableauDeBord($admin);

    // Davantage d'années, de classes, d'élèves, de notes, de paiements, de communiqués et de connexions
    $ancienne = tdbAnnee('2024-2025');
    $troisieme = tdbClasse($ancienne, '3ème', 'D');
    $periode = Periode::factory()->create(['annee_scolaire_id' => $ancienne->id, 'nom' => 'Trimestre 2']);
    tdbSaisie(['periode_id' => $periode->id] + $ctx['criteres'], $troisieme, tdbEleves($troisieme, 12));

    $nouveaux = tdbEleves($ctx['sixieme'], 12);
    tdbSaisie(['periode_id' => $ctx['trimestreActif']->id] + $ctx['criteres'], $ctx['sixieme'], $nouveaux);

    foreach ($nouveaux as $i => $eleve) {
        tdbPaiement($eleve, $ctx['trancheActive'], 1000, '2026-10-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT));
        Communique::create(['titre' => 'Communique '.$i, 'contenu' => '...', 'type' => 'evenement', 'date_publication' => today()->addDays($i + 1), 'actif' => true]);
        ConnexionLog::create(['user_id' => $eleve->parent->user_id, 'parent_id' => $eleve->parent_id]);
    }

    $apres = tdbRequetesDuTableauDeBord($admin);

    expect(count($apres))->toBe(count($avant))
        ->and(count($apres))->toBeLessThanOrEqual(40);
});
