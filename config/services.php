<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // SSO realm that issues API access tokens, e.g. https://sso.example.com/realms/holton
    'oidc' => [
        'issuer' => env('OIDC_ISSUER'),
        'audience' => env('OIDC_AUDIENCE', 'holton-xyz-api'),
        // Client whose client roles count, alongside realm roles and scopes.
        'roles_client' => env('OIDC_ROLES_CLIENT', env('OIDC_AUDIENCE', 'holton-xyz-api')),
        'leeway' => (int) env('OIDC_LEEWAY', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
