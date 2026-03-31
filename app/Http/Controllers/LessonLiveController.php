<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Specialization;
use App\Models\Teacher;
use Illuminate\Http\Request;

class LessonLiveController extends Controller
{
    public function startLive(Request $request, Lesson $lesson)
    {
        $teacher = $this->authorizedTeacher($request, $lesson);
        if (! $teacher) {
            return response()->json(['message' => 'غير مصرح بهذا الدرس.'], 403);
        }

        $data = $request->validate([
            'meet_link' => [
                'required',
                'string',
                'max:2048',
                'regex:/^https:\/\/meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}$/',
            ],
        ]);

        $lesson->meet_link = trim($data['meet_link']);
        $lesson->save();

        return response()->json([
            'lesson_id' => $lesson->id,
            'meet_link' => $lesson->meet_link,
            'has_media' => true,
            'is_recorded' => true,
        ]);
    }

    private function authorizedTeacher(Request $request, Lesson $lesson): ?Teacher
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $teacher = Teacher::query()->where('user_id', $user->id)->first();
        if (! $teacher) {
            return null;
        }

        $allowedSubject = Specialization::query()
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $lesson->subject_id)
            ->exists();

        return $allowedSubject ? $teacher : null;
    }
}
