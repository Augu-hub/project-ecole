<?php

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseAnnee;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\TypeNote;
use App\Models\User;
use App\Services\NotesPdfService;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

function contexteFiche(): array
{
    $classe = Classe::factory()->create(['niveau_id' => Niveau::factory()->create(['nom' => 'Sixième'])->id, 'suffixe' => 'A']);
    $classeAnnee = ClasseAnnee::factory()->create(['classe_id' => $classe->id, 'annee_scolaire_id' => AnneeScolaire::factory()->create()->id]);
    $matiere = Matiere::factory()->create(['nom_matiere' => 'Mathématiques']);
    $periode = Periode::factory()->create(['annee_scolaire_id' => $classeAnnee->annee_scolaire_id]);
    $typeNote = TypeNote::factory()->create(['nom' => 'Interrogation', 'max_par_periode' => 3]);

    $enseignant = Enseignant::factory()->create([
        'user_id' => User::factory()->create(['role' => 'enseignant', 'actif' => true])->id,
        'nom' => 'HOUNKPATIN',
        'prenom' => 'Marie',
    ]);
    ClasseMatiere::create([
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'coefficient' => 2,
        'enseignant_id' => $enseignant->id,
    ]);

    $eleves = Eleve::factory()->count(2)->create(['classe_annee_id' => $classeAnnee->id]);

    $criteres = [
        'classe_annee_id' => $classeAnnee->id,
        'matiere_id' => $matiere->id,
        'periode_id' => $periode->id,
        'type_note_id' => $typeNote->id,
        'numero' => 2,
    ];

    return compact('classeAnnee', 'matiere', 'periode', 'typeNote', 'enseignant', 'eleves', 'criteres');
}

test("l'enseignant assigné enregistre ses notes puis génère la fiche avec son nom, le n° d'évaluation et 2 décimales", function () {
    $ctx = contexteFiche();
    $eleve = $ctx['eleves']->first();

    $this->actingAs($ctx['enseignant']->user)
        ->post(route('admin.notes.store'), $ctx['criteres'] + [
            'notes' => [$eleve->id => ['valeur' => '12.25', 'commentaire' => 'Bien']],
        ])
        ->assertRedirect(route('admin.notes.preview'));

    expect(Note::where('eleve_id', $eleve->id)->value('valeur'))->toBe('12.25');

    $pdf = tempnam(sys_get_temp_dir(), 'fiche');
    $this->mock(NotesPdfService::class)
        ->shouldReceive('generate')->once()
        ->withArgs(fn (array $data) => $data['enseignant'] === 'Marie HOUNKPATIN'
            && $data['type_note'] === 'Interrogation n°2'
            && collect($data['notes'])->firstWhere('matricule', $eleve->matricule)['note'] === '12.25')
        ->andReturn($pdf);

    // Après l'enregistrement, les critères restent en session : l'export fonctionne directement
    $this->actingAs($ctx['enseignant']->user)
        ->get(route('admin.notes.export-pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test("l'administration enregistre des notes sans enseignant attribué à la matière : elles vont à l'enseignant par défaut", function () {
    $ctx = contexteFiche();
    $eleve = $ctx['eleves']->first();
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    // Comme après la reprise des matières de l'année précédente : la matière n'a pas d'enseignant dans la classe
    ClasseMatiere::query()->toBase()->update(['enseignant_id' => null]);

    $this->actingAs($admin)
        ->post(route('admin.notes.store'), $ctx['criteres'] + ['notes' => [$eleve->id => ['valeur' => '14', 'commentaire' => '']]])
        ->assertRedirect(route('admin.notes.preview'))
        ->assertSessionMissing('error');

    expect(Note::where('eleve_id', $eleve->id)->value('enseignant_id'))->toBe($ctx['enseignant']->id);

    // Sans aucun enseignant dans l'établissement, rien n'est enregistré et le message l'explique
    Note::query()->delete();
    Enseignant::query()->delete();

    $this->actingAs($admin)
        ->post(route('admin.notes.store'), $ctx['criteres'] + ['notes' => [$eleve->id => ['valeur' => '14', 'commentaire' => '']]])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, "aucun enseignant n'est encore enregistré"));

    expect(Note::count())->toBe(0);
});

