<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Specialization;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherLessonLiveFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_live_creates_or_updates_live_media_for_srs(): void
    {
        [$user, $lesson] = $this->createAuthorizedTeacherAndLesson();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/teacher/lessons/{$lesson->id}/start-live");

        $response->assertOk()
            ->assertJsonStructure([
                'lesson_id',
                'media_id',
                'whip_url',
                'stream_name',
                'playback_flv_url',
            ]);

        $this->assertDatabaseHas('lesson_media', [
            'lesson_id' => $lesson->id,
            'provider' => 'external',
            'media_type' => 'live',
            'status' => 'live',
            'webrtc_stream_name' => 'lesson-' . $lesson->id,
            'is_active' => 1,
        ]);
    }

    public function test_end_live_uploads_recording_to_s3_and_marks_media_vod(): void
    {
        Storage::fake('s3');

        [$user, $lesson] = $this->createAuthorizedTeacherAndLesson();

        $media = LessonMedia::query()->create([
            'lesson_id' => $lesson->id,
            'provider' => 'external',
            'media_type' => 'live',
            'status' => 'live',
            'is_active' => true,
            'webrtc_stream_name' => 'lesson-' . $lesson->id,
            'source_url' => 'http://localhost:8080/live/lesson-' . $lesson->id . '.flv',
        ]);

        $recordingsPath = sys_get_temp_dir() . '/srs-recordings-' . uniqid('', true);
        @mkdir($recordingsPath, 0777, true);

        $recordingPath = $recordingsPath . '/lesson-' . $lesson->id . '.mp4';
        file_put_contents($recordingPath, 'fake-video');

        config()->set('services.srs.recordings_path', $recordingsPath);
        config()->set('services.srs.wasabi_object_prefix', 'lessons');
        config()->set('services.srs.wasabi_public_base_url', 'https://cdn.example.com');

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/teacher/lessons/{$lesson->id}/end-live");

        $response->assertOk()->assertJsonPath('uploaded', true);

        $media->refresh();
        $this->assertSame('vod', $media->media_type);
        $this->assertSame('ended', $media->status);
        $this->assertStringStartsWith('https://cdn.example.com/lessons/' . $lesson->id . '/', (string) $media->source_url);
        $this->assertFileDoesNotExist($recordingPath);
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

        return [$user, $lesson];
    }
}
