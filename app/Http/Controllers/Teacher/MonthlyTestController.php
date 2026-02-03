<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\MonthlyTest;
use App\Models\Specialization;
use App\Models\Subject;
use App\Models\Teacher;
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

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['data' => []]);
        }

        $subjectIds = Specialization::where('teacher_id', $teacher->id)
            ->pluck('subject_id')
            ->unique()
            ->values();

        if ($subjectIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $subjectId = (int) $request->query('subject_id', 0);
        $classId = (int) $request->query('class_id', 0);
        $levelId = (int) $request->query('level_id', 0);

        if ($subjectId > 0 && ! $subjectIds->contains($subjectId)) {
            return response()->json(['data' => []]);
        }

        $query = MonthlyTest::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('subject_id', $subjectIds);

        if ($subjectId > 0) {
            $query->where('subject_id', $subjectId);
        }

        if ($classId > 0) {
            $query->where('class_id', $classId);
        }

        if ($levelId > 0) {
            $query->where('level_id', $levelId);
        }

        $tests = $query->latest('id')->get();

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

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'level_id' => ['required', 'exists:levels,id'],
            'test_file' => ['nullable', 'file', 'max:20480'],
            'test_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $subject = Subject::where('id', $data['subject_id'])->first();
        if (! $subject) {
            return response()->json(['message' => 'المادة غير موجودة.'], 404);
        }

        if ($subject->class_id !== (int) $data['class_id'] || $subject->level_id !== (int) $data['level_id']) {
            return response()->json(['message' => 'بيانات المرحلة أو الصف غير مطابقة للمادة.'], 422);
        }

        $authorized = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $subject->id)
            ->where('class_id', $subject->class_id)
            ->where('level_id', $subject->level_id)
            ->exists();

        if (! $authorized) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $path = null;
        if ($request->hasFile('test_file')) {
            $path = $request->file('test_file')->store('monthly-tests', 'public');
        } elseif (! empty($data['test_url'])) {
            $path = $data['test_url'];
        } else {
            return response()->json(['message' => 'يرجى رفع ملف أو إدخال رابط صالح.'], 422);
        }
        $test = MonthlyTest::create([
            'subject_id' => $subject->id,
            'level_id' => $subject->level_id,
            'class_id' => $subject->class_id,
            'teacher_id' => $teacher->id,
            'test_url' => $path,
        ]);

        $testUrl = $test->test_url;
        if ($testUrl && ! Str::startsWith($testUrl, ['http://', 'https://'])) {
            $testUrl = Storage::url($testUrl);
        }

        return response()->json([
            'data' => [
                'id' => $test->id,
                'test_url' => $test->test_url,
                'test_url_display' => $testUrl,
                'subject_id' => $test->subject_id,
                'level_id' => $test->level_id,
                'class_id' => $test->class_id,
                'created_at' => optional($test->created_at)->toDateString(),
            ],
        ], 201);
    }

    public function show(Request $request, MonthlyTest $monthlyTest): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher || $monthlyTest->teacher_id !== $teacher->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
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

    public function update(Request $request, MonthlyTest $monthlyTest): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher || $monthlyTest->teacher_id !== $teacher->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $data = $request->validate([
            'test_file' => ['nullable', 'file', 'max:20480'],
            'test_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $path = null;
        if ($request->hasFile('test_file')) {
            $path = $request->file('test_file')->store('monthly-tests', 'public');
        } elseif (! empty($data['test_url'])) {
            $path = $data['test_url'];
        } else {
            return response()->json(['message' => 'يرجى رفع ملف أو إدخال رابط صالح.'], 422);
        }
        $monthlyTest->update([
            'test_url' => $path,
        ]);

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
                'level_id' => $monthlyTest->level_id,
                'class_id' => $monthlyTest->class_id,
                'created_at' => optional($monthlyTest->created_at)->toDateString(),
            ],
        ]);
    }

    public function destroy(Request $request, MonthlyTest $monthlyTest): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher || $monthlyTest->teacher_id !== $teacher->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $monthlyTest->delete();

        return response()->json(['message' => 'تم حذف الاختبار الشهري.']);
    }
}
