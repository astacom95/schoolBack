<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamPeriod;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultController extends Controller
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

        $class = SchoolClass::where('id', $student->class_id)->first();
        if (! $class) {
            return response()->json(['data' => []]);
        }

        $requiredSubjects = (int) ($class->number_of_subjects ?? 0);
        if ($requiredSubjects <= 0) {
            return response()->json(['data' => []]);
        }

        $marks = Mark::query()
            ->leftJoin('subjects', 'subjects.id', '=', 'marks.subject_id')
            ->where('marks.student_id', $student->id)
            ->where('marks.class_id', $student->class_id)
            ->whereNotNull('marks.exam_period_id')
            ->select(
                'marks.exam_period_id',
                'marks.subject_id',
                'marks.degree',
                'marks.total_degree',
                'subjects.name as subject_name'
            )
            ->orderBy('marks.exam_period_id')
            ->get();

        if ($marks->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $byExam = $marks->groupBy('exam_period_id');
        $eligibleExamIds = $byExam->filter(function ($items) use ($requiredSubjects) {
            return $items->pluck('subject_id')->unique()->count() >= $requiredSubjects;
        })->keys()->values();

        if ($eligibleExamIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $examPeriods = ExamPeriod::whereIn('id', $eligibleExamIds)
            ->get()
            ->keyBy('id');

        $data = $eligibleExamIds->map(function ($examId) use ($byExam, $examPeriods) {
            $period = $examPeriods->get($examId);
            $rows = $byExam->get($examId)->map(function ($row) {
                return [
                    'subject_id' => $row->subject_id,
                    'subject_name' => $row->subject_name,
                    'degree' => (int) $row->degree,
                    'total_degree' => (int) $row->total_degree,
                ];
            })->values();

            return [
                'exam_period' => [
                    'id' => $period?->id,
                    'exam_name' => $period?->exam_name,
                    'exam_year' => $period?->exam_year,
                    'exam_start_date' => $period?->exam_start_date,
                    'exam_end_date' => $period?->exam_end_date,
                ],
                'subjects' => $rows,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
