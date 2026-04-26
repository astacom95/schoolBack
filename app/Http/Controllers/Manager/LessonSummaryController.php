<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;

class LessonSummaryController extends Controller
{
    public function index(): JsonResponse
    {
        $lessons = Lesson::query()
            ->leftJoin('subjects', 'subjects.id', '=', 'lessons.subject_id')
            ->leftJoin('levels', 'levels.id', '=', 'lessons.level_id')
            ->leftJoin('classes', 'classes.id', '=', 'lessons.class_id')
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.summary',
                'lessons.subject_id',
                'subjects.name as subject_name',
                'lessons.level_id',
                'levels.name as level_name',
                'lessons.class_id',
                'classes.name as class_name',
                'lessons.created_at'
            )
            ->selectRaw("(lessons.meet_link IS NOT NULL AND lessons.meet_link <> '') as has_media")
            ->groupBy(
                'lessons.id',
                'lessons.title',
                'lessons.summary',
                'lessons.subject_id',
                'subjects.name',
                'lessons.level_id',
                'levels.name',
                'lessons.class_id',
                'classes.name',
                'lessons.meet_link',
                'lessons.created_at'
            )
            ->latest('lessons.id')
            ->get()
            ->map(function (Lesson $lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'subject_id' => $lesson->subject_id,
                    'subject_name' => $lesson->subject_name,
                    'level_id' => $lesson->level_id,
                    'level_name' => $lesson->level_name,
                    'class_id' => $lesson->class_id,
                    'class_name' => $lesson->class_name,
                    'created_at' => $lesson->created_at,
                    'has_media' => (bool) $lesson->has_media,
                ];
            })
            ->values();

        return response()->json(['data' => $lessons]);
    }

    public function today(): JsonResponse
    {
        $today = now()->toDateString();

        $lessons = Lesson::query()
            ->leftJoin('subjects', 'subjects.id', '=', 'lessons.subject_id')
            ->leftJoin('quizzes', 'quizzes.lesson_id', '=', 'lessons.id')
            ->leftJoin('specializations', function ($join) {
                $join->on('specializations.subject_id', '=', 'lessons.subject_id');
            })
            ->leftJoin('teachers as specialized_teachers', 'specialized_teachers.id', '=', 'specializations.teacher_id')
            ->whereDate('lessons.created_at', $today)
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.summary',
                'lessons.meet_link',
                'lessons.created_at',
                'subjects.name as subject_name'
            )
            ->selectRaw('MAX(specialized_teachers.full_name) as teacher_name')
            ->selectRaw('MAX(quizzes.quiz_url) as quiz_url')
            ->groupBy(
                'lessons.id',
                'lessons.title',
                'lessons.summary',
                'lessons.meet_link',
                'lessons.created_at',
                'subjects.name'
            )
            ->latest('lessons.created_at')
            ->get()
            ->map(function (Lesson $lesson) {
                $meetLink = $lesson->meet_link;
                $quizUrl = $lesson->quiz_url;

                return [
                    'id' => $lesson->id,
                    'lesson_name' => $lesson->title,
                    'subject_name' => $lesson->subject_name,
                    'teacher_name' => $lesson->teacher_name,
                    'summary' => $lesson->summary,
                    'created_at' => optional($lesson->created_at)->toDateTimeString(),
                    'meet_link' => $meetLink,
                    'can_join_meet' => ! empty($meetLink),
                    'quiz_url' => $quizUrl,
                    'has_quiz' => ! empty($quizUrl),
                ];
            })
            ->values();

        return response()->json([
            'date' => $today,
            'data' => $lessons,
        ]);
    }
}
