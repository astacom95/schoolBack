<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with('user')
            ->latest('id')
            ->take(200)
            ->get()
            ->map(function (Teacher $teacher) {
                $personalImageUrl = $teacher->personal_image_path ? Storage::url($teacher->personal_image_path) : null;
                $certificateUrl = $teacher->certificate_path ? Storage::url($teacher->certificate_path) : null;
                $cvUrl = $teacher->cv_path ? Storage::url($teacher->cv_path) : null;

                return [
                    'id' => $teacher->id,
                    'full_name' => $teacher->full_name,
                    'email' => $teacher->user->email ?? null,
                    'phone_number' => $teacher->user->phone_number ?? null,
                    'gender' => $teacher->gender,
                    'date_of_birth' => $teacher->date_of_birth,
                    'country' => $teacher->country,
                    'state' => $teacher->state,
                    'city' => $teacher->city,
                    'certificate_path' => $certificateUrl,
                    'cv_path' => $cvUrl,
                    'personal_image_path' => $personalImageUrl,
                    'created_at' => optional($teacher->created_at)->toDateString(),
                ];
            });

        return response()->json(['data' => $teachers]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name'],
            'password' => ['required', 'string', 'min:6'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['required', 'date'],
            'country' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'personal_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'specializations' => ['required', 'array', 'min:1'],
            'specializations.*.level_id' => ['required', 'exists:levels,id'],
            'specializations.*.class_id' => ['required', 'exists:classes,id'],
            'specializations.*.subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $teacher = DB::transaction(function () use ($data) {
            $certificatePath = null;
            if (request()->hasFile('certificate_file')) {
                $certificatePath = request()->file('certificate_file')->store('teachers/certificates', 'public');
            }
            $cvPath = null;
            if (request()->hasFile('cv_file')) {
                $cvPath = request()->file('cv_file')->store('teachers/cv', 'public');
            }
            $personalImagePath = request()->file('personal_image')->store('teachers/personal', 'public');

            $user = User::create([
                'user_name' => $data['user_name'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'],
                'role' => 'Teacher',
                'email' => $data['email'] ?? null,
            ]);

            /** @var Teacher $teacher */
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'date_of_birth' => $data['date_of_birth'],
                'full_name' => $data['full_name'],
                'certificate_path' => $certificatePath ?? '',
                'cv_path' => $cvPath ?? '',
                'country' => $data['country'],
                'state' => $data['state'],
                'city' => $data['city'],
                'gender' => $data['gender'],
                'personal_image_path' => $personalImagePath,
            ]);

            foreach ($data['specializations'] as $spec) {
                // ensure subject aligns with class/level
                $subjectOk = Subject::where('id', $spec['subject_id'])
                    ->where('class_id', $spec['class_id'])
                    ->where('level_id', $spec['level_id'])
                    ->exists();
                if (!$subjectOk) {
                    throw new \RuntimeException('المادة لا تتطابق مع المستوى أو الفصل.');
                }
                $classOk = SchoolClass::where('id', $spec['class_id'])
                    ->where('level_id', $spec['level_id'])
                    ->exists();
                if (!$classOk) {
                    throw new \RuntimeException('الفصل لا يتطابق مع المستوى.');
                }
                $teacher->specializations()->create([
                    'level_id' => $spec['level_id'],
                    'class_id' => $spec['class_id'],
                    'subject_id' => $spec['subject_id'],
                ]);
            }

            return $teacher->load('user');
        });

        $personalImageUrl = $teacher->personal_image_path ? Storage::url($teacher->personal_image_path) : null;
        $certificateUrl = $teacher->certificate_path ? Storage::url($teacher->certificate_path) : null;
        $cvUrl = $teacher->cv_path ? Storage::url($teacher->cv_path) : null;

        return response()->json([
            'data' => [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'email' => $teacher->user->email ?? null,
                'phone_number' => $teacher->user->phone_number ?? null,
                'gender' => $teacher->gender,
                'date_of_birth' => $teacher->date_of_birth,
                'country' => $teacher->country,
                'state' => $teacher->state,
                'city' => $teacher->city,
                'certificate_path' => $certificateUrl,
                'cv_path' => $cvUrl,
                'personal_image_path' => $personalImageUrl,
                'created_at' => optional($teacher->created_at)->toDateString(),
            ],
        ], 201);
    }
}
