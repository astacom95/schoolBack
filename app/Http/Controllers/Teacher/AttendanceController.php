<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['data' => null]);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['data' => null]);
        }

        $subjects = Subject::query()
            ->whereIn('id', Specialization::where('teacher_id', $teacher->id)->pluck('subject_id'))
            ->get(['id', 'level_id', 'class_id']);

        if ($subjects->isEmpty()) {
            return response()->json([
                'data' => [
                    'present' => 0,
                    'absent' => 0,
                    'total_possible' => 0,
                    'present_percent' => 0,
                    'absent_percent' => 0,
                ],
            ]);
        }

        $subjectIds = $subjects->pluck('id');

        $recordedCounts = Lesson::query()
            ->whereIn('lessons.subject_id', $subjectIds)
            ->leftJoin('lesson_media', function ($join) {
                $join->on('lesson_media.lesson_id', '=', 'lessons.id')
                    ->where('lesson_media.is_active', true);
            })
            ->select('lessons.subject_id', DB::raw('COUNT(DISTINCT lesson_media.lesson_id) as recorded_count'))
            ->groupBy('lessons.subject_id')
            ->pluck('recorded_count', 'lessons.subject_id');

        $studentCounts = Student::query()
            ->whereIn('level_id', $subjects->pluck('level_id'))
            ->whereIn('class_id', $subjects->pluck('class_id'))
            ->select('level_id', 'class_id', DB::raw('COUNT(*) as student_count'))
            ->groupBy('level_id', 'class_id')
            ->get()
            ->mapWithKeys(function (Student $student) {
                return [($student->level_id . ':' . $student->class_id) => (int) $student->student_count];
            });

        $attendanceCounts = Attendance::query()
            ->whereIn('subject_id', $subjectIds)
            ->select('subject_id', DB::raw('SUM(attendance_count) as total_attendance'))
            ->groupBy('subject_id')
            ->pluck('total_attendance', 'subject_id');

        $totalPossible = 0;
        $present = 0;

        foreach ($subjects as $subject) {
            $recorded = (int) ($recordedCounts[$subject->id] ?? 0);
            $studentsCount = (int) ($studentCounts[$subject->level_id . ':' . $subject->class_id] ?? 0);
            $totalPossible += $recorded * $studentsCount;
            $present += (int) ($attendanceCounts[$subject->id] ?? 0);
        }

        $present = min($present, $totalPossible);
        $absent = max(0, $totalPossible - $present);
        $presentPercent = $totalPossible > 0 ? round(($present / $totalPossible) * 100) : 0;
        $absentPercent = $totalPossible > 0 ? round(($absent / $totalPossible) * 100) : 0;

        return response()->json([
            'data' => [
                'present' => $present,
                'absent' => $absent,
                'total_possible' => $totalPossible,
                'present_percent' => $presentPercent,
                'absent_percent' => $absentPercent,
            ],
        ]);
    }

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
        if ($subjectId <= 0) {
            return response()->json(['data' => []]);
        }

        $authorized = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->exists();

        if (! $authorized) {
            return response()->json(['data' => []]);
        }

        $subject = Subject::with(['level', 'classroom'])->find($subjectId);
        if (! $subject) {
            return response()->json(['data' => []]);
        }

        $queryClassId = (int) $request->query('class_id', 0);
        if ($queryClassId > 0 && $queryClassId !== (int) $subject->class_id) {
            return response()->json(['data' => []]);
        }

        $queryLevelId = (int) $request->query('level_id', 0);
        if ($queryLevelId > 0 && $queryLevelId !== (int) $subject->level_id) {
            return response()->json(['data' => []]);
        }

        $students = Student::query()
            ->where('level_id', $subject->level_id)
            ->where('class_id', $subject->class_id)
            ->orderBy('full_name')
            ->get();

        $attendanceCounts = Attendance::query()
            ->where('subject_id', $subjectId)
            ->where('level_id', $subject->level_id)
            ->where('class_id', $subject->class_id)
            ->pluck('attendance_count', 'student_id');

        $recordedCount = (int) (Lesson::query()
            ->where('lessons.subject_id', $subjectId)
            ->leftJoin('lesson_media', function ($join) {
                $join->on('lesson_media.lesson_id', '=', 'lessons.id')
                    ->where('lesson_media.is_active', true);
            })
            ->select(DB::raw('COUNT(DISTINCT lesson_media.lesson_id) as recorded_count'))
            ->value('recorded_count') ?? 0);

        $rows = $students->map(function (Student $student) use ($attendanceCounts, $recordedCount) {
            $attendance = (int) ($attendanceCounts[$student->id] ?? 0);
            $percent = $recordedCount > 0 ? round(($attendance / $recordedCount) * 100) : 0;

            return [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'attendance_count' => $attendance,
                'recorded_lessons' => $recordedCount,
                'attendance_percent' => $percent,
            ];
        })->values();

        return response()->json([
            'data' => [
                'subject' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'level_id' => $subject->level_id,
                    'class_id' => $subject->class_id,
                    'level' => $subject->level?->name,
                    'class' => $subject->classroom?->name,
                ],
                'recorded_lessons' => $recordedCount,
                'students' => $rows,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }
}
