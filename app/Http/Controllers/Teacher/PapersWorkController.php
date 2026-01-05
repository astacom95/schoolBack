<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\PapersWork;
use App\Models\Specialization;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PapersWorkController extends Controller
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

        $query = PapersWork::query()->whereIn('subject_id', $subjectIds);

        if ($subjectId > 0) {
            $query->where('subject_id', $subjectId);
        }

        if ($classId > 0) {
            $query->where('class_id', $classId);
        }

        if ($levelId > 0) {
            $query->where('level_id', $levelId);
        }

        $papers = $query->latest('id')->get();

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
            'paper_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
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

        $path = $request->file('paper_file')->store('papers-work', 'public');
        $paper = PapersWork::create([
            'paper_path' => $path,
            'level_id' => $subject->level_id,
            'class_id' => $subject->class_id,
            'subject_id' => $subject->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $paper->id,
                'paper_path' => $paper->paper_path,
                'paper_url' => Storage::url($paper->paper_path),
                'subject_id' => $paper->subject_id,
                'level_id' => $paper->level_id,
                'class_id' => $paper->class_id,
                'created_at' => optional($paper->created_at)->toDateString(),
            ],
        ], 201);
    }
}
