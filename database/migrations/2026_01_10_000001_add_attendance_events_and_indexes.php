<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'subject_id', 'level_id', 'class_id'],
                'attendance_student_subject_level_class_unique'
            );
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE attendance ALTER COLUMN attendance_count SET DEFAULT 0');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE attendance ALTER attendance_count SET DEFAULT 0');
        }

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'lesson_id'], 'attendance_events_student_lesson_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropUnique('attendance_student_subject_level_class_unique');
        });
    }
};
