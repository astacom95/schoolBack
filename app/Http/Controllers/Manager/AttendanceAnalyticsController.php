<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceAnalyticsController extends Controller
{
    public function dailySummary(Request $request): JsonResponse
    {
        $range = $request->query('range', '30d');
        $days = match ($range) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        if (! in_array($range, ['7d', '30d', '90d'], true)) {
            $range = '30d';
        }

        $endDate = CarbonImmutable::now()->startOfDay();
        $startDate = $endDate->subDays($days - 1);
        $totalStudents = Student::query()->count();

        $dailyAttendance = AttendanceEvent::query()
            ->selectRaw('DATE(created_at) as attendance_date')
            ->selectRaw('COUNT(DISTINCT student_id) as attended')
            ->whereBetween('created_at', [$startDate, $endDate->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('attendance_date')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    $row->attendance_date => (int) $row->attended,
                ];
            });

        $series = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $startDate->addDays($offset);
            $dateKey = $date->toDateString();
            $attended = min((int) ($dailyAttendance[$dateKey] ?? 0), $totalStudents);

            $series[] = [
                'date' => $dateKey,
                'attended' => $attended,
                'absent' => max($totalStudents - $attended, 0),
            ];
        }

        return response()->json([
            'data' => [
                'range' => $range,
                'total_students' => $totalStudents,
                'series' => $series,
            ],
        ]);
    }
}
