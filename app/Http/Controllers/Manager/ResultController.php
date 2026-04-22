<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ExamPeriod;
use App\Models\Mark;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $examPeriodId = (int) $request->query('exam_period_id', 0);
        $classId = (int) $request->query('class_id', 0);

        if ($examPeriodId <= 0 || $classId <= 0) {
            return response()->json([
                'filters' => [
                    'exam_period' => null,
                    'class' => null,
                ],
                'data' => [],
            ]);
        }

        $period = ExamPeriod::query()->find($examPeriodId);
        $class = SchoolClass::query()->find($classId);

        if (! $period || ! $class) {
            return response()->json([
                'filters' => [
                    'exam_period' => $period ? [
                        'id' => $period->id,
                        'exam_name' => $period->exam_name,
                        'exam_year' => $period->exam_year,
                    ] : null,
                    'class' => $class ? [
                        'id' => $class->id,
                        'name' => $class->name,
                    ] : null,
                ],
                'data' => [],
            ]);
        }

        $requiredSubjects = (int) ($class->number_of_subjects ?? 0);
        if ($requiredSubjects <= 0) {
            return response()->json([
                'filters' => [
                    'exam_period' => [
                        'id' => $period->id,
                        'exam_name' => $period->exam_name,
                        'exam_year' => $period->exam_year,
                    ],
                    'class' => [
                        'id' => $class->id,
                        'name' => $class->name,
                    ],
                ],
                'data' => [],
            ]);
        }

        $rows = Mark::query()
            ->join('students', 'students.id', '=', 'marks.student_id')
            ->where('marks.exam_period_id', $period->id)
            ->where('marks.class_id', $class->id)
            ->groupBy('marks.student_id', 'students.full_name')
            ->havingRaw('COUNT(DISTINCT marks.subject_id) >= ?', [$requiredSubjects])
            ->orderBy('students.full_name')
            ->selectRaw('
                marks.student_id,
                students.full_name as student_name,
                SUM(marks.degree) as earned_total,
                SUM(marks.total_degree) as max_total,
                COUNT(DISTINCT marks.subject_id) as subjects_count
            ')
            ->get()
            ->map(function ($row) {
                $earnedTotal = (int) $row->earned_total;
                $maxTotal = (int) $row->max_total;
                $percentage = $maxTotal > 0
                    ? (int) round(($earnedTotal / $maxTotal) * 100)
                    : 0;

                return [
                    'student_id' => (int) $row->student_id,
                    'student_name' => $row->student_name,
                    'percentage' => $percentage,
                    'earned_total' => $earnedTotal,
                    'max_total' => $maxTotal,
                    'subjects_count' => (int) $row->subjects_count,
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'exam_period' => [
                    'id' => $period->id,
                    'exam_name' => $period->exam_name,
                    'exam_year' => $period->exam_year,
                ],
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                ],
            ],
            'data' => $rows,
        ]);
    }
}
