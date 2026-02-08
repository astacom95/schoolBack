<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['data' => null], 401);
        }

        $teacher = Teacher::with('user')->where('user_id', $user->id)->first();
        if (! $teacher) {
            return response()->json(['data' => null], 404);
        }

        $imageUrl = $teacher->personal_image_path;
        if ($imageUrl && ! Str::startsWith($imageUrl, ['http://', 'https://'])) {
            $imageUrl = url(Storage::url($imageUrl));
        }

        return response()->json([
            'data' => [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'email' => $teacher->user?->email,
                'personal_image_url' => $imageUrl,
            ],
        ]);
    }
}
