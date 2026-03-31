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
}
