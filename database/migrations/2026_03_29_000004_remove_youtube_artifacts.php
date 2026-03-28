<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('youtube_accounts');

        try {
            DB::statement('DROP INDEX lesson_media_yt_video_id_index ON lesson_media');
        } catch (\Throwable $e) {
            // Ignore when index does not exist.
        }

        try {
            DB::statement('DROP INDEX lesson_media_yt_broadcast_id_index ON lesson_media');
        } catch (\Throwable $e) {
            // Ignore when index does not exist.
        }

        Schema::table('lesson_media', function (Blueprint $table) {
            $dropColumns = [
                'yt_broadcast_id',
                'yt_stream_id',
                'yt_video_id',
                'yt_rtmps_url',
                'yt_stream_key_enc',
                'yt_privacy_status',
                'yt_scheduled_start_time',
            ];

            foreach ($dropColumns as $column) {
                if (Schema::hasColumn('lesson_media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Normalize existing rows so enum shrink does not fail on legacy values.
        DB::table('lesson_media')
            ->whereNotIn('provider', ['cloudflare', 'external', 'none'])
            ->update(['provider' => 'external']);

        DB::statement(
            "ALTER TABLE lesson_media MODIFY provider ENUM('cloudflare','external','none') NOT NULL DEFAULT 'cloudflare'"
        );
    }

    public function down(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            if (! Schema::hasColumn('lesson_media', 'yt_broadcast_id')) {
                $table->string('yt_broadcast_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('lesson_media', 'yt_stream_id')) {
                $table->string('yt_stream_id')->nullable()->after('yt_broadcast_id');
            }
            if (! Schema::hasColumn('lesson_media', 'yt_video_id')) {
                $table->string('yt_video_id')->nullable()->after('yt_stream_id');
            }
            if (! Schema::hasColumn('lesson_media', 'yt_rtmps_url')) {
                $table->string('yt_rtmps_url')->nullable()->after('yt_video_id');
            }
            if (! Schema::hasColumn('lesson_media', 'yt_stream_key_enc')) {
                $table->text('yt_stream_key_enc')->nullable()->after('yt_rtmps_url');
            }
            if (! Schema::hasColumn('lesson_media', 'yt_privacy_status')) {
                $table->enum('yt_privacy_status', ['unlisted', 'private', 'public'])
                    ->default('unlisted')
                    ->after('yt_stream_key_enc');
            }
            if (! Schema::hasColumn('lesson_media', 'yt_scheduled_start_time')) {
                $table->dateTime('yt_scheduled_start_time')->nullable()->after('yt_privacy_status');
            }
        });

        DB::statement(
            "ALTER TABLE lesson_media MODIFY provider ENUM('youtube','cloudflare','external','none') NOT NULL DEFAULT 'cloudflare'"
        );

        Schema::create('youtube_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->nullable();
            $table->string('channel_title')->nullable();
            $table->text('refresh_token');
            $table->timestamp('connected_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }
};
