<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            if (! Schema::hasColumn('lesson_media', 'webrtc_ingest_url')) {
                $table->string('webrtc_ingest_url')->nullable()->after('yt_scheduled_start_time');
            }
            if (! Schema::hasColumn('lesson_media', 'webrtc_stream_name')) {
                $table->string('webrtc_stream_name')->nullable()->after('webrtc_ingest_url');
            }
            if (! Schema::hasColumn('lesson_media', 'webrtc_token_enc')) {
                $table->text('webrtc_token_enc')->nullable()->after('webrtc_stream_name');
            }
        });

        // Ensure provider enum includes youtube.
        DB::statement(
            "ALTER TABLE lesson_media MODIFY provider ENUM('youtube','cloudflare','external','none') NOT NULL DEFAULT 'cloudflare'"
        );
    }

    public function down(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            $table->dropColumn([
                'webrtc_ingest_url',
                'webrtc_stream_name',
                'webrtc_token_enc',
            ]);
        });

        DB::statement(
            "ALTER TABLE lesson_media MODIFY provider ENUM('cloudflare','external','none') NOT NULL DEFAULT 'cloudflare'"
        );
    }
};
