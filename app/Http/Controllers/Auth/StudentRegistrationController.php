<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentRegistrationController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name'],
            'password' => ['required', 'string', 'min:6'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            'student_phone_number' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['nullable', 'date'],
            'level_id' => ['required', 'exists:levels,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', 'string', 'max:255'],
            'guardian_phone_number' => ['required', 'string', 'max:255'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_address' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'personal_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $class = SchoolClass::query()
            ->where('id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->first();

        if (! $class) {
            return response()->json(['message' => 'الصف لا ينتمي إلى المستوى المحدد.'], 422);
        }

        DB::transaction(function () use ($data, $request) {
            $certificatePath = null;
            if ($request->hasFile('certificate_file')) {
                $certificatePath = $request->file('certificate_file')->store('students/certificates', 'public');
            }
            $personalImagePath = $request->file('personal_image')->store('students/personal', 'public');

            $user = User::create([
                'user_name' => $data['user_name'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'],
                'role' => 'Student',
                'email' => $data['email'] ?? null,
                'active' => false,
            ]);

            Student::create([
                'user_id' => $user->id,
                'date_of_birth' => $data['date_of_birth'] ?? now()->toDateString(),
                'full_name' => $data['full_name'],
                'country' => $data['country'] ?? 'غير محدد',
                'state' => $data['state'] ?? 'غير محدد',
                'city' => $data['city'] ?? 'غير محدد',
                'gender' => $data['gender'],
                'level_id' => $data['level_id'],
                'class_id' => $data['class_id'],
                'certificate_path' => $certificatePath ?? '',
                'personal_image_path' => $personalImagePath,
                'guardian_name' => $data['guardian_name'],
                'guardian_relationship' => $data['guardian_relationship'] ?? 'Parent',
                'student_phone_number' => $data['student_phone_number'] ?? $data['phone_number'],
                'guardian_phone_number' => $data['guardian_phone_number'],
                'guardian_email' => $data['guardian_email'] ?? null,
                'guardian_address' => $data['guardian_address'] ?? 'غير محدد',
            ]);
        });

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح وهو بانتظار التفعيل من الإدارة.',
        ], 201);
    }
}
