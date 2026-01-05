<?php

namespace App\Services\YouTube;

use App\Models\YoutubeAccount;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\YouTube;
use Google\Service\YouTube\CdnSettings;
use Google\Service\YouTube\LiveBroadcast;
use Google\Service\YouTube\LiveBroadcastContentDetails;
use Google\Service\YouTube\LiveBroadcastSnippet;
use Google\Service\YouTube\LiveBroadcastStatus;
use Google\Service\YouTube\LiveStream;
use Google\Service\YouTube\LiveStreamSnippet;
use Illuminate\Support\Str;

class YouTubeLiveService
{
    public function __construct(
        private readonly YoutubeAccount $account,
    ) {}

    /**
     * Create broadcast + stream + bind and return data for OBS/broadcaster + embed.
     *
     * @return array{
     *  broadcast_id:string,
     *  stream_id:string,
     *  video_id:string,
     *  rtmps_url:string,
     *  stream_key:string,
     *  embed_url:string,
     * }
     */
    public function createAndBindLive(
        string $title,
        ?string $description,
        Carbon $scheduledStart,
        string $privacyStatus = 'unlisted',
        bool $enableDvr = true,
        bool $autoStart = true,
        bool $autoStop = true,
        string $resolution = '720p',
        string $frameRate = '30fps'
    ): array {
        $yt = $this->youtube();

        // 1) Create Broadcast (Live event)
        $broadcast = $this->createBroadcast(
            yt: $yt,
            title: $title,
            description: $description,
            scheduledStart: $scheduledStart,
            privacyStatus: $privacyStatus,
            enableDvr: $enableDvr,
            autoStart: $autoStart,
            autoStop: $autoStop
        );

        // 2) Create Stream (RTMPS + key)
        $stream = $this->createStream(
            yt: $yt,
            title: $title . ' Stream ' . Str::random(6),
            resolution: $resolution,
            frameRate: $frameRate
        );

        // 3) Bind
        $yt->liveBroadcasts->bind(
            $broadcast['id'],
            'id,contentDetails,status,snippet',
            ['streamId' => $stream['id']]
        );

        // Extract ingestion info
        $ingestion = $stream['cdn']['ingestionInfo'] ?? null;

        $rtmpsUrl = $ingestion['rtmpsIngestionAddress'] ?? ($ingestion['ingestionAddress'] ?? null);
        $streamKey = $ingestion['streamName'] ?? null;

        if (! $rtmpsUrl || ! $streamKey) {
            throw new \RuntimeException('YouTube ingestion info not returned (rtmps url / stream key).');
        }

        // YouTube embed: typically broadcast id works as the watchable video id.
        // Many implementations store broadcast id as "video id" for embed.
        $videoId = $broadcast['id'];

        return [
            'broadcast_id' => $broadcast['id'],
            'stream_id' => $stream['id'],
            'video_id' => $videoId,
            'rtmps_url' => $rtmpsUrl,
            'stream_key' => $streamKey,
            'embed_url' => "https://www.youtube.com/embed/{$videoId}",
        ];
    }

    /**
     * Transition broadcast status: testing | live | complete
     */
    public function transition(string $broadcastId, string $toStatus): array
    {
        $allowed = ['testing', 'live', 'complete'];
        if (! in_array($toStatus, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid transition status. Allowed: testing, live, complete');
        }

        $yt = $this->youtube();
        $res = $yt->liveBroadcasts->transition(
            $toStatus,
            $broadcastId,
            'id,status,contentDetails,snippet'
        );

        return $res->toSimpleObject();
    }

    /**
     * Get broadcast info (useful for polling status)
     */
    public function getBroadcast(string $broadcastId): array
    {
        $yt = $this->youtube();
        $list = $yt->liveBroadcasts->listLiveBroadcasts(
            'id,status,contentDetails,snippet',
            ['id' => $broadcastId, 'maxResults' => 1]
        );

        $items = $list->getItems();
        if (! $items || count($items) === 0) {
            throw new \RuntimeException('Broadcast not found.');
        }

        return json_decode(json_encode($items[0]), true);
    }

    // -----------------------
    // Internal helpers
    // -----------------------

    private function youtube(): YouTube
    {
        return new YouTube($this->googleClient());
    }

    private function googleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.youtube.client_id'));
        $client->setClientSecret(config('services.youtube.client_secret'));
        $client->setRedirectUri(config('services.youtube.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        // Use refresh token stored for the connected school channel
        $client->refreshToken($this->account->refresh_token);

        return $client;
    }

    private function createBroadcast(
        YouTube $yt,
        string $title,
        ?string $description,
        Carbon $scheduledStart,
        string $privacyStatus,
        bool $enableDvr,
        bool $autoStart,
        bool $autoStop
    ): array {
        $snippet = new LiveBroadcastSnippet();
        $snippet->setTitle($title);
        if ($description) {
            $snippet->setDescription($description);
        }
        $snippet->setScheduledStartTime($scheduledStart->toRfc3339String());

        $status = new LiveBroadcastStatus();
        $status->setPrivacyStatus($privacyStatus);

        $contentDetails = new LiveBroadcastContentDetails();
        $contentDetails->setEnableDvr($enableDvr);
        $contentDetails->setEnableAutoStart($autoStart);
        $contentDetails->setEnableAutoStop($autoStop);

        $broadcast = new LiveBroadcast();
        $broadcast->setSnippet($snippet);
        $broadcast->setStatus($status);
        $broadcast->setContentDetails($contentDetails);

        $created = $yt->liveBroadcasts->insert('snippet,status,contentDetails', $broadcast);

        return json_decode(json_encode($created->toSimpleObject()), true);
    }

    private function createStream(
        YouTube $yt,
        string $title,
        string $resolution,
        string $frameRate
    ): array {
        $snippet = new LiveStreamSnippet();
        $snippet->setTitle($title);

        $cdn = new CdnSettings();
        $cdn->setIngestionType('rtmp');
        $cdn->setResolution($resolution);
        $cdn->setFrameRate($frameRate);

        $stream = new LiveStream();
        $stream->setSnippet($snippet);
        $stream->setCdn($cdn);

        $created = $yt->liveStreams->insert('snippet,cdn,contentDetails,status', $stream);

        return json_decode(json_encode($created->toSimpleObject()), true);
    }
}
