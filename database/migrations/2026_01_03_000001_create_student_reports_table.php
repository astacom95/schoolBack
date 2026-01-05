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
        Schema::create('student_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->enum('student_subject_performance', ['ضعيف', 'متوسط', 'جيد', 'ممتاز']);
            $table->enum('homework_commitment', ['ضعيف', 'متوسط', 'جيد', 'ممتاز']);
            $table->enum('discipline_commitment', ['ضعيف', 'متوسط', 'جيد', 'ممتاز']);
            $table->enum('peer_relationship', ['ضعيف', 'متوسط', 'جيد', 'ممتاز']);
            $table->string('self_confidence');
            $table->string('special_skills');
            $table->enum('academic_progress', ['ضعيف', 'متوسط', 'جيد', 'ممتاز']);
            $table->enum('literacy_numeracy_skills', ['ضعيف', 'متوسط', 'جيد', 'ممتاز']);
            $table->enum('participation_interaction', ['ضعيف', 'متوسط', 'جيد', 'ممتاز']);
            $table->string('follow_up_cases');
            $table->string('responsibility_ability');
            $table->string('absence_delay');
            $table->string('support_needs');
            $table->string('recommendations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_reports');
    }
};
