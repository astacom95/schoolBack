<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            // Status for live lifecycle
            if (! Schema::hasColumn('lesson_media', 'status')) {
                $table->enum('status', ['not_started', 'scheduled', 'testing', 'live', 'ended', 'error'])
                    ->default('not_started')
                    ->after('media_type');
            }

            // YouTube identifiers
            $table->string('yt_broadcast_id')->nullable()->after('status');
            $table->string('yt_stream_id')->nullable()->after('yt_broadcast_id');
            $table->string('yt_video_id')->nullable()->after('yt_stream_id');

            // Optional: store ingestion URL (can be re-fetched)
            $table->string('yt_rtmps_url')->nullable()->after('yt_video_id');

            // Optional: encrypted stream key (or omit this column entirely)
            $table->text('yt_stream_key_enc')->nullable()->after('yt_rtmps_url');

            $table->enum('yt_privacy_status', ['unlisted', 'private', 'public'])
                ->default('unlisted')
                ->after('yt_stream_key_enc');

            $table->dateTime('yt_scheduled_start_time')->nullable()->after('yt_privacy_status');

            // Helpful timestamps for state
            if (! Schema::hasColumn('lesson_media', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('yt_scheduled_start_time');
            }
            if (! Schema::hasColumn('lesson_media', 'ended_at')) {
                $table->dateTime('ended_at')->nullable()->after('started_at');
            }

            // Indexes
            $table->index(['provider', 'media_type']);
            $table->index('yt_video_id');
            $table->index('yt_broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            $table->dropIndex(['provider', 'media_type']);
            $table->dropIndex(['yt_video_id']);
            $table->dropIndex(['yt_broadcast_id']);

            $table->dropColumn([
                'status',
                'yt_broadcast_id',
                'yt_stream_id',
                'yt_video_id',
                'yt_rtmps_url',
                'yt_stream_key_enc',
                'yt_privacy_status',
                'yt_scheduled_start_time',
                'started_at',
                'ended_at',
            ]);
        });
    }
};
