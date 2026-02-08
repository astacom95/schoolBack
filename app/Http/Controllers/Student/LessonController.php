<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['data' => []]);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return response()->json(['data' => []]);
        }

        $subjectId = (int) $request->query('subject_id', 0);

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
            ->where('lessons.level_id', $student->level_id)
            ->where('lessons.class_id', $student->class_id)
            ->when($subjectId > 0, fn ($query) => $query->where('lessons.subject_id', $subjectId))
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.subject_id',
                'lessons.summary',
                'subjects.name as subject_name',
                'lessons.created_at',
                'lm.yt_video_id',
                'lm.status',
                'lm.id as media_id'
            )
            ->orderByDesc('lessons.created_at')
            ->get()
            ->map(function ($lesson) {
                $videoId = $lesson->yt_video_id;
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'subject_id' => $lesson->subject_id,
                    'subject_name' => $lesson->subject_name,
                    'created_at' => optional($lesson->created_at)->toDateString(),
                    'watch_url' => $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null,
                    'embed_url' => $videoId ? "https://www.youtube.com/embed/{$videoId}" : null,
                    'is_live' => $lesson->status === 'live',
                    'has_media' => (bool) $lesson->media_id,
                ];
            });

        return response()->json(['data' => $lessons]);
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        if ($lesson->level_id !== $student->level_id || $lesson->class_id !== $student->class_id) {
            return response()->json(['message' => 'Lesson not found.'], 404);
        }

        $media = LessonMedia::query()
            ->where('lesson_id', $lesson->id)
            ->where('provider', 'youtube')
            ->latest('id')
            ->first();

        $videoId = $media?->yt_video_id;
        $subjectName = $lesson->subject_id ? Subject::where('id', $lesson->subject_id)->value('name') : null;
        $quiz = Quiz::where('lesson_id', $lesson->id)->first();
        $quizUrl = $quiz?->quiz_url;
        if ($quizUrl && ! Str::startsWith($quizUrl, ['http://', 'https://'])) {
            $quizUrl = Storage::url($quizUrl);
        }

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'subject_id' => $lesson->subject_id,
                'subject_name' => $subjectName,
                'created_at' => optional($lesson->created_at)->toDateString(),
                'watch_url' => $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null,
                'embed_url' => $videoId ? "https://www.youtube.com/embed/{$videoId}" : null,
                'is_live' => $media?->status === 'live',
                'quiz_url' => $quiz?->quiz_url,
                'quiz_url_display' => $quizUrl,
            ],
        ]);
    }
}
