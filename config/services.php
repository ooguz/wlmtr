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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'wikimedia' => [
        'client_id' => env('WIKIMEDIA_CLIENT_ID'),
        'client_secret' => env('WIKIMEDIA_CLIENT_SECRET'),
        'redirect' => env('WIKIMEDIA_REDIRECT_URI'),
        'oauth_url' => env('WIKIMEDIA_OAUTH_URL', 'https://meta.wikimedia.org/w/rest.php/oauth2'),
        'api_url' => env('WIKIMEDIA_API_URL', 'https://meta.wikimedia.org/w/rest.php/oauth2'),
        'user_agent' => env('WIKIMEDIA_USER_AGENT', 'WLM-TR/1.0 (+https://meta.wikimedia.org/wiki/User_talk:Magurale)'),
    ],

];