test("un enseignant ne peut pas générer la fiche d'une classe qui ne lui est pas assignée", function () {
    $ctx = contexteFiche();
    $autreEnseignant = Enseignant::factory()->create([
        'user_id' => User::factory()->create(['role' => 'enseignant', 'actif' => true])->id,
    ]);

    $this->actingAs($autreEnseignant->user)
        ->withSession(['note_criteres' => $ctx['criteres']])
        ->get(route('admin.notes.export-pdf'))
        ->assertForbidden();
});

test("le template Excel réimporté tel quel ne signale aucune ligne en erreur", function () {
    $ctx = contexteFiche();
    $admin = User::factory()->create(['role' => 'admin', 'actif' => true]);

    $contenu = $this->actingAs($admin)
        ->withSession(['note_criteres' => $ctx['criteres']])
        ->get(route('admin.notes.export-template-excel'))
        ->assertOk()
        ->streamedContent();

    $fichier = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($fichier, $contenu);

    $response = $this->actingAs($admin)
        ->withSession(['note_criteres' => $ctx['criteres']])
        ->post(route('admin.notes.import-preview'), [
            'import_file' => new UploadedFile($fichier, 'template.xlsx', null, null, true),
        ])
        ->assertOk()
        ->assertViewHas('hasErrors', false);

    expect($response->viewData('importedData'))->toHaveCount(2);
});

test("une sélection de fiche expirée redirige vers le choix des critères au lieu d'une erreur", function () {
    $ctx = contexteFiche();
    $criteres = ['classe_annee_id' => 999999] + $ctx['criteres'];

    $this->actingAs(User::factory()->create(['role' => 'admin', 'actif' => true]))
        ->withSession(['note_criteres' => $criteres])
        ->get(route('admin.notes.export-template'))
        ->assertRedirect(route('admin.notes.create'))
        ->assertSessionHas('error');
});

test('la fiche vierge PDF est le bordereau de saisie des notes, au format A4 portrait', function () {
    $ctx = contexteFiche();

    $reponse = $this->actingAs(User::factory()->create(['role' => 'admin', 'actif' => true]))
        ->withSession(['note_criteres' => $ctx['criteres']])
        ->get(route('admin.notes.export-template'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $fichier = $reponse->baseResponse->getFile()->getPathname();
    $pdf = file_get_contents($fichier);
    unlink($fichier);

    // A4 portrait : 595,28 × 841,89 points
    expect($pdf)->toStartWith('%PDF')
        ->toContain('/MediaBox [0.000 0.000 595.280 841.890]');
});

test('le bordereau reprend l\'en-tête et les colonnes du bordereau papier, avec une ligne par élève', function () {
    $bordereau = fn (array $data) => (fn () => $this->buildHtmlVierge($data, ''))->call(app(NotesPdfService::class));
    $texte = fn (string $html) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(
        preg_replace(['#<style>.*?</style>#s', '/<[^>]+>/'], ['', ' '], $html)
    )));

    $html = $bordereau([
        'classe' => '2nde C',
        'annee_scolaire' => '2026-2027',
        'matiere' => 'ANGLAIS',
        'periode' => 'Trimestre 1',
        'enseignant' => 'Marie HOUNKPATIN',
        'notes' => [
            ['num' => 1, 'matricule' => '25-0107', 'nom' => 'Abdelhak', 'prenom' => 'Noura'],
            ['num' => 2, 'matricule' => '20-0482', 'nom' => 'Adjanohoun', 'prenom' => 'Ghémahou Lenhic'],
        ],
    ]);

    expect($texte($html))
        ->toContain('COLLEGE CATHOLIQUE PERE AUPIAIS 04 BP 962 TEL : 21300529 COTONOU BENIN')
        ->toContain('Edition du :')
        ->toContain('Année Scolaire 2026-2027 Classe 2nde C Effectif 2')
        ->toContain('BORDEREAU DE SAISIE DES NOTES')
        ->toContain('Trimestre 1 Matière ANGLAIS Professeur Marie HOUNKPATIN')
        ->toContain('N° Matricule Nom & Prénom(s) Int. 1 Int. 2 Int. 3 Int. 4 Int. 5 Moy. Int. Devoirs Moy. Dv. Compo Moy. Excel Moy. Coef.')
        ->toContain('1 25-0107 ABDELHAK Noura')
        ->toContain('2 20-0482 ADJANOHOUN Ghémahou Lenhic');

    // 12 cases vides à remplir par élève : 5 interrogations, 2 devoirs, la composition et 4 moyennes
    expect(preg_match_all('#<td class="c-(int|moy|dev|compo|large)"></td>#', $html))->toBe(2 * 12);

    // Nom et prénoms sur une seule ligne : un prénom trop long pour sa colonne est écrit plus petit,
    // et ne passe sur deux lignes que s'il faudrait descendre sous 5,5 pt
    $html = $bordereau(['notes' => [
        ['num' => 1, 'matricule' => '25-0107', 'nom' => 'Abdelhak', 'prenom' => 'Noura'],
        ['num' => 2, 'matricule' => '16-0680', 'nom' => 'Gbaguidi', 'prenom' => 'Dorian Jean-Marie-Vianney Vignon Mahoussi'],
        ['num' => 3, 'matricule' => '16-0681', 'nom' => 'Quenum', 'prenom' => 'Sèdjro Emmanuelle Bénédicte Fifamè Olouwakémi Adétutu Marie-Josée'],
    ]]);

    expect($html)->toContain('<td class="c-pre">Noura</td>')
        ->toMatch('#<td class="c-pre" style="font-size:\d\.\dpt">Dorian Jean-Marie-Vianney Vignon Mahoussi</td>#')
        ->toContain('<td class="c-pre" style="font-size:5.5pt;white-space:normal">Sèdjro Emmanuelle');

    // Sans enseignant assigné, le professeur reste à compléter à la main, comme sur le papier
    expect($texte($bordereau(['periode' => 'Trimestre 1', 'matiere' => 'ANGLAIS', 'notes' => []])))
        ->toContain('Professeur ........');
});

