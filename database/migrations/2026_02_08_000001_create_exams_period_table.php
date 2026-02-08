<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams_period', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('exam_name');
            $table->integer('exam_year');
            $table->date('exam_start_date');
            $table->date('exam_end_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams_period');
    }
};
