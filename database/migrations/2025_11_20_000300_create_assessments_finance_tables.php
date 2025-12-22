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
        Schema::create('attendance', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->integer('attendance_count');
            $table->timestamps();
        });

        Schema::create('fees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->unique()->constrained('classes')->cascadeOnDelete();
            $table->double('total_fee', 10, 2);
            $table->double('minimum_fee', 10, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('payment_method', ['visa', 'cash']);
            $table->double('amount', 10, 2);
            $table->string('transaction_uid')->unique();
            $table->foreignId('level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('guardian_name');
            $table->string('guardian_phone_number');
            $table->timestamps();
        });

        Schema::create('class_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('activity_name');
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('student_guidance', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('guidance');
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->foreignId('level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->integer('degree');
            $table->integer('total_degree');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marks');
        Schema::dropIfExists('student_guidance');
        Schema::dropIfExists('class_activities');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fees');
        Schema::dropIfExists('attendance');
    }
};
