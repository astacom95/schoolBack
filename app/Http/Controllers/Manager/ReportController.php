<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\StudentReport;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        $reports = StudentReport::with(['student', 'teacher', 'subject', 'level', 'classroom'])
            ->latest('id')
            ->get()
            ->map(function (StudentReport $report) {
                return [
                    'id' => $report->id,
                    'student_id' => $report->student_id,
                    'student_name' => $report->student?->full_name,
                    'teacher_id' => $report->teacher_id,
                    'teacher_name' => $report->teacher?->full_name,
                    'subject_name' => $report->subject?->name,
                    'level_name' => $report->level?->name,
                    'class_name' => $report->classroom?->name,
                    'student_subject_performance' => $report->student_subject_performance,
                    'homework_commitment' => $report->homework_commitment,
                    'discipline_commitment' => $report->discipline_commitment,
                    'peer_relationship' => $report->peer_relationship,
                    'self_confidence' => $report->self_confidence,
                    'special_skills' => $report->special_skills,
                    'academic_progress' => $report->academic_progress,
                    'literacy_numeracy_skills' => $report->literacy_numeracy_skills,
                    'participation_interaction' => $report->participation_interaction,
                    'follow_up_cases' => $report->follow_up_cases,
                    'responsibility_ability' => $report->responsibility_ability,
                    'absence_delay' => $report->absence_delay,
                    'support_needs' => $report->support_needs,
                    'recommendations' => $report->recommendations,
                    'created_at' => optional($report->created_at)->toDateString(),
                ];
            })
            ->values();

        return response()->json(['data' => $reports]);
    }
}
