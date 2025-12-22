<?php

namespace App\Http\Controllers\Streaming;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CloudflareWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // TODO: Persist VOD completion events and update lesson_media records.
        return response()->json(['status' => 'received']);
    }
}
