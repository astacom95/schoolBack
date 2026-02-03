<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\StudentGuidance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GuidanceController extends Controller
{
    public function index()
    {
        $entries = StudentGuidance::latest('id')
            ->get()
            ->map(function (StudentGuidance $entry) {
                $imageUrl = $entry->image_path ? Storage::url($entry->image_path) : null;
                $videoUrl = $entry->video_path ? Storage::url($entry->video_path) : null;

                return [
                    'id' => $entry->id,
                    'guidance' => $entry->guidance,
                    'image_path' => $imageUrl,
                    'video_path' => $videoUrl,
                    'level_id' => $entry->level_id,
                    'class_id' => $entry->class_id,
                    'level_name' => $entry->level?->name,
                    'class_name' => $entry->classroom?->name,
                    'created_at' => $entry->created_at,
                ];
            });

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'guidance' => ['required', 'string'],
            'image_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'video_path' => ['nullable', 'file', 'mimes:mp4,webm,ogg,mov', 'max:102400'],
        ]);

        $entry = DB::transaction(function () use ($data, $request) {
            if ($request->hasFile('image_path')) {
                $data['image_path'] = $request->file('image_path')->store('student-guidance/images', 'public');
            }
            if ($request->hasFile('video_path')) {
                $data['video_path'] = $request->file('video_path')->store('student-guidance/videos', 'public');
            }

            return StudentGuidance::create($data);
        });

        $imageUrl = $entry->image_path ? Storage::url($entry->image_path) : null;
        $videoUrl = $entry->video_path ? Storage::url($entry->video_path) : null;

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'guidance' => $entry->guidance,
                'image_path' => $imageUrl,
                'video_path' => $videoUrl,
                'created_at' => $entry->created_at,
            ],
        ], 201);
    }

    public function destroy(StudentGuidance $guidance)
    {
        $guidance->delete();
        return response()->json(['message' => 'Guidance deleted']);
    }
}
