<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\YoutubeAccount;
use App\Services\YouTube\YouTubeLiveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class LessonLiveController extends Controller
{
    public function startLive(Request $request, Lesson $lesson)
    {
        // Authorize teacher permission here...

        $account = YoutubeAccount::query()
            ->where('is_active', true)
            ->latest()
            ->firstOrFail();

        $service = new YouTubeLiveService($account);

        $scheduledStart = Carbon::now()->addMinutes(1);

        $result = $service->createAndBindLive(
            title: $lesson->title . ' (Live)',
            description: $lesson->summary,
            scheduledStart: $scheduledStart,
            privacyStatus: 'unlisted',
            enableDvr: true,
            autoStart: true,
            autoStop: true
        );

        $this->startYoutubeRestream($lesson->id, $result['rtmps_url'], $result['stream_key']);

        // Create/Update lesson_media row
        $media = LessonMedia::query()->create([
            'lesson_id' => $lesson->id,
            'provider' => 'youtube',
            'media_type' => 'live',
            'status' => 'scheduled',
            'yt_broadcast_id' => $result['broadcast_id'],
            'yt_stream_id' => $result['stream_id'],
            'yt_video_id' => $result['video_id'],
            'yt_rtmps_url' => $result['rtmps_url'],
            // If you want to store stream key, do it encrypted, or omit:
            // 'yt_stream_key_enc' => encrypt($result['stream_key']),
            'yt_privacy_status' => 'unlisted',
            'yt_scheduled_start_time' => $scheduledStart,
        ]);

        // Set as primary media (optional)
        $lesson->primary_media_id = $media->id;
        $lesson->save();

        // Return to teacher UI (show OBS details)
        return response()->json([
            'lesson_id' => $lesson->id,
            'media_id' => $media->id,
            'youtube_video_id' => $result['video_id'],
            'embed_url' => $result['embed_url'],
            'rtmps_url' => $result['rtmps_url'],
            'stream_key' => $result['stream_key'], // show once
        ]);
    }

    private function startYoutubeRestream(int $lessonId, string $rtmpsUrl, string $streamKey): void
    {
        $rtmpBase = config('services.srs.rtmp_base_url', env('SRS_RTMP_BASE_URL', 'rtmp://localhost/live'));
        $streamName = 'lesson-' . $lessonId;
        $inputUrl = rtrim($rtmpBase, '/') . '/' . $streamName;

        if (! str_contains($rtmpsUrl, '://')) {
            $rtmpsUrl = 'rtmps://' . ltrim($rtmpsUrl, '/');
        }
        $outputUrl = rtrim($rtmpsUrl, '/') . '/' . $streamKey;

        $ffmpegPath = config('services.srs.ffmpeg_path', env('FFMPEG_PATH', 'ffmpeg'));
        $ffmpegCmd = sprintf(
            '%s -re -i %s -c copy -f flv %s',
            escapeshellcmd($ffmpegPath),
            escapeshellarg($inputUrl),
            escapeshellarg($outputUrl)
        );

        $command = sprintf(
            'for i in {1..30}; do %s && exit 0; sleep 2; done; exit 1',
            $ffmpegCmd
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();
    }
}
