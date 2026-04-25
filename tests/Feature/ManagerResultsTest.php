<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\Level;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_empty_when_query_params_are_missing_or_invalid(): void
    {
        $this->getJson('/api/manager/results')
            ->assertOk()
            ->assertJsonPath('filters.exam_period', null)
            ->assertJsonPath('filters.class', null)
            ->assertJsonPath('data', []);

        $this->getJson('/api/manager/results?exam_period_id=999&class_id=999')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_it_returns_aggregated_percentage_for_selected_class_and_period(): void
    {
        [$level, $class, $math, $science] = $this->createSchoolStructure(2);
        $period = ExamPeriod::create([
            'exam_name' => 'Midterm',
            'exam_year' => 2026,
            'exam_start_date' => '2026-02-01',
            'exam_end_date' => '2026-02-10',
        ]);

        $studentA = $this->createStudent($level->id, $class->id, 'Alice');
        $studentB = $this->createStudent($level->id, $class->id, 'Bob');

        Mark::create([
            'student_id' => $studentA->id,
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 80,
            'total_degree' => 100,
        ]);
        Mark::create([
            'student_id' => $studentA->id,
            'subject_id' => $science->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 40,
            'total_degree' => 50,
        ]);

        Mark::create([
            'student_id' => $studentB->id,
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 90,
            'total_degree' => 100,
        ]);
        Mark::create([
            'student_id' => $studentB->id,
            'subject_id' => $science->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 45,
            'total_degree' => 50,
        ]);

        $response = $this->getJson("/api/manager/results?exam_period_id={$period->id}&class_id={$class->id}");
        $response->assertOk()
            ->assertJsonPath('filters.exam_period.id', $period->id)
            ->assertJsonPath('filters.class.id', $class->id);

        $rows = collect($response->json('data'))->keyBy('student_name');

        $this->assertCount(2, $rows);
        $this->assertSame(80, $rows['Alice']['percentage']);
        $this->assertSame(120, $rows['Alice']['earned_total']);
        $this->assertSame(150, $rows['Alice']['max_total']);
        $this->assertSame(90, $rows['Bob']['percentage']);
        $this->assertSame(135, $rows['Bob']['earned_total']);
        $this->assertSame(150, $rows['Bob']['max_total']);
    }

    public function test_it_excludes_students_with_incomplete_results(): void
    {
        [$level, $class, $math, $science] = $this->createSchoolStructure(2);
        $period = ExamPeriod::create([
            'exam_name' => 'Final',
            'exam_year' => 2026,
            'exam_start_date' => '2026-05-01',
            'exam_end_date' => '2026-05-10',
        ]);

        $completeStudent = $this->createStudent($level->id, $class->id, 'Complete Student');
        $incompleteStudent = $this->createStudent($level->id, $class->id, 'Incomplete Student');

        Mark::create([
            'student_id' => $completeStudent->id,
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 75,
            'total_degree' => 100,
        ]);
        Mark::create([
            'student_id' => $completeStudent->id,
            'subject_id' => $science->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 45,
            'total_degree' => 50,
        ]);

        Mark::create([
            'student_id' => $incompleteStudent->id,
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 60,
            'total_degree' => 100,
        ]);

        $rows = $this->getJson("/api/manager/results?exam_period_id={$period->id}&class_id={$class->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('Complete Student', $rows[0]['student_name']);
    }

    public function test_it_ignores_marks_from_other_classes_and_periods(): void
    {
        [$levelOne, $classOne, $mathOne] = $this->createSchoolStructure(1);
        [$levelTwo, $classTwo, $mathTwo] = $this->createSchoolStructure(1, 'Level 2', 'Class B', 'Math B');

        $periodOne = ExamPeriod::create([
            'exam_name' => 'Period 1',
            'exam_year' => 2026,
            'exam_start_date' => '2026-01-01',
            'exam_end_date' => '2026-01-05',
        ]);
        $periodTwo = ExamPeriod::create([
            'exam_name' => 'Period 2',
            'exam_year' => 2026,
            'exam_start_date' => '2026-02-01',
            'exam_end_date' => '2026-02-05',
        ]);

        $student = $this->createStudent($levelOne->id, $classOne->id, 'Target Student');
        $otherClassStudent = $this->createStudent($levelTwo->id, $classTwo->id, 'Other Class');

        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $mathOne->id,
            'level_id' => $levelOne->id,
            'class_id' => $classOne->id,
            'exam_period_id' => $periodOne->id,
            'degree' => 90,
            'total_degree' => 100,
        ]);

        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $mathOne->id,
            'level_id' => $levelOne->id,
            'class_id' => $classOne->id,
            'exam_period_id' => $periodTwo->id,
            'degree' => 10,
            'total_degree' => 100,
        ]);
        Mark::create([
            'student_id' => $otherClassStudent->id,
            'subject_id' => $mathTwo->id,
            'level_id' => $levelTwo->id,
            'class_id' => $classTwo->id,
            'exam_period_id' => $periodOne->id,
            'degree' => 20,
            'total_degree' => 100,
        ]);

        $rows = $this->getJson("/api/manager/results?exam_period_id={$periodOne->id}&class_id={$classOne->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('Target Student', $rows[0]['student_name']);
        $this->assertSame(90, $rows[0]['percentage']);
    }

    public function test_it_returns_zero_percentage_when_total_degree_is_zero(): void
    {
        [$level, $class, $math] = $this->createSchoolStructure(1);
        $period = ExamPeriod::create([
            'exam_name' => 'Special',
            'exam_year' => 2026,
            'exam_start_date' => '2026-03-01',
            'exam_end_date' => '2026-03-05',
        ]);
        $student = $this->createStudent($level->id, $class->id, 'Zero Total');

        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 10,
            'total_degree' => 0,
        ]);

        $rows = $this->getJson("/api/manager/results?exam_period_id={$period->id}&class_id={$class->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['percentage']);
        $this->assertSame(10, $rows[0]['earned_total']);
        $this->assertSame(0, $rows[0]['max_total']);
    }

    public function test_it_returns_empty_details_shape_when_detail_params_are_missing_or_invalid(): void
    {
        [$level, $class] = $this->createSchoolStructure(1);
        $student = $this->createStudent($level->id, $class->id, 'No Details');

        $this->getJson("/api/manager/results/{$student->id}")
            ->assertOk()
            ->assertJsonPath('student', null)
            ->assertJsonPath('summary', null)
            ->assertJsonPath('subjects', []);

        $this->getJson("/api/manager/results/999999?exam_period_id=999&class_id=999")
            ->assertOk()
            ->assertJsonPath('student', null)
            ->assertJsonPath('summary', null)
            ->assertJsonPath('subjects', []);
    }

    public function test_it_returns_full_student_subject_details_for_selected_class_and_period(): void
    {
        [$level, $class, $math, $science] = $this->createSchoolStructure(2);
        $period = ExamPeriod::create([
            'exam_name' => 'Detailed Exam',
            'exam_year' => 2026,
            'exam_start_date' => '2026-06-01',
            'exam_end_date' => '2026-06-10',
        ]);
        $student = $this->createStudent($level->id, $class->id, 'Detailed Student');

        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 80,
            'total_degree' => 100,
        ]);
        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $science->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 45,
            'total_degree' => 50,
        ]);

        $response = $this->getJson("/api/manager/results/{$student->id}?exam_period_id={$period->id}&class_id={$class->id}");
        $response->assertOk()
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('student.full_name', 'Detailed Student')
            ->assertJsonPath('student.class_id', $class->id)
            ->assertJsonPath('student.exam_period_id', $period->id)
            ->assertJsonPath('summary.earned_total', 125)
            ->assertJsonPath('summary.max_total', 150)
            ->assertJsonPath('summary.percentage', 83)
            ->assertJsonPath('summary.subjects_count', 2);

        $subjects = collect($response->json('subjects'))->keyBy('subject_name');
        $this->assertCount(2, $subjects);
        $this->assertSame(80, $subjects["Math"]['percentage']);
        $this->assertSame(90, $subjects["Math 2"]['percentage']);
    }

    public function test_it_ignores_detail_marks_from_other_periods_and_classes(): void
    {
        [$levelOne, $classOne, $mathOne] = $this->createSchoolStructure(1);
        [$levelTwo, $classTwo, $mathTwo] = $this->createSchoolStructure(1, 'Level 2', 'Class B', 'Math B');
        $periodOne = ExamPeriod::create([
            'exam_name' => 'P1',
            'exam_year' => 2026,
            'exam_start_date' => '2026-07-01',
            'exam_end_date' => '2026-07-05',
        ]);
        $periodTwo = ExamPeriod::create([
            'exam_name' => 'P2',
            'exam_year' => 2026,
            'exam_start_date' => '2026-08-01',
            'exam_end_date' => '2026-08-05',
        ]);
        $student = $this->createStudent($levelOne->id, $classOne->id, 'Scoped Student');
        $otherStudent = $this->createStudent($levelTwo->id, $classTwo->id, 'Other Scoped Student');

        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $mathOne->id,
            'level_id' => $levelOne->id,
            'class_id' => $classOne->id,
            'exam_period_id' => $periodOne->id,
            'degree' => 70,
            'total_degree' => 100,
        ]);
        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $mathOne->id,
            'level_id' => $levelOne->id,
            'class_id' => $classOne->id,
            'exam_period_id' => $periodTwo->id,
            'degree' => 10,
            'total_degree' => 100,
        ]);
        Mark::create([
            'student_id' => $otherStudent->id,
            'subject_id' => $mathTwo->id,
            'level_id' => $levelTwo->id,
            'class_id' => $classTwo->id,
            'exam_period_id' => $periodOne->id,
            'degree' => 90,
            'total_degree' => 100,
        ]);

        $response = $this->getJson("/api/manager/results/{$student->id}?exam_period_id={$periodOne->id}&class_id={$classOne->id}");
        $response->assertOk()
            ->assertJsonPath('summary.earned_total', 70)
            ->assertJsonPath('summary.max_total', 100)
            ->assertJsonPath('summary.percentage', 70)
            ->assertJsonCount(1, 'subjects');
    }

    public function test_it_handles_zero_total_degree_in_student_detail_subjects(): void
    {
        [$level, $class, $math] = $this->createSchoolStructure(1);
        $period = ExamPeriod::create([
            'exam_name' => 'Zero Detail',
            'exam_year' => 2026,
            'exam_start_date' => '2026-09-01',
            'exam_end_date' => '2026-09-05',
        ]);
        $student = $this->createStudent($level->id, $class->id, 'Zero Detail Student');

        Mark::create([
            'student_id' => $student->id,
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'exam_period_id' => $period->id,
            'degree' => 10,
            'total_degree' => 0,
        ]);

        $response = $this->getJson("/api/manager/results/{$student->id}?exam_period_id={$period->id}&class_id={$class->id}");
        $response->assertOk()
            ->assertJsonPath('summary.percentage', 0)
            ->assertJsonPath('subjects.0.percentage', 0);
    }

    protected function createSchoolStructure(
        int $requiredSubjects,
        string $levelName = 'Level 1',
        string $className = 'Class A',
        string $firstSubjectName = 'Math',
    ): array {
        $level = Level::create(['name' => $levelName]);
        $class = SchoolClass::create([
            'name' => $className,
            'level_id' => $level->id,
            'number_of_subjects' => $requiredSubjects,
        ]);

        $subjectOne = Subject::create([
            'name' => $firstSubjectName,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'total_lessons' => 20,
            'total_degree' => 100,
        ]);

        if ($requiredSubjects <= 1) {
            return [$level, $class, $subjectOne];
        }

        $subjectTwo = Subject::create([
            'name' => "{$firstSubjectName} 2",
            'level_id' => $level->id,
            'class_id' => $class->id,
            'total_lessons' => 20,
            'total_degree' => 50,
        ]);

        return [$level, $class, $subjectOne, $subjectTwo];
    }

    protected function createStudent(int $levelId, int $classId, string $fullName): Student
    {
        $userName = strtolower(str_replace(' ', '.', $fullName)) . '.' . uniqid();
        $user = User::create([
            'user_name' => $userName,
            'password' => bcrypt('password'),
            'phone_number' => '0500000000',
            'role' => 'Student',
            'email' => "{$userName}@example.com",
        ]);

        return Student::create([
            'user_id' => $user->id,
            'date_of_birth' => '2010-01-01',
            'full_name' => $fullName,
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
            'guardian_email' => "{$userName}@example.com",
            'guardian_address' => 'Address',
        ]);
    }
}
