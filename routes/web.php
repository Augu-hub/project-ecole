<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CahierNotesController;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Admin\TypeNoteController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Pas de page d'accueil publique : l'adresse du site mène à la connexion, ou au tableau de bord
// du compte quand on est déjà connecté.
Route::get('/', fn () => redirect()->route(Auth::user()?->routeTableauDeBord() ?? 'login'));

Route::view('/politique-de-confidentialite', 'privacy-policy')
    ->name('privacy.policy');

// ==================== VÉRIFICATION PUBLIQUE CERTIFICAT (sans authentification) ====================
Route::get('/certificats/verifier/{numero}', [Admin\CertificatScolariteController::class, 'verifier'])->name('certificats.verifier');

// ==================== ROUTES ADMIN ====================
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {

    Route::get('personnel', [PersonnelController::class, 'index'])->name('personnel.index')->middleware('role:admin,censeur,directeur');
    Route::get('personnel/create', [PersonnelController::class, 'create'])->name('personnel.create')->middleware('role:admin,censeur,directeur');
    Route::post('personnel', [PersonnelController::class, 'store'])->name('personnel.store')->middleware('role:admin,censeur,directeur');
    Route::get('personnel/{personnel}/edit', [PersonnelController::class, 'edit'])->name('personnel.edit')->middleware('role:admin,censeur,directeur');
    Route::put('personnel/{personnel}', [PersonnelController::class, 'update'])->name('personnel.update')->middleware('role:admin,censeur,directeur');
    Route::delete('personnel/{personnel}', [PersonnelController::class, 'destroy'])->name('personnel.destroy')->middleware('role:admin,censeur,directeur');
    Route::post('personnel/{personnel}/reset-password', [PersonnelController::class, 'resetPassword'])->name('personnel.reset-password')->middleware('role:admin,censeur,directeur');

    // ==================== ÉQUIPE DU PRÉFET (surveillants) ====================
    Route::prefix('equipe')->name('equipe.')->middleware('role:prefet')->group(function () {
        Route::get('/', [Admin\EquipeController::class, 'index'])->name('index');
        Route::get('create', [Admin\EquipeController::class, 'create'])->name('create');
        Route::post('/', [Admin\EquipeController::class, 'store'])->name('store');
        Route::get('{membre}/edit', [Admin\EquipeController::class, 'edit'])->name('edit');
        Route::put('{membre}', [Admin\EquipeController::class, 'update'])->name('update');
        Route::delete('{membre}', [Admin\EquipeController::class, 'destroy'])->name('destroy');
        Route::post('{membre}/reset-password', [Admin\EquipeController::class, 'resetPassword'])->name('reset-password');
    });

    // Dashboard admin
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard')->middleware('role:admin,directeur,censeur,econome,prefet,surveillant,saisisseur');

    // ==================== PARENTS ====================
    Route::get('parents/import', [Admin\ParentController::class, 'showForm'])->name('parents.import.phpoffice')->middleware('role:admin,censeur,directeur,econome');
    Route::post('parents/import', [Admin\ParentController::class, 'import'])->name('parents.import.phpoffice.post')->middleware('role:admin,censeur,directeur,econome');
    Route::resource('parents', Admin\ParentController::class)->only(['index', 'edit', 'update', 'destroy'])->middleware('role:admin,censeur,directeur,econome');
    Route::get('parents/{parent}/reset-password', [Admin\ParentController::class, 'resetPasswordForm'])->name('parents.reset-password.form')->middleware('role:admin,censeur,directeur,econome');
    Route::post('parents/{parent}/reset-password', [Admin\ParentController::class, 'resetPassword'])->name('parents.reset-password')->middleware('role:admin,censeur,directeur,econome');

    // ==================== NIVEAUX, CLASSES, ANNÉES, MATIÈRES ====================
    Route::resource('niveaux', Admin\NiveauController::class)->except(['show'])->middleware('role:admin,censeur,directeur,econome');
    Route::resource('classes', Admin\ClasseController::class)->except(['show'])->middleware('role:admin,censeur,directeur,econome');
    Route::resource('annees-scolaires', Admin\AnneeScolaireController::class)->except(['show'])->parameters(['annees-scolaires' => 'anneeScolaire'])->middleware('role:admin,censeur,directeur,econome');
    Route::resource('classe-annees', Admin\ClasseAnneeController::class)->except(['show'])->parameters(['classe-annees' => 'classeAnnee'])->middleware('role:admin,censeur,directeur,econome');
    Route::resource('matieres', Admin\MatiereController::class)->except(['show'])->middleware('role:admin,censeur,directeur,econome');

    // ==================== GESTION DES COEFFICIENTS ====================
    Route::prefix('classe-annees/{classeAnnee}/matieres')->middleware('role:admin,censeur,directeur,econome')->name('classe-matieres.')->group(function () {
        Route::get('/', [Admin\ClasseMatiereController::class, 'index'])->name('index');
        Route::post('/', [Admin\ClasseMatiereController::class, 'store'])->name('store');
        Route::put('{matiere}', [Admin\ClasseMatiereController::class, 'update'])->name('update');
        Route::delete('{matiere}', [Admin\ClasseMatiereController::class, 'destroy'])->name('destroy');
        // Reprise des matières de l'année précédente, pour cette classe seule
        Route::post('reprise', [Admin\ClasseMatiereController::class, 'reprendre'])->name('reprise');
    });
    // Même reprise, pour toutes les classes de l'année active qui n'ont encore aucune matière (mêmes rôles)
    Route::post('classe-matieres/reprise', [Admin\ClasseMatiereController::class, 'reprendrePourAnneeActive'])
        ->name('classe-matieres.reprise-annee')
        ->middleware('role:admin,censeur,directeur,econome');

    // ==================== CRÉNEAUX HORAIRES ====================
    Route::resource('creneaux-horaires', Admin\CreneauHoraireController::class)
        ->except(['show'])
        ->parameters(['creneaux-horaires' => 'creneauxHoraire'])
        ->middleware('role:admin,censeur,directeur,econome,prefet,surveillant');

    // ==================== EMPLOI DU TEMPS (secondaire) ====================
    Route::middleware('role:admin,censeur,directeur,econome,prefet,surveillant')->group(function () {
        Route::get('classe-annees/{classeAnnee}/emploi-du-temps', [Admin\EmploiDuTempsController::class, 'classe'])->name('emploi-du-temps.classe');
        Route::post('classe-annees/{classeAnnee}/emploi-du-temps', [Admin\EmploiDuTempsController::class, 'store'])->name('emploi-du-temps.store');
        Route::delete('emploi-du-temps/{emploiDuTemps}', [Admin\EmploiDuTempsController::class, 'destroy'])->name('emploi-du-temps.destroy');
        Route::get('enseignants/{enseignant}/emploi-du-temps', [Admin\EmploiDuTempsController::class, 'parEnseignant'])->name('emploi-du-temps.enseignant');
    });

    // ==================== SCOLARITÉS ====================
    Route::resource('scolarites', App\Http\Controllers\Admin\ScolariteController::class)->except(['show'])->middleware('role:admin,censeur,directeur,econome');

    // ==================== TRANCHES ====================
    Route::prefix('scolarites/{scolarite}/tranches')->name('tranches.')->middleware('role:admin,censeur,directeur,econome')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\TrancheController::class, 'index'])->name('index');
        Route::get('create', [App\Http\Controllers\Admin\TrancheController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\TrancheController::class, 'store'])->name('store');
        Route::get('{tranche}/edit', [App\Http\Controllers\Admin\TrancheController::class, 'edit'])->name('edit');
        Route::put('{tranche}', [App\Http\Controllers\Admin\TrancheController::class, 'update'])->name('update');
        Route::delete('{tranche}', [App\Http\Controllers\Admin\TrancheController::class, 'destroy'])->name('destroy');
    });

    // ==================== FRAIS DE DOSSIER (secondaire) ====================
    Route::resource('frais-dossiers', App\Http\Controllers\Admin\FraisDossierController::class)->except(['show'])->parameters(['frais-dossiers' => 'fraisDossier'])->middleware('role:admin,censeur,directeur,econome');
    Route::post('frais-dossiers/{fraisDossier}/payer', [App\Http\Controllers\Admin\FraisDossierController::class, 'payer'])->name('frais-dossiers.payer')->middleware('role:admin,censeur,directeur,econome');

    // ==================== ÉLÈVES ====================
    Route::resource('eleves', Admin\EleveController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['eleves' => 'eleve'])
        ->middleware('role:admin,censeur,directeur,econome');
    Route::get('eleves/import', [Admin\ImportEleveController::class, 'showForm'])->name('eleves.import')->middleware('role:admin,censeur,directeur,econome');
    Route::post('eleves/import', [Admin\ImportEleveController::class, 'import'])->name('eleves.import.post')->middleware('role:admin,censeur,directeur,econome');

    Route::prefix('eleves/import')->name('eleves.import.')->middleware('role:admin,censeur,directeur,econome')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ImportEleveController::class, 'create'])->name('create');
        Route::post('/preview', [App\Http\Controllers\Admin\ImportEleveController::class, 'preview'])->name('preview');
        Route::post('/store', [App\Http\Controllers\Admin\ImportEleveController::class, 'store'])->name('store');
    });

    // ==================== DOCUMENTS ÉLÈVES ====================
    Route::middleware('role:admin,censeur,directeur,econome')->group(function () {
        Route::post('eleves/{eleve}/fichiers', [Admin\FichierController::class, 'store'])->name('eleves.fichiers.store');
        Route::delete('fichiers/{fichier}', [Admin\FichierController::class, 'destroy'])->name('fichiers.destroy');
        Route::get('fichiers/{fichier}/download', [Admin\FichierController::class, 'download'])->name('fichiers.download');
    });

    // ==================== CERTIFICATS DE SCOLARITÉ (secondaire) ====================
    Route::middleware('role:admin,censeur,directeur,econome')->group(function () {
        Route::get('certificats', [Admin\CertificatScolariteController::class, 'index'])->name('certificats.index');
        Route::post('eleves/{eleve}/certificats', [Admin\CertificatScolariteController::class, 'store'])->name('certificats.store');
        Route::get('certificats/{certificat}/download', [Admin\CertificatScolariteController::class, 'download'])->name('certificats.download');
    });

    // ==================== CANTINE (tous cycles) ====================
    Route::middleware('role:admin,censeur,directeur,econome')->group(function () {
        Route::get('cantine/tarifs', [Admin\CantineOffreController::class, 'index'])->name('cantine.tarifs');
        Route::post('cantine/tarifs', [Admin\CantineOffreController::class, 'enregistrer'])->name('cantine.tarifs.enregistrer');
        Route::post('eleves/{eleve}/cantine/{offre}/payer', [Admin\CantineController::class, 'payer'])->name('cantine.payer');
    });

    // ==================== UNIFORMES (secondaire) ====================
    Route::middleware('role:admin,censeur,directeur,econome')->group(function () {
        Route::resource('uniformes', Admin\UniformeController::class)->except(['show', 'create', 'edit']);
        Route::post('eleves/{eleve}/uniformes/vendre', [Admin\UniformeVenteController::class, 'vendre'])->name('uniformes.vendre');
        Route::post('uniformes-ventes/{vente}/payer', [Admin\UniformeVenteController::class, 'payer'])->name('uniformes.payer');
    });

    // ==================== JOURNAL DES CONNEXIONS (audit) ====================
    Route::middleware('role:admin,directeur')->group(function () {
        Route::get('connexion-logs', [Admin\ConnexionLogController::class, 'index'])->name('connexion-logs.index');
    });

    // ==================== MESSAGERIE (staff administratif) ====================
    Route::middleware('role:admin,censeur,directeur,econome,prefet,surveillant')->group(function () {
        Route::get('messagerie', [Admin\MessagerieController::class, 'index'])->name('messagerie.index');
        Route::get('messagerie/rechercher-parent', [Admin\MessagerieController::class, 'rechercherParent'])->name('messagerie.rechercher-parent');
        Route::get('messagerie/conversations', [Admin\MessagerieController::class, 'mesConversations'])->name('messagerie.conversations');
        Route::get('messagerie/conversations/{conversation}/messages', [Admin\MessagerieController::class, 'messages'])->name('messagerie.messages');
        Route::post('messagerie/conversations', [Admin\MessagerieController::class, 'creerConversation'])->name('messagerie.creer-conversation');
        Route::post('messagerie/conversations/{conversation}/messages', [Admin\MessagerieController::class, 'envoyerMessage'])->name('messagerie.envoyer-message');
        Route::post('messagerie/conversations/{conversation}/marquer-lu', [Admin\MessagerieController::class, 'marquerLu'])->name('messagerie.marquer-lu');
    });

    // ==================== NOTES - ROUTES COMPLÈTES ====================
    // Type de notes
    Route::resource('type-notes', TypeNoteController::class)->except(['show'])->parameters(['type-notes' => 'typeNote'])->middleware('role:admin,censeur,directeur,econome');

    // Périodes
    Route::resource('periodes', App\Http\Controllers\Admin\PeriodeController::class)->except(['show'])->middleware('role:admin,censeur,directeur,econome');

    // Routes principales des notes
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index')->middleware('role:admin,censeur,directeur,econome,saisisseur');
    Route::get('/notes/create', [NoteController::class, 'create'])->name('notes.create')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');
    Route::match(['get', 'post'], '/notes/preview', [NoteController::class, 'preview'])->name('notes.preview')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');

    // Routes d'export
    Route::get('/notes/export', [NoteController::class, 'export'])->name('notes.export')->middleware('role:admin,censeur,directeur,econome,saisisseur');
    Route::get('/notes/export-pdf', [NoteController::class, 'exportPdf'])->name('notes.export-pdf')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');
    Route::get('/notes/export-template', [NoteController::class, 'exportTemplate'])->name('notes.export-template')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');
    Route::get('/notes/export-template-excel', [NoteController::class, 'exportTemplateExcel'])->name('notes.export-template-excel')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');

    // Routes d'import
    Route::post('/notes/import-preview', [NoteController::class, 'importPreview'])->name('notes.import-preview')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');
    Route::post('/notes/import-image', [NoteController::class, 'importImage'])->name('notes.import-image')->middleware('role:admin,censeur,directeur,econome,saisisseur,enseignant');

    // ==================== RÉINSCRIPTIONS (secondaire) ====================
    Route::prefix('reinscriptions')->name('reinscriptions.')->middleware('role:admin,censeur,directeur,econome')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ReinscriptionController::class, 'index'])->name('index');
        Route::get('/campagne/{anneeSource}', [App\Http\Controllers\Admin\ReinscriptionController::class, 'campagne'])->name('campagne');
        Route::get('/campagne/{anneeSource}/classe/{classeSource}', [App\Http\Controllers\Admin\ReinscriptionController::class, 'campagneClasse'])->name('campagne-classe');
        Route::post('/campagne/{anneeSource}/classe/{classeSource}', [App\Http\Controllers\Admin\ReinscriptionController::class, 'confirmer'])->name('confirmer');
    });

    // ==================== CAHIER DE NOTES ====================
    Route::prefix('cahier-notes')->name('cahier-notes.')->middleware('role:admin,censeur,directeur,econome,prefet,surveillant')->group(function () {
        // Page de sélection (choix de la classe et de la période)
        Route::get('/', [CahierNotesController::class, 'index'])->name('index');

        // Cahier de notes complet d'une classe (avec filtres en query string)
        Route::get('/classe/{classeAnnee}', [CahierNotesController::class, 'classe'])->name('classe');

        // Liste des élèves d'une classe (point d'entrée vers les bulletins)
        Route::get('/classe/{classeAnnee}/eleves', [CahierNotesController::class, 'listeEleves'])->name('liste-eleves');

        // Bulletin individuel d'un élève (toutes périodes)
        Route::get('/eleve/{eleve}', [CahierNotesController::class, 'bulletinEleve'])->name('bulletin-eleve');
    });

    // ==================== COMMUNIQUÉS ====================
    Route::resource('communiques', \App\Http\Controllers\Admin\CommuniqueController::class)->middleware('role:admin,censeur,directeur,econome,prefet,surveillant');
    Route::patch('communiques/{communique}/toggle', [\App\Http\Controllers\Admin\CommuniqueController::class, 'toggle'])->name('communiques.toggle')->middleware('role:admin,censeur,directeur,econome,prefet,surveillant');

    // ==================== ACCÈS AUX MENUS (réglage compte par compte) ====================
    Route::prefix('acces-menus')->name('acces-menus.')->middleware('role:admin,directeur')->group(function () {
        Route::get('/', [Admin\AccesMenuController::class, 'index'])->name('index');
        Route::get('{compte}', [Admin\AccesMenuController::class, 'edit'])->name('edit');
        Route::put('{compte}', [Admin\AccesMenuController::class, 'update'])->name('update');
    });
});

// ==================== ROUTES ENSEIGNANT ====================
Route::prefix('enseignant')->name('enseignant.')->middleware(['auth', 'role:enseignant'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Enseignant\EnseignantController::class, 'dashboard'])->name('dashboard');
    Route::get('/emploi-du-temps', [\App\Http\Controllers\Enseignant\EnseignantController::class, 'emploiDuTemps'])->name('emploi-du-temps');
});

// ==================== ROUTES PAR DÉFAUT ====================
// Adresse générique (utilisée après certaines étapes de connexion) : renvoie au tableau de bord du compte
Route::get('/dashboard', fn () => redirect()->route(Auth::user()->routeTableauDeBord()))
    ->middleware(['auth', 'verified'])->name('dashboard');

// ==================== PROFIL ====================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ==================== AUTH ====================
require __DIR__.'/auth.php';