test('la fiche de notes PDF reprend l\'en-tête du bordereau, les notes sur 20 et le bilan de la classe', function () {
    $fiche = fn (array $data) => (fn () => $this->buildHtml($data, $this->calcStats($data['notes']), ''))->call(app(NotesPdfService::class));
    $texte = fn (string $html) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(
        preg_replace(['#<style>.*?</style>#s', '/<[^>]+>/'], ['', ' '], $html)
    )));

    $html = $fiche([
        'classe' => '2nde C',
        'annee_scolaire' => '2026-2027',
        'matiere' => 'ANGLAIS',
        'periode' => 'Trimestre 1',
        'type_note' => 'Interrogation n°2',
        'enseignant' => 'Marie HOUNKPATIN',
        'notes' => [
            ['num' => 1, 'matricule' => '25-0107', 'nom' => 'Abdelhak', 'prenom' => 'Noura', 'note' => '12.25', 'commentaire' => ''],
            ['num' => 2, 'matricule' => '20-0482', 'nom' => 'Adjanohoun', 'prenom' => 'Ghémahou Lenhic', 'note' => '8.50', 'commentaire' => 'Copie rendue en retard'],
            ['num' => 3, 'matricule' => '22-0003', 'nom' => 'Agassoussi', 'prenom' => 'Heloy', 'note' => null, 'commentaire' => ''],
            ['num' => 4, 'matricule' => '16-0422', 'nom' => 'Agbo', 'prenom' => 'Sélomé', 'note' => '20.00', 'commentaire' => ''],
        ],
    ]);

    expect($texte($html))
        ->toContain('COLLEGE CATHOLIQUE PERE AUPIAIS 04 BP 962 TEL : 21300529 COTONOU BENIN')
        ->toContain('Année Scolaire 2026-2027 Classe 2nde C Effectif 4')
        ->toContain('FICHE DE NOTES')
        ->toContain('Période Trimestre 1 Matière ANGLAIS Évaluation Interrogation n°2 Professeur Marie HOUNKPATIN')
        ->toContain('N° Matricule Nom & Prénom(s) Note / 20 Appréciation Observation')
        // Notes écrites comme sur un document : deux chiffres, une virgule, deux décimales
        ->toContain('1 25-0107 ABDELHAK Noura 12,25 Assez bien')
        ->toContain('2 20-0482 ADJANOHOUN Ghémahou Lenhic 08,50 Insuffisant Copie rendue en retard')
        ->toContain('3 22-0003 AGASSOUSSI Heloy — Non noté')
        ->toContain('4 16-0422 AGBO Sélomé 20,00 Excellent')
        // Le bilan ne compte que les élèves notés
        ->toContain('Élèves notés 3 / 4 Moyenne de la classe 13,58 Plus forte note 20,00 Plus faible note 08,50 Notes de 10 et plus 2 (67 %)')
        ->toContain('Le Professeur Marie HOUNKPATIN Le Censeur')
        // L'ancienne fiche en couleurs portait des mentions qui ne figurent sur aucun document du collège
        ->not->toContain('République du Bénin')
        ->not->toContain('Excellence');

    // Document en noir et blanc, fait pour être imprimé et photocopié
    expect($html)->not->toMatch('/#003366|#C8A951|color:\s*#(?!000|444)/i');

    // Sans aucune note enregistrée, le bilan reste lisible
    expect($texte($fiche(['notes' => [['num' => 1, 'matricule' => '25-0107', 'nom' => 'Abdelhak', 'prenom' => 'Noura', 'note' => null]]])))
        ->toContain('1 25-0107 ABDELHAK Noura — Non noté')
        ->toContain('Élèves notés 0 / 1 Moyenne de la classe — Plus forte note — Plus faible note — Notes de 10 et plus —');
});

