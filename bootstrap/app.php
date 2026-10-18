<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Aucun utilisateur ne doit voir de code, de trace ou de page « Page Expired ».
        // Les erreurs restent journalisées normalement dans storage/logs/laravel.log.

        // Formulaire resté ouvert trop longtemps (jeton CSRF expiré, 419) : au lieu de la page
        // « Page Expired », on revient à la page précédente avec la saisie conservée et un message clair.
        // Fichiers trop volumineux pour le serveur (413) : même retour à la page précédente, avec un message.
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! in_array($e->getStatusCode(), [413, 419], true) || $request->expectsJson()) {
                return null;
            }

            if ($e->getStatusCode() === 413) {
                // L'application mobile (routes « api ») garde sa réponse d'erreur habituelle
                if ($request->is('api/*')) {
                    return null;
                }

                $retour = fn () => redirect()->back()
                    ->with('error', 'Les fichiers envoyés sont trop volumineux. Envoyez-en moins à la fois ou des fichiers plus légers.');

                // Un envoi trop volumineux est refusé dès l'arrivée de la requête, avant l'ouverture de la
                // session : on l'ouvre ici, sinon le message serait perdu au moment de la redirection.
                return $request->hasSession()
                    ? $retour()
                    : (new Pipeline(app()))->send($request)
                        ->through([EncryptCookies::class, StartSession::class])
                        ->then($retour);
            }

            if ($request->routeIs('logout')) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/');
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'Votre session a expiré. Merci de réessayer.');
        });

        // Erreur inattendue (bug, base de données indisponible…) : page ou message générique,
        // quel que soit APP_DEBUG. Les exceptions « normales » gardent leur traitement Laravel.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpResponseException) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Une erreur est survenue. Veuillez réessayer.'], 500);
            }

            // Les vues d'erreur héritent de « errors::minimal » : espace de noms à déclarer avant le rendu
            (new RegisterErrorViewPaths)();

            return response()->view('errors::500', [], 500);
        });

        // Réponses JSON d'erreur (application mobile) : jamais de classe, fichier ou trace,
        // ni de message technique anglais du framework.
        $exceptions->respond(function (Response $response) {
            if (! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
                return $response;
            }

            $data = $response->getData(true);
            if (! is_array($data)) {
                return $response;
            }

            unset($data['exception'], $data['file'], $data['line'], $data['trace']);

            $messages = [
                404 => 'Ressource introuvable.',
                405 => 'Action non autorisée.',
                413 => 'Les fichiers envoyés sont trop volumineux.',
                419 => 'Session expirée. Veuillez vous reconnecter.',
                429 => 'Trop de tentatives. Veuillez patienter avant de réessayer.',
                500 => 'Une erreur est survenue. Veuillez réessayer.',
                503 => 'Service momentanément indisponible. Veuillez réessayer plus tard.',
            ];
            if (isset($messages[$response->getStatusCode()])) {
                $data['message'] = $messages[$response->getStatusCode()];
            }

            return $response->setData($data);
        });
    })->create();
