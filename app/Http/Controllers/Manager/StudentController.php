<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function index()
    {
        $students = Student::query()
            ->with(['user', 'level', 'classroom'])
            ->latest('id')
            ->take(200)
            ->get()
            ->map(function (Student $student) {
                $personalImageUrl = $student->personal_image_path ? Storage::url($student->personal_image_path) : null;
                $certificateUrl = $student->certificate_path ? Storage::url($student->certificate_path) : null;
                return [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'email' => $student->user->email ?? null,
                    'phone_number' => $student->student_phone_number ?? $student->user->phone_number ?? null,
                    'level' => $student->level->name ?? null,
                    'class' => $student->classroom->name ?? null,
                    'gender' => $student->gender,
                    'guardian_name' => $student->guardian_name,
                    'date_of_birth' => $student->date_of_birth,
                    'created_at' => optional($student->created_at)->toDateString(),
                    'country' => $student->country,
                    'state' => $student->state,
                    'city' => $student->city,
                    'certificate_path' => $certificateUrl,
                    'personal_image_path' => $personalImageUrl,
                ];
            });

        return response()->json(['data' => $students]);
    }

    public function store(Request $request)
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
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        // Ensure class belongs to the level.
        $class = SchoolClass::where('id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->first();

        if (!$class) {
            return response()->json(['message' => 'الصف لا ينتمي إلى المستوى المحدد.'], 422);
        }

        $fee = Fee::where('class_id', $data['class_id'])->first();
        if (!$fee) {
            return response()->json(['message' => 'لا توجد رسوم محددة لهذا الفصل.'], 422);
        }

        if ($data['amount'] < $fee->minimum_fee) {
            return response()->json(['message' => 'المبلغ اقل من الحد الادنى'], 422);
        }

        $student = DB::transaction(function () use ($data) {
            $certificatePath = null;
            if (request()->hasFile('certificate_file')) {
                $certificatePath = request()->file('certificate_file')->store('students/certificates', 'public');
            }
            $personalImagePath = request()->file('personal_image')->store('students/personal', 'public');

            $user = User::create([
                'user_name' => $data['user_name'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'],
                'role' => 'Student',
                'email' => $data['email'] ?? null,
            ]);

            /** @var Student $student */
            $student = Student::create([
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

            Payment::create([
                'student_id' => $student->id,
                'payment_method' => 'cash',
                'amount' => $data['amount'],
                'transaction_uid' => (string) Str::uuid(),
                'level_id' => $student->level_id,
                'class_id' => $student->class_id,
                'guardian_name' => $student->guardian_name,
                'guardian_phone_number' => $student->guardian_phone_number,
            ]);

            return $student->load(['user', 'level', 'classroom']);
        });

        return response()->json([
            'data' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'email' => $student->user->email ?? null,
                'phone_number' => $student->student_phone_number ?? $student->user->phone_number ?? null,
                'level' => $student->level->name ?? null,
                'class' => $student->classroom->name ?? null,
                'gender' => $student->gender,
                'guardian_name' => $student->guardian_name,
                'date_of_birth' => $student->date_of_birth,
                'created_at' => optional($student->created_at)->toDateString(),
                'country' => $student->country,
                'state' => $student->state,
                'city' => $student->city,
                'certificate_path' => $student->certificate_path,
                'personal_image_path' => $student->personal_image_path,
            ],
        ], 201);
    }
}
