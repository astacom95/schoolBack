<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ExamPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamPeriodController extends Controller
{
    public function index(): JsonResponse
    {
        $periods = ExamPeriod::query()
            ->latest('id')
            ->get()
            ->map(function (ExamPeriod $period) {
                return [
                    'id' => $period->id,
                    'exam_name' => $period->exam_name,
                    'exam_year' => $period->exam_year,
                    'exam_start_date' => $period->exam_start_date,
                    'exam_end_date' => $period->exam_end_date,
                    'created_at' => optional($period->created_at)->toDateString(),
                ];
            })
            ->values();

        return response()->json(['data' => $periods]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exam_name' => ['required', 'string', 'max:255'],
            'exam_year' => ['required', 'integer'],
            'exam_start_date' => ['required', 'date'],
            'exam_end_date' => ['required', 'date', 'after_or_equal:exam_start_date'],
        ]);

        $period = ExamPeriod::create($data);

        return response()->json([
            'data' => [
                'id' => $period->id,
                'exam_name' => $period->exam_name,
                'exam_year' => $period->exam_year,
                'exam_start_date' => $period->exam_start_date,
                'exam_end_date' => $period->exam_end_date,
            ],
        ], 201);
    }
}
