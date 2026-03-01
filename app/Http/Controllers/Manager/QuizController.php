<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuizController extends Controller
{
    public function index(): JsonResponse
    {
        $quizzes = Quiz::with(['lesson.subject', 'lesson.level', 'lesson.classroom'])
            ->latest('id')
            ->get()
            ->map(function (Quiz $quiz) {
                $lesson = $quiz->lesson;
                $quizUrl = $quiz->quiz_url;
                if ($quizUrl && ! Str::startsWith($quizUrl, ['http://', 'https://'])) {
                    $quizUrl = url(Storage::url($quizUrl));
                }

                return [
                    'id' => $quiz->id,
                    'lesson_id' => $quiz->lesson_id,
                    'lesson_title' => $lesson?->title,
                    'subject_id' => $lesson?->subject_id,
                    'subject_name' => $lesson?->subject?->name,
                    'level_id' => $lesson?->level_id,
                    'level_name' => $lesson?->level?->name,
                    'class_id' => $lesson?->class_id,
                    'class_name' => $lesson?->classroom?->name,
                    'quiz_url' => $quiz->quiz_url,
                    'quiz_url_display' => $quizUrl,
                    'created_at' => $quiz->created_at,
                ];
            })
            ->values();

        return response()->json(['data' => $quizzes]);
    }
}
