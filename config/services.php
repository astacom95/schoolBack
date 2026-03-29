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
    'srs' => [
        'whip_base_url' => env('SRS_WHIP_BASE_URL', 'http://localhost:1985/rtc/v1/whip'),
        'whip_app' => env('SRS_WHIP_APP', 'live'),
        'playback_flv_base_url' => env('SRS_PLAYBACK_FLV_BASE_URL', 'http://localhost:8080/live'),
        'recordings_path' => env('SRS_RECORDINGS_PATH', storage_path('app/srs-recordings')),
        'recording_finalize_wait_seconds' => env('SRS_RECORDING_FINALIZE_WAIT_SECONDS', 12),
        'wasabi_public_base_url' => env('WASABI_PUBLIC_BASE_URL', ''),
        'wasabi_object_prefix' => env('WASABI_OBJECT_PREFIX', 'lessons'),
    ],

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

];
