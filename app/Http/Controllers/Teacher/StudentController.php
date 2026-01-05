<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
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

        $classId = (int) $request->query('class_id', 0);
        if ($classId <= 0) {
            return response()->json(['data' => []]);
        }

        $authorized = Specialization::where('teacher_id', $teacher->id)
            ->where('class_id', $classId)
            ->exists();

        if (! $authorized) {
            return response()->json(['data' => []]);
        }

        $students = Student::where('class_id', $classId)
            ->orderBy('full_name')
            ->get()
            ->map(function (Student $student) {
                return [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                ];
            })
            ->values();

        return response()->json(['data' => $students]);
    }
}
