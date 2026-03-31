<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            if (! Schema::hasColumn('lessons', 'meet_link')) {
                $table->string('meet_link', 2048)->nullable()->after('summary');
            }
        });

        if (Schema::hasColumn('lessons', 'primary_media_id')) {
            Schema::table('lessons', function (Blueprint $table) {
                try {
                    $table->dropForeign(['primary_media_id']);
                } catch (\Throwable $e) {
                    // Ignore if the foreign key is already missing.
                }

                $table->dropColumn('primary_media_id');
            });
        }

        Schema::dropIfExists('lesson_media');
    }

    public function down(): void
    {
        if (! Schema::hasTable('lesson_media')) {
            Schema::create('lesson_media', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
                $table->enum('provider', ['cloudflare', 'external', 'none'])->default('cloudflare');
                $table->enum('media_type', ['live', 'vod', 'uploaded']);
                $table->string('source_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('lessons', function (Blueprint $table) {
            if (! Schema::hasColumn('lessons', 'primary_media_id')) {
                $table->unsignedBigInteger('primary_media_id')->nullable()->after('subject_id');
                $table->foreign('primary_media_id')
                    ->references('id')
                    ->on('lesson_media')
                    ->nullOnDelete();
            }

            if (Schema::hasColumn('lessons', 'meet_link')) {
                $table->dropColumn('meet_link');
            }
        });
    }
};
