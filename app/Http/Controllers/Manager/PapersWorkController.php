<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\PapersWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PapersWorkController extends Controller
{
    public function index(): JsonResponse
    {
        $papers = PapersWork::with(['subject.level', 'subject.classroom'])
            ->latest('id')
            ->get()
            ->map(function (PapersWork $paper) {
                $paperUrl = $paper->paper_path;
                if ($paperUrl && ! Str::startsWith($paperUrl, ['http://', 'https://'])) {
                    $paperUrl = url(Storage::url($paperUrl));
                }

                return [
                    'id' => $paper->id,
                    'paper_path' => $paper->paper_path,
                    'paper_url' => $paperUrl,
                    'subject_id' => $paper->subject_id,
                    'subject_name' => $paper->subject?->name,
                    'level_id' => $paper->level_id,
                    'level_name' => $paper->subject?->level?->name,
                    'class_id' => $paper->class_id,
                    'class_name' => $paper->subject?->classroom?->name,
                    'created_at' => optional($paper->created_at)->toDateString(),
                ];
            })
            ->values();

        return response()->json(['data' => $papers]);
    }
}
