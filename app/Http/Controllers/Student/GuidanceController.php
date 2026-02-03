<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentGuidance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuidanceController extends Controller
{
    public function index(Request $request)
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
                    'created_at' => $entry->created_at,
                ];
            });

        return response()->json(['data' => $entries]);
    }
}
