<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentYear = (int) CarbonImmutable::now()->year;
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $yearExpression = "CAST(strftime('%Y', created_at) AS INTEGER)";
            $monthExpression = "CAST(strftime('%m', created_at) AS INTEGER)";
        } else {
            $yearExpression = 'YEAR(created_at)';
            $monthExpression = 'MONTH(created_at)';
        }

        $availableYears = Payment::query()
            ->selectRaw("$yearExpression as payment_year")
            ->whereNotNull('created_at')
            ->distinct()
            ->orderByDesc('payment_year')
            ->pluck('payment_year')
            ->map(fn ($year) => (int) $year)
            ->filter()
            ->values()
            ->all();

        $requestedYear = (int) $request->query('year', 0);
        $selectedYear = $requestedYear > 0
            ? $requestedYear
            : (in_array($currentYear, $availableYears, true) ? $currentYear : ($availableYears[0] ?? $currentYear));

        if (empty($availableYears)) {
            $availableYears = [$selectedYear];
        } elseif (! in_array($selectedYear, $availableYears, true)) {
            $availableYears[] = $selectedYear;
            rsort($availableYears);
        }

        $monthlyTotals = Payment::query()
            ->selectRaw("$monthExpression as payment_month")
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->whereYear('created_at', $selectedYear)
            ->groupBy('payment_month')
            ->orderBy('payment_month')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (int) $row->payment_month => (float) $row->total_amount,
                ];
            });

        $monthLabels = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];

        $series = collect(range(1, 12))
            ->map(function (int $month) use ($monthlyTotals, $monthLabels) {
                return [
                    'month' => $month,
                    'month_label' => $monthLabels[$month],
                    'total_amount' => (float) ($monthlyTotals[$month] ?? 0),
                ];
            })
            ->values();

        $yearTotal = (float) $series->sum('total_amount');
        $comparisonYear = collect($availableYears)
            ->first(fn (int $year) => $year < $selectedYear);

        $comparisonYearTotal = null;
        if ($comparisonYear) {
            $comparisonYearTotal = (float) Payment::query()
                ->whereYear('created_at', $comparisonYear)
                ->sum('amount');
        }

        return response()->json([
            'data' => [
                'selected_year' => $selectedYear,
                'available_years' => $availableYears,
                'year_total' => $yearTotal,
                'comparison_year' => $comparisonYear,
                'comparison_year_total' => $comparisonYearTotal,
                'series' => $series,
            ],
        ]);
    }
}
