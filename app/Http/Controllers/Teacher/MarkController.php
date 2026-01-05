<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Mark;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkController extends Controller
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

        $subjectId = (int) $request->query('subject_id', 0);
        $classId = (int) $request->query('class_id', 0);

        if ($subjectId <= 0 || $classId <= 0) {
            return response()->json(['data' => []]);
        }

        $subject = Subject::where('id', $subjectId)->first();
        if (! $subject) {
            return response()->json(['data' => []]);
        }

        if ($subject->class_id !== $classId) {
            return response()->json(['data' => []]);
        }

        $authorized = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $subject->id)
            ->where('class_id', $subject->class_id)
            ->where('level_id', $subject->level_id)
            ->exists();

        if (! $authorized) {
            return response()->json(['data' => []]);
        }

        $marks = Mark::where('subject_id', $subject->id)
            ->where('class_id', $subject->class_id)
            ->get()
            ->map(function (Mark $mark) {
                return [
                    'student_id' => $mark->student_id,
                    'degree' => $mark->degree,
                    'total_degree' => $mark->total_degree,
                ];
            })
            ->values();

        return response()->json(['data' => $marks]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'exists:students,id'],
            'marks.*.degree' => ['required', 'integer', 'min:0'],
        ]);

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $subject = Subject::where('id', $data['subject_id'])->first();
        if (! $subject) {
            return response()->json(['message' => 'المادة غير موجودة.'], 404);
        }

        if ($subject->class_id !== (int) $data['class_id']) {
            return response()->json(['message' => 'الصف غير مطابق للمادة المحددة.'], 422);
        }

        $authorized = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $subject->id)
            ->where('class_id', $subject->class_id)
            ->where('level_id', $subject->level_id)
            ->exists();

        if (! $authorized) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $studentIds = collect($data['marks'])->pluck('student_id')->unique()->values();
        $students = Student::whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        foreach ($studentIds as $studentId) {
            $student = $students->get($studentId);
            if (! $student || $student->class_id !== $subject->class_id) {
                return response()->json(['message' => 'الطلاب المحددون لا ينتمون للصف.'], 422);
            }
        }

        $totalDegree = (int) ($subject->total_degree ?? 0);
        if ($totalDegree > 0) {
            foreach ($data['marks'] as $markRow) {
                if ((int) $markRow['degree'] > $totalDegree) {
                    return response()->json(['message' => 'الدرجة تتجاوز الدرجة الكاملة.'], 422);
                }
            }
        }

        $saved = 0;
        foreach ($data['marks'] as $markRow) {
            Mark::updateOrCreate(
                [
                    'student_id' => $markRow['student_id'],
                    'subject_id' => $subject->id,
                ],
                [
                    'level_id' => $subject->level_id,
                    'class_id' => $subject->class_id,
                    'degree' => (int) $markRow['degree'],
                    'total_degree' => $totalDegree,
                ]
            );
            $saved++;
        }

        return response()->json(['saved' => $saved]);
    }
}
