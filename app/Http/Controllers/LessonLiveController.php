<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\Specialization;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Throwable;

class LessonLiveController extends Controller
{
    public function startLive(Request $request, Lesson $lesson)
    {
        $teacher = $this->authorizedTeacher($request, $lesson);
        if (! $teacher) {
            return response()->json(['message' => 'غير مصرح بهذا الدرس.'], 403);
        }

        $streamName = 'lesson-' . $lesson->id;
        $whipUrl = $this->buildWhipUrl($streamName);
        $playbackFlvUrl = $this->buildPlaybackFlvUrl($streamName);

        $media = LessonMedia::query()
            ->where('lesson_id', $lesson->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $media) {
            $media = new LessonMedia();
            $media->lesson_id = $lesson->id;
        }

        $media->provider = 'external';
        $media->media_type = 'live';
        $media->status = 'live';
        $media->webrtc_stream_name = $streamName;
        $media->webrtc_ingest_url = $whipUrl;
        $media->source_url = $playbackFlvUrl;
        $media->started_at = now();
        $media->ended_at = null;
        $media->is_active = true;
        $media->save();

        $lesson->primary_media_id = $media->id;
        $lesson->save();

        return response()->json([
            'lesson_id' => $lesson->id,
            'media_id' => $media->id,
            'whip_url' => $whipUrl,
            'stream_name' => $streamName,
            'playback_flv_url' => $playbackFlvUrl,
        ]);
    }

    public function endLive(Request $request, Lesson $lesson)
    {
        $teacher = $this->authorizedTeacher($request, $lesson);
        if (! $teacher) {
            return response()->json(['message' => 'غير مصرح بهذا الدرس.'], 403);
        }

        $media = LessonMedia::query()
            ->where('lesson_id', $lesson->id)
            ->where('is_active', true)
            ->where('media_type', 'live')
            ->latest('id')
            ->first();

        if (! $media) {
            return response()->json([
                'message' => 'لا يوجد بث مباشر نشط لهذا الدرس.',
            ], 422);
        }

        $media->status = 'ended';
        $media->ended_at = now();
        $media->save();

        $streamName = $media->webrtc_stream_name ?: ('lesson-' . $lesson->id);
        $recordingsPath = rtrim((string) config('services.srs.recordings_path'), '/');
        $localFilePath = $this->resolveRecordingFilePath($recordingsPath, $streamName);

        if (! $localFilePath || ! is_file($localFilePath)) {
            $media->status = 'error';
            $media->save();

            return response()->json([
                'message' => 'لم يتم العثور على ملف التسجيل المحلي.',
                'recordings_path' => $recordingsPath,
                'expected_stream_name' => $streamName,
            ], 422);
        }

        try {
            $prefix = trim((string) config('services.srs.wasabi_object_prefix', 'lessons'), '/');
            $objectKey = "{$prefix}/{$lesson->id}/" . now()->format('Ymd_His') . '.mp4';

            $stream = fopen($localFilePath, 'r');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open local recording file.');
            }

            Storage::disk('s3')->put($objectKey, $stream, [
                'visibility' => 'public',
                'ContentType' => 'video/mp4',
            ]);
            fclose($stream);

            $publicBaseUrl = rtrim((string) config('services.srs.wasabi_public_base_url', ''), '/');
            $publicUrl = $publicBaseUrl !== ''
                ? $publicBaseUrl . '/' . ltrim($objectKey, '/')
                : Storage::disk('s3')->url($objectKey);

            $media->provider = 'external';
            $media->media_type = 'vod';
            $media->status = 'ended';
            $media->source_url = $publicUrl;
            $media->save();

            @unlink($localFilePath);

            return response()->json([
                'lesson_id' => $lesson->id,
                'media_id' => $media->id,
                'recording_url' => $publicUrl,
                'local_file_path' => $localFilePath,
                'uploaded' => true,
            ]);
        } catch (Throwable $e) {
            Log::error('end-live upload failed', [
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);

            $media->status = 'error';
            $media->save();

            return response()->json([
                'message' => 'فشل رفع التسجيل إلى Wasabi. تم الاحتفاظ بالملف المحلي.',
                'uploaded' => false,
            ], 500);
        }
    }

    private function authorizedTeacher(Request $request, Lesson $lesson): ?Teacher
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $teacher = Teacher::query()->where('user_id', $user->id)->first();
        if (! $teacher) {
            return null;
        }

        $allowedSubject = Specialization::query()
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $lesson->subject_id)
            ->exists();

        return $allowedSubject ? $teacher : null;
    }

    private function buildWhipUrl(string $streamName): string
    {
        $base = rtrim((string) config('services.srs.whip_base_url'), '/');
        $app = (string) config('services.srs.whip_app', 'live');
        $endpoint = "{$base}/";
        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return "{$endpoint}{$separator}app={$app}&stream={$streamName}";
    }

    private function buildPlaybackFlvUrl(string $streamName): string
    {
        $base = rtrim((string) config('services.srs.playback_flv_base_url'), '/');

        return "{$base}/{$streamName}.flv";
    }

    private function resolveRecordingFilePath(string $recordingsPath, string $streamName): ?string
    {
        if ($recordingsPath === '' || ! is_dir($recordingsPath)) {
            return null;
        }

        $exact = $recordingsPath . '/' . $streamName . '.mp4';
        if (is_file($exact)) {
            return $exact;
        }

        $matches = [];

        foreach (glob($recordingsPath . '/' . $streamName . '*.mp4') ?: [] as $filePath) {
            if (is_file($filePath)) {
                $matches[] = $filePath;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($recordingsPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if (! str_ends_with(strtolower($filename), '.mp4')) {
                continue;
            }

            if (! str_starts_with($filename, $streamName)) {
                continue;
            }

            $matches[] = $fileInfo->getPathname();
        }

        if (empty($matches)) {
            return null;
        }

        usort($matches, static function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });

        return $matches[0] ?? null;
    }
}
