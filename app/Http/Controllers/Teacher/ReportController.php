<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'level_id' => ['required', 'exists:levels,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'student_subject_performance' => ['required', 'in:ضعيف,متوسط,جيد,ممتاز'],
            'homework_commitment' => ['required', 'in:ضعيف,متوسط,جيد,ممتاز'],
            'discipline_commitment' => ['required', 'in:ضعيف,متوسط,جيد,ممتاز'],
            'peer_relationship' => ['required', 'in:ضعيف,متوسط,جيد,ممتاز'],
            'self_confidence' => ['required', 'string', 'max:255'],
            'special_skills' => ['required', 'string', 'max:255'],
            'academic_progress' => ['required', 'in:ضعيف,متوسط,جيد,ممتاز'],
            'literacy_numeracy_skills' => ['required', 'in:ضعيف,متوسط,جيد,ممتاز'],
            'participation_interaction' => ['required', 'in:ضعيف,متوسط,جيد,ممتاز'],
            'follow_up_cases' => ['required', 'string', 'max:255'],
            'responsibility_ability' => ['required', 'string', 'max:255'],
            'absence_delay' => ['required', 'string', 'max:255'],
            'support_needs' => ['required', 'string', 'max:255'],
            'recommendations' => ['required', 'string', 'max:255'],
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
            return response()->json(['message' => 'الصف أو المرحلة غير مطابقة للمادة.'], 422);
        }

        $authorized = Specialization::where('teacher_id', $teacher->id)
            ->where('subject_id', $subject->id)
            ->where('class_id', $subject->class_id)
            ->where('level_id', $subject->level_id)
            ->exists();

        if (! $authorized) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $student = Student::where('id', $data['student_id'])
            ->where('class_id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->first();

        if (! $student) {
            return response()->json(['message' => 'الطالب غير مرتبط بالصف أو المرحلة.'], 422);
        }

        $report = StudentReport::create([
            'student_id' => $data['student_id'],
            'teacher_id' => $teacher->id,
            'level_id' => $data['level_id'],
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'student_subject_performance' => $data['student_subject_performance'],
            'homework_commitment' => $data['homework_commitment'],
            'discipline_commitment' => $data['discipline_commitment'],
            'peer_relationship' => $data['peer_relationship'],
            'self_confidence' => $data['self_confidence'],
            'special_skills' => $data['special_skills'],
            'academic_progress' => $data['academic_progress'],
            'literacy_numeracy_skills' => $data['literacy_numeracy_skills'],
            'participation_interaction' => $data['participation_interaction'],
            'follow_up_cases' => $data['follow_up_cases'],
            'responsibility_ability' => $data['responsibility_ability'],
            'absence_delay' => $data['absence_delay'],
            'support_needs' => $data['support_needs'],
            'recommendations' => $data['recommendations'],
        ]);

        return response()->json(['data' => ['id' => $report->id]], 201);
    }
}
