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
'youtube' => [
    'client_id' => env('YOUTUBE_CLIENT_ID'),
    'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
    'redirect' => env('YOUTUBE_REDIRECT_URI'),
],

    'srs' => [
        'whip_base_url' => env('SRS_WHIP_BASE_URL', 'http://localhost:1985/rtc/v1/whip'),
        'rtmp_base_url' => env('SRS_RTMP_BASE_URL', 'rtmp://localhost/live'),
        'rtmp_publish_url' => env('SRS_RTMP_PUBLISH_URL', 'rtmp://localhost/live'),
        'whip_app' => env('SRS_WHIP_APP', 'live'),
        'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
        'restream_audio_bitrate' => env('RESTREAM_AUDIO_BITRATE', '192k'),
        'restream_audio_rate' => env('RESTREAM_AUDIO_RATE', 44100),
        'restream_audio_channels' => env('RESTREAM_AUDIO_CHANNELS', 1),
        'restream_audio_filter_base' => env('RESTREAM_AUDIO_FILTER_BASE', 'highpass=f=150,lowpass=f=5000,afftdn=nr=12:nt=w'),
        'restream_audio_enable_rnnoise' => env('RESTREAM_AUDIO_ENABLE_RNNOISE', false),
        'restream_rnnoise_model_path' => env('RESTREAM_RNNOISE_MODEL_PATH', '/usr/local/share/rnnoise/cb.rnnn'),
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
