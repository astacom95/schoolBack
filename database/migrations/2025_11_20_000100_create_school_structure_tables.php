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
        Schema::create('levels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->integer('number_of_subjects');
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->integer('total_lessons');
            $table->integer('total_degree');
            $table->timestamps();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->date('date_of_birth');
            $table->string('full_name');
            $table->string('certificate_path');
            $table->string('cv_path');
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->enum('gender', ['Male', 'Female']);
            $table->timestamps();
        });

        Schema::create('managers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->date('date_of_birth');
            $table->string('full_name');
            $table->enum('gender', ['Male', 'Female']);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->date('date_of_birth');
            $table->string('full_name');
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->enum('gender', ['Male', 'Female']);
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('certificate_path');
            $table->string('personal_image_path');
            $table->string('guardian_name');
            $table->string('guardian_relationship');
            $table->string('student_phone_number')->nullable();
            $table->string('guardian_phone_number');
            $table->string('guardian_email')->nullable();
            $table->string('guardian_address');
            $table->timestamps();
        });

        Schema::create('specializations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specializations');
        Schema::dropIfExists('students');
        Schema::dropIfExists('managers');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('levels');
    }
};
