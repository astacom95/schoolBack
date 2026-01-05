<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Specialization;
use App\Models\Teacher;
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

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['data' => []]);
        }

        $subjectId = (int) $request->query('subject_id', 0);
        $query = TeacherTimeTable::query()->where('teacher_id', $teacher->id);

        if ($subjectId > 0) {
            $authorized = Specialization::where('teacher_id', $teacher->id)
                ->where('subject_id', $subjectId)
                ->exists();

            if (! $authorized) {
                return response()->json(['data' => []]);
            }

            $query->where('subject_id', $subjectId);
        }

        $entries = $query
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
                    'subject_name' => $entry->subject?->name,
                ];
            })
            ->values();

        return response()->json(['data' => $entries]);
    }
}
