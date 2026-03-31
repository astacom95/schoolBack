<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $lessons = Lesson::query()
            ->leftJoin('subjects', 'subjects.id', '=', 'lessons.subject_id')
            ->where('lessons.level_id', $student->level_id)
            ->where('lessons.class_id', $student->class_id)
            ->when($subjectId > 0, fn ($query) => $query->where('lessons.subject_id', $subjectId))
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.subject_id',
                'lessons.summary',
                'lessons.meet_link',
                'subjects.name as subject_name',
                'lessons.created_at'
            )
            ->orderByDesc('lessons.created_at')
            ->get()
            ->map(function ($lesson) {
                $meetLink = $lesson->meet_link;
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'subject_id' => $lesson->subject_id,
                    'subject_name' => $lesson->subject_name,
                    'created_at' => optional($lesson->created_at)->toDateString(),
                    'meet_link' => $meetLink,
                    'watch_url' => $meetLink,
                    'embed_url' => null,
                    'video_url' => null,
                    'playback_url' => null,
                    'is_live' => false,
                    'media_type' => null,
                    'has_media' => ! empty($meetLink),
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

        $meetLink = $lesson->meet_link;
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
                'meet_link' => $meetLink,
                'watch_url' => $meetLink,
                'embed_url' => null,
                'video_url' => null,
                'playback_url' => null,
                'is_live' => false,
                'media_type' => null,
                'has_media' => ! empty($meetLink),
                'quiz_url' => $quiz?->quiz_url,
                'quiz_url_display' => $quizUrl,
            ],
        ]);
    }
}
