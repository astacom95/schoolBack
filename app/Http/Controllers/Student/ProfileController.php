<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
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

        $student = Student::with('user')->where('user_id', $user->id)->first();
        if (! $student) {
            return response()->json(['data' => null], 404);
        }

        $imageUrl = $student->personal_image_path;
        if ($imageUrl && ! Str::startsWith($imageUrl, ['http://', 'https://'])) {
            $imageUrl = url(Storage::url($imageUrl));
        }

        return response()->json([
            'data' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'email' => $student->user?->email,
                'personal_image_url' => $imageUrl,
            ],
        ]);
    }
}
