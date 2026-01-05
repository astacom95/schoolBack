<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $latestMedia = LessonMedia::query()
            ->select('lesson_id', DB::raw('MAX(id) as media_id'))
            ->where('provider', 'youtube')
            ->groupBy('lesson_id');

        $lessons = Lesson::query()
            ->leftJoin('subjects', 'subjects.id', '=', 'lessons.subject_id')
            ->leftJoinSub($latestMedia, 'latest_media', function ($join) {
                $join->on('latest_media.lesson_id', '=', 'lessons.id');
            })
            ->leftJoin('lesson_media as lm', 'lm.id', '=', 'latest_media.media_id')
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.summary',
                'subjects.name as subject_name',
                'lessons.created_at',
                'lm.yt_video_id',
                'lm.status'
            )
            ->orderByDesc('lessons.created_at')
            ->get()
            ->map(function ($lesson) {
                $videoId = $lesson->yt_video_id;
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'subject_name' => $lesson->subject_name,
                    'created_at' => optional($lesson->created_at)->toDateString(),
                    'watch_url' => $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null,
                    'embed_url' => $videoId ? "https://www.youtube.com/embed/{$videoId}" : null,
                    'is_live' => $lesson->status === 'live',
                ];
            });

        return response()->json(['data' => $lessons]);
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $media = LessonMedia::query()
            ->where('lesson_id', $lesson->id)
            ->where('provider', 'youtube')
            ->latest('id')
            ->first();

        $videoId = $media?->yt_video_id;

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'subject_id' => $lesson->subject_id,
                'created_at' => optional($lesson->created_at)->toDateString(),
                'watch_url' => $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null,
                'embed_url' => $videoId ? "https://www.youtube.com/embed/{$videoId}" : null,
                'is_live' => $media?->status === 'live',
            ],
        ]);
    }
}
