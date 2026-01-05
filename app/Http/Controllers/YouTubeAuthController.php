<?php

namespace App\Http\Controllers;

use App\Models\YoutubeAccount;
use Google\Client as GoogleClient;
use Google\Service\YouTube;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class YouTubeAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $client = $this->googleClient();

        $state = Str::random(40);
        $request->session()->put('youtube_oauth_state', $state);
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return $this->redirectWithStatus('error');
        }

        $expectedState = $request->session()->pull('youtube_oauth_state');
        if (! $expectedState || $expectedState !== $request->get('state')) {
            abort(419, 'Invalid OAuth state.');
        }

        $code = $request->get('code');
        if (! $code) {
            return $this->redirectWithStatus('missing_code');
        }

        $client = $this->googleClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return $this->redirectWithStatus('token_error');
        }

        $refreshToken = $token['refresh_token'] ?? null;
        if (! $refreshToken) {
            return $this->redirectWithStatus('missing_refresh_token');
        }

        $client->setAccessToken($token);
        $channel = $this->fetchChannel($client);

        YoutubeAccount::query()->update(['is_active' => false]);

        YoutubeAccount::query()->create([
            'channel_id' => $channel['id'] ?? null,
            'channel_title' => $channel['title'] ?? null,
            'refresh_token' => $refreshToken,
            'connected_at' => now(),
            'is_active' => true,
        ]);

        return $this->redirectWithStatus('connected');
    }

    private function googleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.youtube.client_id'));
        $client->setClientSecret(config('services.youtube.client_secret'));
        $client->setRedirectUri(config('services.youtube.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            YouTube::YOUTUBE,
        ]);

        return $client;
    }

    private function fetchChannel(GoogleClient $client): array
    {
        $service = new YouTube($client);
        $response = $service->channels->listChannels('id,snippet', [
            'mine' => true,
            'maxResults' => 1,
        ]);

        $items = $response->getItems();
        if (! $items || count($items) === 0) {
            return [];
        }

        $item = $items[0];

        return [
            'id' => $item->getId(),
            'title' => $item->getSnippet()?->getTitle(),
        ];
    }

    private function redirectWithStatus(string $status)
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');

        return redirect()->away("{$frontendUrl}/manager/youtube-connector?status={$status}");
    }
}
