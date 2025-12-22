<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('summary');
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedBigInteger('primary_media_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lesson_media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->enum('provider', ['cloudflare', 'external', 'none'])->default('cloudflare');
            $table->enum('media_type', ['live', 'vod', 'uploaded']);
            $table->string('source_url')->nullable();
            $table->string('cf_live_input_id')->nullable();
            $table->string('cf_live_playback_id')->nullable();
            $table->enum('live_status', ['not_started', 'scheduled', 'live', 'ended'])->nullable();
            $table->dateTime('live_scheduled_at')->nullable();
            $table->dateTime('live_started_at')->nullable();
            $table->dateTime('live_ended_at')->nullable();
            $table->string('cf_vod_video_id')->nullable();
            $table->string('cf_vod_playback_id')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreign('primary_media_id')
                ->references('id')
                ->on('lesson_media')
                ->nullOnDelete();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('lesson_id')->unique()->constrained('lessons')->cascadeOnDelete();
            $table->string('quiz_url');
            $table->timestamps();
        });

        Schema::create('monthly_tests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('test_url');
            $table->timestamps();
        });

        Schema::create('papers_work', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('paper_path');
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('teacher_time_table', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('track_teachers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('attend');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_teachers');
        Schema::dropIfExists('teacher_time_table');
        Schema::dropIfExists('papers_work');
        Schema::dropIfExists('monthly_tests');
        Schema::dropIfExists('quizzes');

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['primary_media_id']);
        });

        Schema::dropIfExists('lesson_media');
        Schema::dropIfExists('lessons');
    }
};
