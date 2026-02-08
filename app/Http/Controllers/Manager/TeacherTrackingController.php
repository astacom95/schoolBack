<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Specialization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeacherTrackingController extends Controller
{
    public function index(): JsonResponse
    {
        $subjectRecorded = Lesson::query()
            ->leftJoin('lesson_media', function ($join) {
                $join->on('lesson_media.lesson_id', '=', 'lessons.id')
                    ->where('lesson_media.is_active', true);
            })
            ->select('lessons.subject_id', DB::raw('COUNT(DISTINCT lesson_media.lesson_id) as recorded_count'))
            ->groupBy('lessons.subject_id')
            ->pluck('recorded_count', 'lessons.subject_id');

        $rows = Specialization::query()
            ->leftJoin('teachers', 'teachers.id', '=', 'specializations.teacher_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'specializations.subject_id')
            ->select(
                'teachers.id as teacher_id',
                'teachers.full_name as teacher_name',
                'teachers.personal_image_path as teacher_image_path',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.total_lessons as total_lessons'
            )
            ->orderBy('teachers.full_name')
            ->get()
            ->map(function ($row) use ($subjectRecorded) {
                $totalLessons = (int) ($row->total_lessons ?? 0);
                $recorded = (int) ($subjectRecorded[$row->subject_id] ?? 0);
                $percent = $totalLessons > 0 ? round(($recorded / $totalLessons) * 100) : 0;
                $imageUrl = $row->teacher_image_path;
                if ($imageUrl && ! Str::startsWith($imageUrl, ['http://', 'https://'])) {
                    $imageUrl = url(Storage::url($imageUrl));
                }

                return [
                    'teacher_id' => $row->teacher_id,
                    'teacher_name' => $row->teacher_name,
                    'teacher_image_url' => $imageUrl,
                    'subject_id' => $row->subject_id,
                    'subject_name' => $row->subject_name,
                    'total_lessons' => $totalLessons,
                    'recorded_lessons' => $recorded,
                    'percent' => $percent,
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }
}
