<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\Quiz;
use App\Models\Specialization;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['message' => 'المعلم غير موجود.'], 404);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'quiz_file' => ['nullable', 'file', 'max:20480'],
            'quiz_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $allowedSubject = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (! $allowedSubject) {
            return response()->json(['message' => 'غير مصرح بهذا الموضوع.'], 403);
        }

        $subject = Subject::findOrFail($data['subject_id']);

        $lesson = Lesson::query()->create([
            'title' => $data['title'],
            'summary' => $data['summary'],
            'subject_id' => $subject->id,
            'level_id' => $subject->level_id,
            'class_id' => $subject->class_id,
        ]);

        $quizPath = null;
        if ($request->hasFile('quiz_file')) {
            $quizPath = $request->file('quiz_file')->store('quizzes', 'public');
        } elseif (! empty($data['quiz_url'])) {
            $quizPath = $data['quiz_url'];
        }

        if ($quizPath) {
            Quiz::query()->create([
                'lesson_id' => $lesson->id,
                'quiz_url' => $quizPath,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
            ],
            'message' => 'تم إنشاء الدرس بنجاح.',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['data' => [], 'teacher' => null]);
        }

        $teacher = Teacher::with('user')->where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['data' => [], 'teacher' => null]);
        }

        $teacherImage = $teacher->personal_image_path;
        if ($teacherImage && ! Str::startsWith($teacherImage, ['http://', 'https://'])) {
            $teacherImage = Storage::url($teacherImage);
        }

        $specializations = Specialization::query()
            ->where('specializations.teacher_id', $teacher->id)
            ->leftJoin('subjects', 'subjects.id', '=', 'specializations.subject_id')
            ->leftJoin('levels', 'levels.id', '=', 'specializations.level_id')
            ->leftJoin('classes', 'classes.id', '=', 'specializations.class_id')
            ->select(
                'specializations.id',
                'subjects.name as subject_name',
                'levels.name as level_name',
                'classes.name as class_name'
            )
            ->orderBy('subjects.name')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'subject_name' => $row->subject_name,
                    'level_name' => $row->level_name,
                    'class_name' => $row->class_name,
                ];
            })
            ->values();

        $subjectIds = Specialization::where('teacher_id', $teacher->id)
            ->pluck('subject_id')
            ->unique()
            ->values();

        if ($subjectIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'teacher' => [
                    'name' => $teacher->full_name,
                    'email' => $teacher->user?->email,
                    'phone_number' => $teacher->user?->phone_number,
                    'personal_image_url' => $teacherImage,
                    'specializations' => $specializations,
                ],
            ]);
        }

        $recordedOnly = filter_var($request->query('recorded', false), FILTER_VALIDATE_BOOLEAN);
        $subjectId = (int) $request->query('subject_id', 0);

        if ($subjectId > 0 && ! $subjectIds->contains($subjectId)) {
            return response()->json([
                'data' => [],
                'teacher' => [
                    'name' => $teacher->full_name,
                    'email' => $teacher->user?->email,
                    'phone_number' => $teacher->user?->phone_number,
                    'personal_image_url' => $teacherImage,
                    'specializations' => $specializations,
                ],
            ]);
        }

        if ($recordedOnly) {
            $recordedLessons = Lesson::query()
                ->whereIn('lessons.subject_id', $subjectIds)
                ->when($subjectId > 0, fn ($query) => $query->where('lessons.subject_id', $subjectId))
                ->join('subjects', 'subjects.id', '=', 'lessons.subject_id')
                ->join('lesson_media', function ($join) {
                    $join->on('lesson_media.lesson_id', '=', 'lessons.id')
                        ->where('lesson_media.is_active', true)
                        ->whereIn('lesson_media.media_type', ['vod', 'uploaded']);
                })
                ->select(
                    'lessons.id',
                    'lessons.title',
                    'subjects.name as subject_name',
                    'lessons.created_at',
                    'lesson_media.thumbnail_url',
                    'lesson_media.source_url',
                    'lesson_media.cf_vod_playback_id',
                    'lesson_media.media_type'
                )
                ->orderByDesc('lessons.created_at')
                ->get()
                ->map(function ($lesson) {
                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'subject_name' => $lesson->subject_name,
                        'thumbnail_url' => $lesson->thumbnail_url ?? null,
                        'media_type' => $lesson->media_type ?? null,
                        'created_at' => optional($lesson->created_at)->toDateString(),
                    ];
                });

            return response()->json([
                'data' => $recordedLessons,
                'teacher' => [
                    'name' => $teacher->full_name,
                    'email' => $teacher->user?->email,
                    'phone_number' => $teacher->user?->phone_number,
                    'personal_image_url' => $teacherImage,
                    'specializations' => $specializations,
                ],
            ]);
        }

        $lessons = Lesson::query()
            ->whereIn('lessons.subject_id', $subjectIds)
            ->when($subjectId > 0, fn ($query) => $query->where('lessons.subject_id', $subjectId))
            ->leftJoin('subjects', 'subjects.id', '=', 'lessons.subject_id')
            ->leftJoin('lesson_media', function ($join) {
                $join->on('lesson_media.lesson_id', '=', 'lessons.id')
                    ->where('lesson_media.is_active', true);
            })
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.subject_id',
                'subjects.name as subject_name',
                'lessons.created_at'
            )
            ->selectRaw('MAX(lesson_media.id) as media_id')
            ->selectRaw('COUNT(lesson_media.id) > 0 as has_media')
            ->groupBy('lessons.id', 'lessons.title', 'lessons.subject_id', 'subjects.name', 'lessons.created_at')
            ->orderByDesc('lessons.created_at')
            ->get()
            ->map(function ($lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'subject_name' => $lesson->subject_name,
                    'media_id' => $lesson->media_id ? (int) $lesson->media_id : null,
                    'has_media' => (bool) $lesson->has_media,
                    'created_at' => optional($lesson->created_at)->toDateString(),
                ];
            });

        return response()->json([
            'data' => $lessons,
            'teacher' => [
                'name' => $teacher->full_name,
                'email' => $teacher->user?->email,
                'phone_number' => $teacher->user?->phone_number,
                'personal_image_url' => $teacherImage,
                'specializations' => $specializations,
            ],
        ]);
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['message' => 'المعلم غير موجود.'], 404);
        }

        $allowedSubject = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $lesson->subject_id)
            ->exists();

        if (! $allowedSubject) {
            return response()->json(['message' => 'غير مصرح بهذا الدرس.'], 403);
        }

        $lessonDetails = Lesson::query()
            ->leftJoin('subjects', 'subjects.id', '=', 'lessons.subject_id')
            ->leftJoin('levels', 'levels.id', '=', 'lessons.level_id')
            ->leftJoin('classes', 'classes.id', '=', 'lessons.class_id')
            ->where('lessons.id', $lesson->id)
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.summary',
                'lessons.subject_id',
                'lessons.level_id',
                'lessons.class_id',
                'lessons.primary_media_id',
                'lessons.created_at',
                'subjects.name as subject_name',
                'levels.name as level_name',
                'classes.name as class_name'
            )
            ->first();

        $media = null;
        if ($lesson->primary_media_id) {
            $media = LessonMedia::query()
                ->where('id', $lesson->primary_media_id)
                ->where('lesson_id', $lesson->id)
                ->where('is_active', true)
                ->first();
        }

        if (! $media) {
            $media = LessonMedia::query()
                ->where('lesson_id', $lesson->id)
                ->where('is_active', true)
                ->latest('id')
                ->first();
        }

        $videoId = $media?->yt_video_id;
        $watchUrl = $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null;
        $embedUrl = $videoId ? "https://www.youtube.com/embed/{$videoId}" : null;
        $videoUrl = $media?->source_url;

        if (! $embedUrl && $media?->cf_vod_playback_id) {
            $embedUrl = "https://iframe.videodelivery.net/{$media->cf_vod_playback_id}";
        }

        return response()->json([
            'data' => [
                'id' => $lessonDetails?->id ?? $lesson->id,
                'title' => $lessonDetails?->title ?? $lesson->title,
                'summary' => $lessonDetails?->summary ?? $lesson->summary,
                'subject_id' => $lessonDetails?->subject_id ?? $lesson->subject_id,
                'subject_name' => $lessonDetails?->subject_name,
                'level_id' => $lessonDetails?->level_id ?? $lesson->level_id,
                'level_name' => $lessonDetails?->level_name,
                'class_id' => $lessonDetails?->class_id ?? $lesson->class_id,
                'class_name' => $lessonDetails?->class_name,
                'created_at' => optional($lessonDetails?->created_at ?? $lesson->created_at)->toDateString(),
                'watch_url' => $watchUrl,
                'embed_url' => $embedUrl,
                'video_url' => $videoUrl,
                'media' => $media ? [
                    'id' => $media->id,
                    'provider' => $media->provider,
                    'media_type' => $media->media_type,
                    'status' => $media->status ?? null,
                    'thumbnail_url' => $media->thumbnail_url,
                    'duration_seconds' => $media->duration_seconds,
                    'source_url' => $media->source_url,
                    'cf_vod_playback_id' => $media->cf_vod_playback_id,
                    'yt_video_id' => $media->yt_video_id,
                ] : null,
            ],
        ]);
    }

    public function whip(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['message' => 'المعلم غير موجود.'], 404);
        }

        $allowedSubject = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $lesson->subject_id)
            ->exists();

        if (! $allowedSubject) {
            return response()->json(['message' => 'غير مصرح بهذا الدرس.'], 403);
        }

        $baseUrl = config('services.srs.whip_base_url', env('SRS_WHIP_BASE_URL', 'http://localhost:1985/rtc/v1/whip'));
        $app = env('SRS_WHIP_APP', 'live');
        $streamName = 'lesson-' . $lesson->id;
        $normalizedBaseUrl = rtrim($baseUrl, '/') . '/';
        $separator = str_contains($normalizedBaseUrl, '?') ? '&' : '?';
        $whipUrl = $normalizedBaseUrl . $separator . 'app=' . urlencode($app) . '&stream=' . urlencode($streamName);

        return response()->json([
            'lesson_id' => $lesson->id,
            'whipUrl' => $whipUrl,
        ]);
    }

    public function obs(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['message' => 'المعلم غير موجود.'], 404);
        }

        $allowedSubject = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $lesson->subject_id)
            ->exists();

        if (! $allowedSubject) {
            return response()->json(['message' => 'غير مصرح بهذا الدرس.'], 403);
        }

        $rtmpServerUrl = config('services.srs.rtmp_publish_url', env('SRS_RTMP_PUBLISH_URL', 'rtmp://localhost/live'));
        $streamKey = 'lesson-' . $lesson->id;
        $fullIngestUrl = rtrim((string) $rtmpServerUrl, '/') . '/' . $streamKey;

        return response()->json([
            'lesson_id' => $lesson->id,
            'rtmp_server_url' => $rtmpServerUrl,
            'stream_key' => $streamKey,
            'full_ingest_url' => $fullIngestUrl,
        ]);
    }
}
