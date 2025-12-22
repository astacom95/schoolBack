<?php

return [
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    'stream_token' => env('CLOUDFLARE_STREAM_TOKEN'),
    'signing_key' => env('CLOUDFLARE_SIGNING_KEY'),
    'base_url' => 'https://api.cloudflare.com/client/v4/accounts'
];
