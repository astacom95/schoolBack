<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PapersWork;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PapersController extends Controller
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

        $papers = PapersWork::query()
            ->where('level_id', $student->level_id)
            ->where('class_id', $student->class_id)
            ->latest('id')
            ->get();

        $subjects = Subject::with(['level', 'classroom'])
            ->whereIn('id', $papers->pluck('subject_id')->unique())
            ->get()
            ->keyBy('id');

        $data = $papers->map(function (PapersWork $paper) use ($subjects) {
            $subject = $subjects->get($paper->subject_id);
            $paperUrl = $paper->paper_path;
            if ($paperUrl && ! Str::startsWith($paperUrl, ['http://', 'https://'])) {
                $paperUrl = Storage::url($paperUrl);
            }

            return [
                'id' => $paper->id,
                'paper_path' => $paper->paper_path,
                'paper_url' => $paperUrl,
                'subject_id' => $paper->subject_id,
                'subject_name' => $subject?->name,
                'level_id' => $paper->level_id,
                'level_name' => $subject?->level?->name,
                'class_id' => $paper->class_id,
                'class_name' => $subject?->classroom?->name,
                'created_at' => optional($paper->created_at)->toDateString(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, PapersWork $paper): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        if ($paper->level_id !== $student->level_id || $paper->class_id !== $student->class_id) {
            return response()->json(['message' => 'Paper not found.'], 404);
        }

        $subject = Subject::with(['level', 'classroom'])->find($paper->subject_id);
        $paperUrl = $paper->paper_path;
        if ($paperUrl && ! Str::startsWith($paperUrl, ['http://', 'https://'])) {
            $paperUrl = Storage::url($paperUrl);
        }

        return response()->json([
            'data' => [
                'id' => $paper->id,
                'paper_path' => $paper->paper_path,
                'paper_url' => $paperUrl,
                'subject_id' => $paper->subject_id,
                'subject_name' => $subject?->name,
                'level_id' => $paper->level_id,
                'level_name' => $subject?->level?->name,
                'class_id' => $paper->class_id,
                'class_name' => $subject?->classroom?->name,
                'created_at' => optional($paper->created_at)->toDateString(),
            ],
        ]);
    }
}
