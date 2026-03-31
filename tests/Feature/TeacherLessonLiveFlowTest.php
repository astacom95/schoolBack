<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Specialization;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherLessonLiveFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_live_saves_google_meet_link_for_authorized_teacher(): void
    {
        [$user, $lesson] = $this->createAuthorizedTeacherAndLesson();

        Sanctum::actingAs($user);

        $meetLink = 'https://meet.google.com/ayc-obyo-ojq';
        $response = $this->postJson("/api/teacher/lessons/{$lesson->id}/start-live", [
            'meet_link' => $meetLink,
        ]);

        $response->assertOk()
            ->assertJsonPath('lesson_id', $lesson->id)
            ->assertJsonPath('meet_link', $meetLink)
            ->assertJsonPath('has_media', true)
            ->assertJsonPath('is_recorded', true);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'meet_link' => $meetLink,
        ]);
    }

    public function test_start_live_rejects_invalid_google_meet_link(): void
    {
        [$user, $lesson] = $this->createAuthorizedTeacherAndLesson();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/teacher/lessons/{$lesson->id}/start-live", [
            'meet_link' => 'https://example.com/not-meet',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['meet_link']);
    }

    public function test_start_live_rejects_teacher_without_subject_authorization(): void
    {
        [$authorizedUser, $lesson] = $this->createAuthorizedTeacherAndLesson();
        unset($authorizedUser);

        [$otherUser] = $this->createUnauthorizedTeacherForLesson($lesson);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/teacher/lessons/{$lesson->id}/start-live", [
            'meet_link' => 'https://meet.google.com/ayc-obyo-ojq',
        ]);

        $response->assertStatus(403);
    }

    private function createAuthorizedTeacherAndLesson(): array
    {
        $user = User::query()->create([
            'user_name' => 'teacher_' . uniqid(),
            'password' => bcrypt('secret123'),
            'phone_number' => '9715' . random_int(1000000, 9999999),
            'role' => 'Teacher',
            'email' => 'teacher_' . uniqid() . '@example.com',
        ]);

        $teacher = Teacher::query()->create([
            'user_id' => $user->id,
            'date_of_birth' => '1990-01-01',
            'full_name' => 'Teacher Name',
            'certificate_path' => 'cert.pdf',
            'cv_path' => 'cv.pdf',
            'country' => 'AE',
            'state' => 'Dubai',
            'city' => 'Dubai',
            'gender' => 'Male',
        ]);

        $level = Level::query()->create(['name' => 'Level 1']);
        $class = SchoolClass::query()->create([
            'name' => 'Class A',
            'level_id' => $level->id,
            'number_of_subjects' => 1,
        ]);

        $subject = Subject::query()->create([
            'name' => 'Math',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'total_lessons' => 10,
            'total_degree' => 100,
        ]);

        Specialization::query()->create([
            'teacher_id' => $teacher->id,
            'level_id' => $level->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $lesson = Lesson::query()->create([
            'title' => 'Live Lesson',
            'summary' => 'Summary',
            'level_id' => $level->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        return [$user, $lesson, $teacher, $subject];
    }

    private function createUnauthorizedTeacherForLesson(Lesson $lesson): array
    {
        $user = User::query()->create([
            'user_name' => 'teacher_' . uniqid(),
            'password' => bcrypt('secret123'),
            'phone_number' => '9715' . random_int(1000000, 9999999),
            'role' => 'Teacher',
            'email' => 'teacher_' . uniqid() . '@example.com',
        ]);

        $teacher = Teacher::query()->create([
            'user_id' => $user->id,
            'date_of_birth' => '1992-01-01',
            'full_name' => 'Other Teacher',
            'certificate_path' => 'cert.pdf',
            'cv_path' => 'cv.pdf',
            'country' => 'AE',
            'state' => 'Dubai',
            'city' => 'Dubai',
            'gender' => 'Male',
        ]);

        $otherSubject = Subject::query()->create([
            'name' => 'Science ' . uniqid(),
            'level_id' => $lesson->level_id,
            'class_id' => $lesson->class_id,
            'total_lessons' => 6,
            'total_degree' => 60,
        ]);

        Specialization::query()->create([
            'teacher_id' => $teacher->id,
            'level_id' => $lesson->level_id,
            'class_id' => $lesson->class_id,
            'subject_id' => $otherSubject->id,
        ]);

        return [$user, $teacher];
    }
}
