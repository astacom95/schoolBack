<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;

class LessonSummaryController extends Controller
{
    public function index(): JsonResponse
    {
        $lessons = Lesson::with(['subject', 'level', 'classroom'])
            ->latest('id')
            ->get()
            ->map(function (Lesson $lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'subject_id' => $lesson->subject_id,
                    'subject_name' => $lesson->subject?->name,
                    'level_id' => $lesson->level_id,
                    'level_name' => $lesson->level?->name,
                    'class_id' => $lesson->class_id,
                    'class_name' => $lesson->classroom?->name,
                    'created_at' => $lesson->created_at,
                ];
            })
            ->values();

        return response()->json(['data' => $lessons]);
    }
}
