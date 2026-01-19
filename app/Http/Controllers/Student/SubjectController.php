<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Subject;
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

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return response()->json(['data' => []]);
        }

        $subjects = Subject::with(['level', 'classroom'])
            ->where('level_id', $student->level_id)
            ->where('class_id', $student->class_id)
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
}