test('la fiche de notes PDF tient sur une page A4 portrait pour une classe ordinaire, sur deux au-delà', function () {
    $eleves = fn (int $nombre) => array_map(fn (int $i) => [
        'num' => $i,
        'matricule' => sprintf('26-%04d', $i),
        'nom' => 'Nom'.$i,
        'prenom' => 'Prénom Exemple',
        'note' => number_format(($i * 7) % 21, 2, '.', ''),
        'commentaire' => '',
    ], range(1, $nombre));

    $pages = function (int $nombre) use ($eleves) {
        $fichier = app(NotesPdfService::class)->generate([
            'classe' => '2nde C',
            'annee_scolaire' => '2026-2027',
            'matiere' => 'ANGLAIS',
            'periode' => 'Trimestre 1',
            'type_note' => 'Interrogation n°2',
            'enseignant' => 'Marie HOUNKPATIN',
            'notes' => $eleves($nombre),
        ]);
        $pdf = file_get_contents($fichier);
        unlink($fichier);

        // A4 portrait : 595,28 × 841,89 points
        expect($pdf)->toStartWith('%PDF')->toContain('/MediaBox [0.000 0.000 595.280 841.890]');

        return preg_match_all('#/Type /Page\b#', $pdf);
    };

    // La liste, le bilan et les signatures sur une seule page pour une classe de 45 élèves
    expect($pages(45))->toBe(1)
        ->and($pages(60))->toBe(2);
});

test('un formulaire expiré (419) renvoie à la page précédente avec un message, jamais « Page Expired »', function () {
    Route::post('/_test/formulaire-expire', fn () => throw new TokenMismatchException)->middleware('web');

    $this->from('/login')
        ->post('/_test/formulaire-expire', ['login' => 'admin@ecole.bj', 'password' => 'secret'])
        ->assertRedirect('/login')
        ->assertSessionHas('error', 'Votre session a expiré. Merci de réessayer.')
        ->assertSessionHasInput('login', 'admin@ecole.bj')
        ->assertSessionMissing('_old_input.password');
});

test("une erreur inattendue n'affiche jamais de détail technique, même avec APP_DEBUG", function () {
    config(['app.debug' => true]);
    Route::get('/_test/erreur', fn () => throw new RuntimeException('SQLSTATE[42S22] detail interne'))->middleware('web');

    $this->get('/_test/erreur')
        ->assertStatus(500)
        ->assertSee('Une erreur est survenue')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('RuntimeException');

    $this->getJson('/_test/erreur')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Une erreur est survenue. Veuillez réessayer.']);
});

test('une erreur HTTP en JSON ne contient ni trace ni message technique anglais', function () {
    config(['app.debug' => true]);

    $this->getJson('/api/route-qui-n-existe-pas')
        ->assertNotFound()
        ->assertJsonMissingPath('trace')
        ->assertJsonMissingPath('exception')
        ->assertJson(['message' => 'Ressource introuvable.']);
});
