<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Specialization;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['data' => []]);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['data' => []]);
        }

        $subjectIds = Specialization::where('teacher_id', $teacher->id)
            ->pluck('subject_id')
            ->unique()
            ->values();

        $subjects = Subject::with(['level', 'classroom'])
            ->whereIn('id', $subjectIds)
            ->get()
            ->map(function (Subject $subject) {
                $bookThumbUrl = $subject->book_thumbnail ? Storage::url($subject->book_thumbnail) : null;
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'total_lessons' => $subject->total_lessons,
                    'total_degree' => $subject->total_degree,
                    'level_id' => $subject->level_id,
                    'class_id' => $subject->class_id,
                    'level' => $subject->level?->name,
                    'class' => $subject->classroom?->name,
                    'book_thumbnail' => $bookThumbUrl,
                ];
            })
            ->values();

        return response()->json(['data' => $subjects]);
    }

    public function show(Request $request, Subject $subject): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $authorized = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $subject->id)
            ->exists();

        if (! $authorized) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $subject->loadMissing(['level', 'classroom']);

        $bookThumbUrl = $subject->book_thumbnail ? Storage::url($subject->book_thumbnail) : null;
        $lessonsCount = Lesson::where('subject_id', $subject->id)->count();

        return response()->json([
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'total_lessons' => $subject->total_lessons,
                'total_degree' => $subject->total_degree,
                'level_id' => $subject->level_id,
                'class_id' => $subject->class_id,
                'level' => $subject->level?->name,
                'class' => $subject->classroom?->name,
                'book_thumbnail' => $bookThumbUrl,
                'lessons_count' => $lessonsCount,
            ],
        ]);
    }
}
