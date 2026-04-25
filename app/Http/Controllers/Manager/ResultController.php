<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ExamPeriod;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
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

    public function show(Request $request, int $studentId): JsonResponse
    {
        $examPeriodId = (int) $request->query('exam_period_id', 0);
        $classId = (int) $request->query('class_id', 0);

        if ($examPeriodId <= 0 || $classId <= 0) {
            return response()->json($this->emptyDetailsResponse(null, null, null));
        }

        $period = ExamPeriod::query()->find($examPeriodId);
        $class = SchoolClass::query()->find($classId);
        $student = Student::query()->find($studentId);

        if (! $period || ! $class || ! $student) {
            return response()->json($this->emptyDetailsResponse($student, $period, $class));
        }

        if ((int) $student->class_id !== (int) $class->id) {
            return response()->json($this->emptyDetailsResponse($student, $period, $class));
        }

        $subjectRows = Mark::query()
            ->leftJoin('subjects', 'subjects.id', '=', 'marks.subject_id')
            ->where('marks.student_id', $student->id)
            ->where('marks.class_id', $class->id)
            ->where('marks.exam_period_id', $period->id)
            ->orderBy('subjects.name')
            ->select(
                'marks.subject_id',
                'subjects.name as subject_name',
                'marks.degree',
                'marks.total_degree'
            )
            ->get()
            ->map(function ($row) {
                $degree = (int) $row->degree;
                $totalDegree = (int) $row->total_degree;
                $percentage = $totalDegree > 0
                    ? (int) round(($degree / $totalDegree) * 100)
                    : 0;

                return [
                    'subject_id' => (int) $row->subject_id,
                    'subject_name' => $row->subject_name,
                    'degree' => $degree,
                    'total_degree' => $totalDegree,
                    'percentage' => $percentage,
                ];
            })
            ->values();

        if ($subjectRows->isEmpty()) {
            return response()->json($this->emptyDetailsResponse($student, $period, $class));
        }

        $earnedTotal = (int) $subjectRows->sum('degree');
        $maxTotal = (int) $subjectRows->sum('total_degree');
        $percentage = $maxTotal > 0
            ? (int) round(($earnedTotal / $maxTotal) * 100)
            : 0;

        return response()->json([
            'student' => [
                'id' => (int) $student->id,
                'full_name' => $student->full_name,
                'class_id' => (int) $class->id,
                'class_name' => $class->name,
                'exam_period_id' => (int) $period->id,
                'exam_name' => $period->exam_name,
                'exam_year' => $period->exam_year,
            ],
            'summary' => [
                'earned_total' => $earnedTotal,
                'max_total' => $maxTotal,
                'percentage' => $percentage,
                'subjects_count' => (int) $subjectRows->count(),
            ],
            'subjects' => $subjectRows,
        ]);
    }

    private function emptyDetailsResponse(?Student $student, ?ExamPeriod $period, ?SchoolClass $class): array
    {
        return [
            'student' => $student && $period && $class ? [
                'id' => (int) $student->id,
                'full_name' => $student->full_name,
                'class_id' => (int) $class->id,
                'class_name' => $class->name,
                'exam_period_id' => (int) $period->id,
                'exam_name' => $period->exam_name,
                'exam_year' => $period->exam_year,
            ] : null,
            'summary' => null,
            'subjects' => [],
        ];
    }
}
