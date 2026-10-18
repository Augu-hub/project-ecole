<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fedapay' => [
        'public_key' => env('FEDAPAY_PUBLIC_KEY'),
        'secret_key' => env('FEDAPAY_SECRET_KEY'),
        'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),

        // Compte FedaPay séparé pour le primaire (école distincte du secondaire) : seule
        // la cantine du primaire passe par ce compte. Tant que ces clés ne sont pas
        // renseignées, un paiement primaire échouera proprement côté FedaPay (401) plutôt
        // que d'être encaissé par erreur sur le compte du secondaire.
        'primaire' => [
            'public_key' => env('FEDAPAY_PRIMAIRE_PUBLIC_KEY'),
            'secret_key' => env('FEDAPAY_PRIMAIRE_SECRET_KEY'),
            'environment' => env('FEDAPAY_PRIMAIRE_ENVIRONMENT', env('FEDAPAY_ENVIRONMENT', 'sandbox')),
        ],
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),

        // Modèle qui lit les photos de fiches de notes. Celui-ci est le seul modèle « vision » ouvert
        // à la formule gratuite du compte ; avec une formule payante, un modèle plus puissant
        // (mistral-medium-latest) lit mieux l'écriture manuscrite.
        'vision_model' => env('MISTRAL_VISION_MODEL', 'ministral-14b-latest'),
    ],

    // Config Reverb côté client (navigateur admin) : le thème admin n'a pas de pipeline Vite,
    // ces valeurs sont donc injectées manuellement dans la vue plutôt que via import.meta.env.
    'reverb_client' => [
        'host' => env('REVERB_HOST', '127.0.0.1'),
        'port' => env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],

];
