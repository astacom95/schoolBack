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
    private function transformTeacher(Teacher $teacher): array
    {
        return [
            'id' => $teacher->id,
            'full_name' => $teacher->full_name,
            'user_name' => $teacher->user->user_name ?? null,
            'email' => $teacher->user->email ?? null,
            'phone_number' => $teacher->user->phone_number ?? null,
            'gender' => $teacher->gender,
            'date_of_birth' => $teacher->date_of_birth,
            'country' => $teacher->country,
            'state' => $teacher->state,
            'city' => $teacher->city,
            'certificate_path' => $teacher->certificate_path ? Storage::url($teacher->certificate_path) : null,
            'cv_path' => $teacher->cv_path ? Storage::url($teacher->cv_path) : null,
            'personal_image_path' => $teacher->personal_image_path ? Storage::url($teacher->personal_image_path) : null,
            'created_at' => optional($teacher->created_at)->toDateString(),
            'specializations' => $teacher->specializations->map(fn ($spec) => [
                'id' => $spec->id,
                'level_id' => $spec->level_id,
                'level_name' => $spec->level->name ?? null,
                'class_id' => $spec->class_id,
                'class_name' => $spec->classroom->name ?? null,
                'subject_id' => $spec->subject_id,
                'subject_name' => $spec->subject->name ?? null,
            ])->values(),
        ];
    }

    public function index()
    {
        $teachers = Teacher::with(['user', 'specializations.level', 'specializations.classroom', 'specializations.subject'])
            ->latest('id')
            ->take(200)
            ->get()
            ->map(fn (Teacher $teacher) => $this->transformTeacher($teacher));

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

            return $teacher->load(['user', 'specializations.level', 'specializations.classroom', 'specializations.subject']);
        });

        return response()->json([
            'data' => $this->transformTeacher($teacher),
        ], 201);
    }

    public function update(Request $request, Teacher $teacher)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name,' . $teacher->user_id],
            'password' => ['nullable', 'string', 'min:6'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $teacher->user_id],
            'phone_number' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['required', 'date'],
            'country' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'personal_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'specializations' => ['required', 'array', 'min:1'],
            'specializations.*.level_id' => ['required', 'exists:levels,id'],
            'specializations.*.class_id' => ['required', 'exists:classes,id'],
            'specializations.*.subject_id' => ['required', 'exists:subjects,id'],
        ]);

        DB::transaction(function () use ($teacher, $data, $request) {
            $newPersonalImagePath = null;
            if ($request->hasFile('personal_image')) {
                $newPersonalImagePath = $request->file('personal_image')->store('teachers/personal', 'public');
            }

            if ($teacher->user) {
                $userPayload = [
                    'user_name' => $data['user_name'],
                    'email' => $data['email'] ?? null,
                    'phone_number' => $data['phone_number'],
                ];

                if (!empty($data['password'])) {
                    $userPayload['password'] = Hash::make($data['password']);
                }

                $teacher->user->update($userPayload);
            }

            $teacherPayload = [
                'full_name' => $data['full_name'],
                'date_of_birth' => $data['date_of_birth'],
                'country' => $data['country'],
                'state' => $data['state'],
                'city' => $data['city'],
                'gender' => $data['gender'],
            ];

            if ($newPersonalImagePath) {
                if ($teacher->personal_image_path) {
                    Storage::disk('public')->delete($teacher->personal_image_path);
                }
                $teacherPayload['personal_image_path'] = $newPersonalImagePath;
            }

            $teacher->update($teacherPayload);

            $teacher->specializations()->delete();

            foreach ($data['specializations'] as $spec) {
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
        });

        $teacher->load(['user', 'specializations.level', 'specializations.classroom', 'specializations.subject']);

        return response()->json([
            'data' => $this->transformTeacher($teacher),
        ]);
    }

    public function destroy(Teacher $teacher)
    {
        DB::transaction(function () use ($teacher) {
            if ($teacher->certificate_path) {
                Storage::disk('public')->delete($teacher->certificate_path);
            }

            if ($teacher->cv_path) {
                Storage::disk('public')->delete($teacher->cv_path);
            }

            if ($teacher->personal_image_path) {
                Storage::disk('public')->delete($teacher->personal_image_path);
            }

            if ($teacher->user) {
                $teacher->user->delete();
                return;
            }

            $teacher->delete();
        });

        return response()->json(['message' => 'تم حذف المعلم بنجاح.']);
    }
}
