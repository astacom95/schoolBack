<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerAttendanceDailySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_zero_filled_30_day_series_when_no_attendance_events_exist(): void
    {
        CarbonImmutable::setTestNow('2026-03-02 10:00:00');

        $response = $this->getJson('/api/manager/attendance/daily-summary');

        $response->assertOk()
            ->assertJsonPath('data.range', '30d')
            ->assertJsonPath('data.total_students', 0);

        $series = $response->json('data.series');

        $this->assertCount(30, $series);
        $this->assertSame('2026-02-01', $series[0]['date']);
        $this->assertSame('2026-03-02', $series[29]['date']);
        $this->assertSame(0, $series[0]['attended']);
        $this->assertSame(0, $series[0]['absent']);
    }

    public function test_it_counts_unique_students_per_day_and_zero_fills_missing_days(): void
    {
        CarbonImmutable::setTestNow('2026-03-02 10:00:00');

        [$level, $class, $subject] = $this->createSchoolStructure();

        $studentOne = $this->createStudent($level->id, $class->id, 'student1@example.com');
        $studentTwo = $this->createStudent($level->id, $class->id, 'student2@example.com');
        $this->createStudent($level->id, $class->id, 'student3@example.com');

        $lessonOne = Lesson::create([
            'title' => 'Lesson 1',
            'summary' => 'Summary 1',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $lessonTwo = Lesson::create([
            'title' => 'Lesson 2',
            'summary' => 'Summary 2',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        AttendanceEvent::create([
            'student_id' => $studentOne->id,
            'lesson_id' => $lessonOne->id,
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'created_at' => '2026-03-01 08:00:00',
            'updated_at' => '2026-03-01 08:00:00',
        ]);

        AttendanceEvent::create([
            'student_id' => $studentOne->id,
            'lesson_id' => $lessonTwo->id,
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'created_at' => '2026-03-01 11:00:00',
            'updated_at' => '2026-03-01 11:00:00',
        ]);

        AttendanceEvent::create([
            'student_id' => $studentTwo->id,
            'lesson_id' => $lessonOne->id,
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'created_at' => '2026-03-01 09:00:00',
            'updated_at' => '2026-03-01 09:00:00',
        ]);

        $response = $this->getJson('/api/manager/attendance/daily-summary?range=7d');

        $response->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonPath('data.total_students', 3);

        $series = collect($response->json('data.series'))->keyBy('date');

        $this->assertCount(7, $series);
        $this->assertSame([
            'date' => '2026-03-01',
            'attended' => 2,
            'absent' => 1,
        ], $series['2026-03-01']);

        $this->assertSame([
            'date' => '2026-03-02',
            'attended' => 0,
            'absent' => 3,
        ], $series['2026-03-02']);
    }

    public function test_it_falls_back_to_30_days_for_invalid_ranges(): void
    {
        CarbonImmutable::setTestNow('2026-03-02 10:00:00');

        $response = $this->getJson('/api/manager/attendance/daily-summary?range=2d');

        $response->assertOk()
            ->assertJsonPath('data.range', '30d');

        $this->assertCount(30, $response->json('data.series'));
    }

    protected function createSchoolStructure(): array
    {
        $level = Level::create(['name' => 'Level 1']);
        $class = SchoolClass::create([
            'name' => 'Class A',
            'level_id' => $level->id,
            'number_of_subjects' => 1,
        ]);
        $subject = Subject::create([
            'name' => 'Math',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'total_lessons' => 10,
            'total_degree' => 100,
        ]);

        return [$level, $class, $subject];
    }

    protected function createStudent(int $levelId, int $classId, string $email): Student
    {
        $user = User::factory()->create(['email' => $email]);

        return Student::create([
            'user_id' => $user->id,
            'date_of_birth' => '2010-01-01',
            'full_name' => strstr($email, '@', true),
            'country' => 'Country',
            'state' => 'State',
            'city' => 'City',
            'gender' => 'Male',
            'level_id' => $levelId,
            'class_id' => $classId,
            'certificate_path' => 'certificate.pdf',
            'personal_image_path' => 'student.jpg',
            'guardian_name' => 'Guardian',
            'guardian_relationship' => 'Parent',
            'student_phone_number' => '123456789',
            'guardian_phone_number' => '987654321',
            'guardian_email' => $email,
            'guardian_address' => 'Address',
        ]);
    }
}
