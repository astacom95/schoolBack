<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function overall(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $subjectIds = Subject::query()
            ->where('level_id', $student->level_id)
            ->where('class_id', $student->class_id)
            ->pluck('id');

        if ($subjectIds->isEmpty()) {
            return response()->json([
                'data' => [
                    'average_score' => 0,
                    'average_percent' => 0,
                    'subject_count' => 0,
                ],
            ]);
        }

        $recordedCounts = Lesson::query()
            ->whereIn('lessons.subject_id', $subjectIds)
            ->leftJoin('lesson_media', function ($join) {
                $join->on('lesson_media.lesson_id', '=', 'lessons.id')
                    ->where('lesson_media.is_active', true);
            })
            ->select('lessons.subject_id', DB::raw('COUNT(DISTINCT lesson_media.lesson_id) as recorded_count'))
            ->groupBy('lessons.subject_id')
            ->pluck('recorded_count', 'lessons.subject_id');

        $attendanceCounts = Attendance::query()
            ->where('student_id', $student->id)
            ->where('level_id', $student->level_id)
            ->where('class_id', $student->class_id)
            ->whereIn('subject_id', $subjectIds)
            ->pluck('attendance_count', 'subject_id');

        $totalScore = 0;
        foreach ($subjectIds as $subjectId) {
            $recorded = (int) ($recordedCounts[$subjectId] ?? 0);
            $attendance = (int) ($attendanceCounts[$subjectId] ?? 0);
            $subjectScore = $recorded > 0 ? ($attendance / $recorded) * 7 : 0;
            $totalScore += $subjectScore;
        }

        $subjectCount = $subjectIds->count();
        $averageScore = $subjectCount > 0 ? $totalScore / $subjectCount : 0;
        $averagePercent = $averageScore > 0 ? ($averageScore / 7) * 100 : 0;

        return response()->json([
            'data' => [
                'average_score' => $averageScore,
                'average_percent' => $averagePercent,
                'subject_count' => $subjectCount,
            ],
        ]);
    }

    public function store(Request $request, Lesson $lesson): JsonResponse
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

        $result = DB::transaction(function () use ($lesson, $student) {
            $event = AttendanceEvent::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'subject_id' => $lesson->subject_id,
                    'level_id' => $lesson->level_id,
                    'class_id' => $lesson->class_id,
                ]
            );

            $attendance = Attendance::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'subject_id' => $lesson->subject_id,
                    'level_id' => $lesson->level_id,
                    'class_id' => $lesson->class_id,
                ],
                ['attendance_count' => 0]
            );

            if ($event->wasRecentlyCreated) {
                $attendance->increment('attendance_count');
            }

            return [
                'attendance_count' => $attendance->attendance_count,
                'event_created' => $event->wasRecentlyCreated,
            ];
        });

        return response()->json([
            'data' => [
                'attendance_count' => $result['attendance_count'],
                'event_created' => $result['event_created'],
            ],
        ]);
    }
}
