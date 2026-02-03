<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\MonthlyTest;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MonthlyTestController extends Controller
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

        $tests = MonthlyTest::query()
            ->where('level_id', $student->level_id)
            ->where('class_id', $student->class_id)
            ->latest('id')
            ->get();

        $subjects = Subject::with(['level', 'classroom'])
            ->whereIn('id', $tests->pluck('subject_id')->unique())
            ->get()
            ->keyBy('id');

        $data = $tests->map(function (MonthlyTest $test) use ($subjects) {
            $subject = $subjects->get($test->subject_id);
            $testUrl = $test->test_url;
            if ($testUrl && ! Str::startsWith($testUrl, ['http://', 'https://'])) {
                $testUrl = Storage::url($testUrl);
            }

            return [
                'id' => $test->id,
                'test_url' => $test->test_url,
                'test_url_display' => $testUrl,
                'subject_id' => $test->subject_id,
                'subject_name' => $subject?->name,
                'level_id' => $test->level_id,
                'level_name' => $subject?->level?->name,
                'class_id' => $test->class_id,
                'class_name' => $subject?->classroom?->name,
                'created_at' => optional($test->created_at)->toDateString(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, MonthlyTest $monthlyTest): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        if ($monthlyTest->level_id !== $student->level_id || $monthlyTest->class_id !== $student->class_id) {
            return response()->json(['message' => 'Monthly test not found.'], 404);
        }

        $subject = Subject::with(['level', 'classroom'])->find($monthlyTest->subject_id);
        $testUrl = $monthlyTest->test_url;
        if ($testUrl && ! Str::startsWith($testUrl, ['http://', 'https://'])) {
            $testUrl = Storage::url($testUrl);
        }

        return response()->json([
            'data' => [
                'id' => $monthlyTest->id,
                'test_url' => $monthlyTest->test_url,
                'test_url_display' => $testUrl,
                'subject_id' => $monthlyTest->subject_id,
                'subject_name' => $subject?->name,
                'level_id' => $monthlyTest->level_id,
                'level_name' => $subject?->level?->name,
                'class_id' => $monthlyTest->class_id,
                'class_name' => $subject?->classroom?->name,
                'created_at' => optional($monthlyTest->created_at)->toDateString(),
            ],
        ]);
    }

    public function store(): JsonResponse
    {
        return response()->json(['message' => 'غير مصرح.'], 403);
    }
}
