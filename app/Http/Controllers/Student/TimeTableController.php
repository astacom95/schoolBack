<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TeacherTimeTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeTableController extends Controller
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

        $entries = TeacherTimeTable::query()
            ->where('level_id', $student->level_id)
            ->where('class_id', $student->class_id)
            ->with('subject')
            ->orderBy('day')
            ->orderBy('start_time')
            ->get()
            ->map(function (TeacherTimeTable $entry) {
                return [
                    'id' => $entry->id,
                    'day' => $entry->day,
                    'start_time' => $entry->start_time,
                    'end_time' => $entry->end_time,
                    'subject_id' => $entry->subject_id,
                    'subject_name' => $entry->subject?->name ?? '—',
                ];
            })
            ->values();

        return response()->json(['data' => $entries]);
    }
}
