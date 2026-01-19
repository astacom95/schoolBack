<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
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
