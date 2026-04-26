<?php

namespace Tests\Feature;

use App\Models\Fee;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentRegistrationAndActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_registration_creates_inactive_student_without_payment(): void
    {
        Storage::fake('public');
        [$level, $class] = $this->createLevelAndClass();

        $response = $this->post('/api/auth/register/student', [
            'full_name' => 'Self Student',
            'user_name' => 'self.student',
            'password' => 'secret123',
            'phone_number' => '0500000000',
            'student_phone_number' => '0500000001',
            'email' => 'self@student.test',
            'gender' => 'Male',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'guardian_name' => 'Guardian Name',
            'guardian_phone_number' => '0599999999',
            'guardian_relationship' => 'Parent',
            'guardian_email' => 'guardian@test.com',
            'guardian_address' => 'Riyadh',
            'country' => 'Saudi Arabia',
            'state' => 'Riyadh',
            'city' => 'Riyadh',
            'date_of_birth' => '2010-01-01',
            'personal_image' => UploadedFile::fake()->image('student.jpg'),
            'certificate_file' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
        ]);

        $response->assertCreated();

        $user = User::query()->where('user_name', 'self.student')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->active);

        $student = Student::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($student);

        $this->assertSame(0, Payment::query()->where('student_id', $student->id)->count());
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'user_name' => 'inactive.student',
            'password' => Hash::make('secret123'),
            'phone_number' => '0500000000',
            'role' => 'Student',
            'email' => 'inactive@student.test',
            'active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'user_name' => 'inactive.student',
            'password' => 'secret123',
        ])
            ->assertStatus(403)
            ->assertJsonMissingPath('token');
    }

    public function test_manager_can_toggle_student_status(): void
    {
        [$level, $class] = $this->createLevelAndClass();

        $user = User::create([
            'user_name' => 'toggle.student',
            'password' => Hash::make('secret123'),
            'phone_number' => '0500000000',
            'role' => 'Student',
            'email' => 'toggle@student.test',
            'active' => false,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'date_of_birth' => '2011-05-02',
            'full_name' => 'Toggle Student',
            'country' => 'Saudi Arabia',
            'state' => 'Riyadh',
            'city' => 'Riyadh',
            'gender' => 'Male',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'certificate_path' => '',
            'personal_image_path' => 'students/personal/toggle.jpg',
            'guardian_name' => 'Guardian',
            'guardian_relationship' => 'Parent',
            'student_phone_number' => '0500000000',
            'guardian_phone_number' => '0599999999',
            'guardian_email' => null,
            'guardian_address' => 'Riyadh',
        ]);

        $this->patchJson("/api/manager/students/{$student->id}/status", ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->assertTrue((bool) $user->fresh()->active);

        $this->patchJson("/api/manager/students/{$student->id}/status", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->assertFalse((bool) $user->fresh()->active);
    }

    public function test_manager_student_creation_still_creates_payment_and_active_user(): void
    {
        Storage::fake('public');
        [$level, $class] = $this->createLevelAndClass();

        Fee::create([
            'class_id' => $class->id,
            'total_fee' => 1000,
            'minimum_fee' => 200,
        ]);

        $response = $this->post('/api/manager/students', [
            'full_name' => 'Manager Student',
            'user_name' => 'manager.student',
            'password' => 'secret123',
            'phone_number' => '0500000100',
            'student_phone_number' => '0500000101',
            'email' => 'manager@student.test',
            'gender' => 'Female',
            'date_of_birth' => '2010-04-04',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'guardian_name' => 'Guardian Name',
            'guardian_phone_number' => '0591111111',
            'guardian_relationship' => 'Parent',
            'guardian_email' => 'guardian2@test.com',
            'guardian_address' => 'Riyadh',
            'country' => 'Saudi Arabia',
            'state' => 'Riyadh',
            'city' => 'Riyadh',
            'amount' => 250,
            'personal_image' => UploadedFile::fake()->image('student2.jpg'),
            'certificate_file' => UploadedFile::fake()->create('certificate2.pdf', 120, 'application/pdf'),
        ]);

        $response->assertCreated();

        $user = User::query()->where('user_name', 'manager.student')->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->active);

        $student = Student::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($student);

        $this->assertSame(1, Payment::query()->where('student_id', $student->id)->count());
    }

    public function test_manager_can_update_student_payment_amount(): void
    {
        [$level, $class] = $this->createLevelAndClass();

        Fee::create([
            'class_id' => $class->id,
            'total_fee' => 1000,
            'minimum_fee' => 200,
        ]);

        $user = User::create([
            'user_name' => 'payment.update.student',
            'password' => Hash::make('secret123'),
            'phone_number' => '0500000000',
            'role' => 'Student',
            'email' => 'payment@student.test',
            'active' => true,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'date_of_birth' => '2011-05-02',
            'full_name' => 'Payment Student',
            'country' => 'Saudi Arabia',
            'state' => 'Riyadh',
            'city' => 'Riyadh',
            'gender' => 'Male',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'certificate_path' => '',
            'personal_image_path' => 'students/personal/payment.jpg',
            'guardian_name' => 'Guardian',
            'guardian_relationship' => 'Parent',
            'student_phone_number' => '0500000000',
            'guardian_phone_number' => '0599999999',
            'guardian_email' => null,
            'guardian_address' => 'Riyadh',
        ]);

        Payment::create([
            'student_id' => $student->id,
            'payment_method' => 'cash',
            'amount' => 300,
            'transaction_uid' => 'tx-test-payment-1',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'guardian_name' => $student->guardian_name,
            'guardian_phone_number' => $student->guardian_phone_number,
        ]);

        $this->patchJson("/api/manager/students/{$student->id}/payment", ['amount' => 450])
            ->assertOk()
            ->assertJsonPath('data.paid_amount', 450.0)
            ->assertJsonPath('data.remaining_amount', 550.0);

        $this->assertSame(1, Payment::query()->where('student_id', $student->id)->count());
        $this->assertSame(450.0, (float) Payment::query()->where('student_id', $student->id)->first()->amount);
    }

    private function createLevelAndClass(): array
    {
        $level = Level::create(['name' => 'Level One']);
        $class = SchoolClass::create([
            'name' => 'Class A',
            'level_id' => $level->id,
            'number_of_subjects' => 3,
        ]);

        return [$level, $class];
    }
}
