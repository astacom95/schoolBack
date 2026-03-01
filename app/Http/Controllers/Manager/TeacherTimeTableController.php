<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherTimeTable;
use Illuminate\Http\Request;

class TeacherTimeTableController extends Controller
{
    private function transformEntry(TeacherTimeTable $row): array
    {
        return [
            'id' => $row->id,
            'day' => $row->day,
            'start_time' => $row->start_time,
            'end_time' => $row->end_time,
            'level_id' => $row->level_id,
            'level_name' => $row->level->name ?? null,
            'class_id' => $row->class_id,
            'class_name' => $row->classroom->name ?? null,
            'subject_id' => $row->subject_id,
            'subject_name' => $row->subject->name ?? null,
            'teacher_id' => $row->teacher_id,
            'teacher_name' => $row->teacher->full_name ?? null,
            'teacher_email' => optional($row->teacher->user)->email,
        ];
    }

    public function index()
    {
        $entries = TeacherTimeTable::with(['level', 'classroom', 'subject', 'teacher.user'])
            ->orderBy('day')
            ->orderBy('start_time')
            ->get()
            ->map(fn (TeacherTimeTable $row) => $this->transformEntry($row));

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'day' => ['required', 'string', 'max:20'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'level_id' => ['required', 'exists:levels,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
        ]);

        // Ensure the class belongs to the level.
        $classOk = SchoolClass::where('id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->exists();
        if (!$classOk) {
            return response()->json(['message' => 'الفصل لا ينتمي إلى المستوى المحدد'], 422);
        }

        // Ensure the subject belongs to the class and level.
        $subjectOk = Subject::where('id', $data['subject_id'])
            ->where('class_id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->exists();
        if (!$subjectOk) {
            return response()->json(['message' => 'المادة لا تنتمي إلى الفصل أو المستوى المحدد'], 422);
        }

        $start = $data['start_time'];
        $end = $data['end_time'];
        $day = $data['day'];

        $classConflict = TeacherTimeTable::where('class_id', $data['class_id'])
            ->where('day', $day)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();

        if ($classConflict) {
            return response()->json(['message' => 'Class already has a teacher at this time on this day.'], 422);
        }

        $teacherConflict = TeacherTimeTable::where('teacher_id', $data['teacher_id'])
            ->where('day', $day)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();

        if ($teacherConflict) {
            return response()->json(['message' => 'Teacher is not available at this time on this day.'], 422);
        }

        $entry = TeacherTimeTable::create($data);
        $entry->load(['level', 'classroom', 'subject', 'teacher.user']);

        return response()->json([
            'data' => $this->transformEntry($entry),
        ], 201);
    }

    public function update(Request $request, TeacherTimeTable $teacherTimeTable)
    {
        $data = $request->validate([
            'day' => ['required', 'string', 'max:20'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'level_id' => ['required', 'exists:levels,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
        ]);

        $classOk = SchoolClass::where('id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->exists();
        if (!$classOk) {
            return response()->json(['message' => 'الفصل لا ينتمي إلى المستوى المحدد'], 422);
        }

        $subjectOk = Subject::where('id', $data['subject_id'])
            ->where('class_id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->exists();
        if (!$subjectOk) {
            return response()->json(['message' => 'المادة لا تنتمي إلى الفصل أو المستوى المحدد'], 422);
        }

        $start = $data['start_time'];
        $end = $data['end_time'];
        $day = $data['day'];

        $classConflict = TeacherTimeTable::where('class_id', $data['class_id'])
            ->where('day', $day)
            ->whereKeyNot($teacherTimeTable->id)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();

        if ($classConflict) {
            return response()->json(['message' => 'Class already has a teacher at this time on this day.'], 422);
        }

        $teacherConflict = TeacherTimeTable::where('teacher_id', $data['teacher_id'])
            ->where('day', $day)
            ->whereKeyNot($teacherTimeTable->id)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();

        if ($teacherConflict) {
            return response()->json(['message' => 'Teacher is not available at this time on this day.'], 422);
        }

        $teacherTimeTable->update($data);
        $teacherTimeTable->load(['level', 'classroom', 'subject', 'teacher.user']);

        return response()->json([
            'data' => $this->transformEntry($teacherTimeTable),
        ]);
    }
}
