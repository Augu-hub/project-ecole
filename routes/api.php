<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CantineController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\EleveController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\UniformeController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API fonctionne',
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->group(function () {
    // Vérification de validité du token (utilisé par l'app mobile au démarrage
    // pour décider de sauter l'écran de connexion).
    Route::get('/me', [AuthController::class, 'me']);

    // Parents
    Route::get('/parents/{id}/enfants', [ParentController::class, 'enfants']);

    // Élèves
    Route::get('/eleves/{id}/dernieres-notes', [EleveController::class, 'dernieresNotes']);
    Route::get('/eleves/{id}/toutes-notes', [EleveController::class, 'toutesNotes']);
    Route::get('/eleves/{id}/statistiques', [EleveController::class, 'statistiques']);
    Route::get('/eleves/{id}/annees-scolaires', [EleveController::class, 'anneesScolaires']);
    Route::get('/eleves/{id}/bulletin', [EleveController::class, 'bulletin']);
    Route::get('/eleves/{id}/emploi-du-temps', [EleveController::class, 'emploiDuTemps']);

    Route::post('/save-fcm-token', [NotificationController::class, 'saveFCMToken']);
    Route::post('/send-notification', [NotificationController::class, 'sendNotification']);

    // Scolarité
    Route::get('/eleves/{eleve}/scolarite', [PaiementController::class, 'getScolariteEnfant']);
    Route::get('/parents/{parent}/paiements-recents', [PaiementController::class, 'getPaiementsRecents']);

    // FedaPay
    Route::post('/paiements/create-order', [PaiementController::class, 'createOrder']);
    Route::post('/paiements/capture-order', [PaiementController::class, 'captureOrder']);

    // Reçu PDF
    Route::get('/paiements/{paiement}/recu', [PaiementController::class, 'downloadRecu']);

    // Cantine
    Route::get('/eleves/{eleve}/cantine', [CantineController::class, 'getCantineEnfant']);
    Route::post('/cantine/create-order', [CantineController::class, 'createOrder']);
    Route::post('/cantine/capture-order', [CantineController::class, 'captureOrder']);

    // Uniformes
    Route::get('/uniformes/catalogue', [UniformeController::class, 'getCatalogue']);
    Route::get('/eleves/{eleve}/uniformes/historique', [UniformeController::class, 'getHistoriqueEnfant']);
    Route::post('/uniformes/create-order', [UniformeController::class, 'createOrder']);
    Route::post('/uniformes/capture-order', [UniformeController::class, 'captureOrder']);

    // Communiqués
    Route::get('/communiques', [\App\Http\Controllers\Api\CommuniqueApiController::class, 'index']);
    Route::get('/communiques/{communique}', [\App\Http\Controllers\Api\CommuniqueApiController::class, 'show']);

    Route::post('/change-password', [\App\Http\Controllers\Api\AuthController::class, 'changePassword']);

    // Messagerie (Laravel Reverb pour le temps réel, voir routes/channels.php)
    Route::get('/staff', [ChatController::class, 'staff']);
    Route::get('/conversations', [ChatController::class, 'mesConversations']);
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/conversations', [ChatController::class, 'creerConversation']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'envoyerMessage']);
    Route::post('/conversations/{conversation}/marquer-lu', [ChatController::class, 'marquerLu']);

    // Autorisation des canaux privés Reverb (accepte tokens Bearer ET session cookie de
    // première partie, donc utilisable à la fois par le mobile et l'admin web).
    Broadcast::routes(['middleware' => ['auth:sanctum']]);
});

// Callbacks FedaPay
Route::get('/fedapay/success', [PaiementController::class, 'successRedirect']);
Route::get('/fedapay/cancel', [PaiementController::class, 'cancelRedirect']);
