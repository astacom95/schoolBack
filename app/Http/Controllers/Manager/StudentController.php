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
    private function transformStudent(Student $student, ?float $paidAmount = null, ?float $totalFee = null): array
    {
        $resolvedPaidAmount = $paidAmount ?? (float) Payment::query()->where('student_id', $student->id)->sum('amount');
        $resolvedTotalFee = $totalFee ?? (float) Fee::query()->where('class_id', $student->class_id)->value('total_fee');

        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'active' => (bool) ($student->user->active ?? true),
            'user_name' => $student->user->user_name ?? null,
            'email' => $student->user->email ?? null,
            'phone_number' => $student->student_phone_number ?? $student->user->phone_number ?? null,
            'level_id' => $student->level_id,
            'level' => $student->level->name ?? null,
            'class_id' => $student->class_id,
            'class' => $student->classroom->name ?? null,
            'gender' => $student->gender,
            'guardian_name' => $student->guardian_name,
            'date_of_birth' => $student->date_of_birth,
            'created_at' => optional($student->created_at)->toDateString(),
            'country' => $student->country,
            'state' => $student->state,
            'city' => $student->city,
            'certificate_path' => $student->certificate_path ? Storage::url($student->certificate_path) : null,
            'personal_image_path' => $student->personal_image_path ? Storage::url($student->personal_image_path) : null,
            'paid_amount' => $resolvedPaidAmount,
            'total_fee' => $resolvedTotalFee,
            'remaining_amount' => max($resolvedTotalFee - $resolvedPaidAmount, 0),
        ];
    }

    public function index()
    {
        $students = Student::query()
            ->with(['user', 'level', 'classroom'])
            ->latest('id')
            ->take(200)
            ->get();

        $paymentSumsByStudent = Payment::query()
            ->selectRaw('student_id, SUM(amount) as total_paid')
            ->whereIn('student_id', $students->pluck('id'))
            ->groupBy('student_id')
            ->pluck('total_paid', 'student_id');

        $feesByClass = Fee::query()
            ->whereIn('class_id', $students->pluck('class_id')->filter()->unique())
            ->pluck('total_fee', 'class_id');

        $students = $students
            ->map(function (Student $student) use ($paymentSumsByStudent, $feesByClass) {
                $paidAmount = (float) ($paymentSumsByStudent[$student->id] ?? 0);
                $totalFee = (float) ($feesByClass[$student->class_id] ?? 0);

                return $this->transformStudent($student, $paidAmount, $totalFee);
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
                'active' => true,
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
            'data' => $this->transformStudent($student),
        ], 201);
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name,' . $student->user_id],
            'password' => ['nullable', 'string', 'min:6'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $student->user_id],
            'phone_number' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['nullable', 'date'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'personal_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        DB::transaction(function () use ($student, $data, $request) {
            $newPersonalImagePath = null;
            if ($request->hasFile('personal_image')) {
                $newPersonalImagePath = $request->file('personal_image')->store('students/personal', 'public');
            }

            if ($student->user) {
                $userPayload = [
                    'user_name' => $data['user_name'],
                    'email' => $data['email'] ?? null,
                    'phone_number' => $data['phone_number'],
                ];
                if (!empty($data['password'])) {
                    $userPayload['password'] = Hash::make($data['password']);
                }

                $student->user->update($userPayload);
            }

            $studentPayload = [
                'full_name' => $data['full_name'],
                'student_phone_number' => $data['phone_number'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'] ?? $student->date_of_birth,
                'guardian_name' => $data['guardian_name'],
                'country' => $data['country'] ?? '',
                'state' => $data['state'] ?? '',
                'city' => $data['city'] ?? '',
            ];

            if ($newPersonalImagePath) {
                if ($student->personal_image_path) {
                    Storage::disk('public')->delete($student->personal_image_path);
                }
                $studentPayload['personal_image_path'] = $newPersonalImagePath;
            }

            $student->update($studentPayload);
        });

        $student->load(['user', 'level', 'classroom']);

        return response()->json([
            'data' => $this->transformStudent($student),
        ]);
    }

    public function updateStatus(Request $request, Student $student)
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        if (! $student->user) {
            return response()->json(['message' => 'لا يمكن تحديث حالة طالب بدون حساب مستخدم.'], 422);
        }

        $student->user->update([
            'active' => (bool) $data['active'],
        ]);

        $student->load(['user', 'level', 'classroom']);

        return response()->json([
            'data' => $this->transformStudent($student),
            'message' => $data['active'] ? 'تم تفعيل الطالب بنجاح.' : 'تم تعطيل الطالب بنجاح.',
        ]);
    }

    public function updatePaymentAmount(Request $request, Student $student)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = (float) $data['amount'];

        DB::transaction(function () use ($student, $amount) {
            $payments = Payment::query()
                ->where('student_id', $student->id)
                ->orderBy('id')
                ->get();

            if ($payments->isEmpty()) {
                Payment::create([
                    'student_id' => $student->id,
                    'payment_method' => 'cash',
                    'amount' => $amount,
                    'transaction_uid' => (string) Str::uuid(),
                    'level_id' => $student->level_id,
                    'class_id' => $student->class_id,
                    'guardian_name' => $student->guardian_name,
                    'guardian_phone_number' => $student->guardian_phone_number,
                ]);
                return;
            }

            $primaryPayment = $payments->first();
            $primaryPayment->update([
                'amount' => $amount,
                'payment_method' => 'cash',
                'level_id' => $student->level_id,
                'class_id' => $student->class_id,
                'guardian_name' => $student->guardian_name,
                'guardian_phone_number' => $student->guardian_phone_number,
            ]);

            if ($payments->count() > 1) {
                $payments
                    ->slice(1)
                    ->each(fn (Payment $payment) => $payment->delete());
            }
        });

        $student->load(['user', 'level', 'classroom']);

        $totalFee = (float) Fee::query()->where('class_id', $student->class_id)->value('total_fee');

        return response()->json([
            'data' => $this->transformStudent($student, $amount, $totalFee),
            'message' => 'تم تحديث مبلغ الدفع بنجاح.',
        ]);
    }

    public function destroy(Student $student)
    {
        DB::transaction(function () use ($student) {
            if ($student->certificate_path) {
                Storage::disk('public')->delete($student->certificate_path);
            }

            if ($student->personal_image_path) {
                Storage::disk('public')->delete($student->personal_image_path);
            }

            if ($student->user) {
                $student->user->delete();
                return;
            }

            $student->delete();
        });

        return response()->json(['message' => 'تم حذف الطالب بنجاح.']);
    }
}
