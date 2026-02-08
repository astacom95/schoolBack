<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->foreignId('exam_period_id')
                ->nullable()
                ->after('class_id')
                ->constrained('exams_period')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropForeign(['exam_period_id']);
            $table->dropColumn('exam_period_id');
        });
    }
};
