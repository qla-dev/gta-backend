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

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    'google' => [
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID', '26872246820-lolanjps9n6bfisn3srjpl6odd9nm2ak.apps.googleusercontent.com'),
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID', '26872246820-2nmb25vtdtk21i88a2q4tm48d76h7i8g.apps.googleusercontent.com'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID', '26872246820-hpgopunb07b6mm9k6c91a5qbt9sk1rup.apps.googleusercontent.com'),
    ],

];
