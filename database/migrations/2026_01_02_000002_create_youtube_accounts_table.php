<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('youtube_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->nullable();
            $table->string('channel_title')->nullable();

            // Store securely (encrypted cast in model)
            $table->text('refresh_token');
            $table->timestamp('connected_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_accounts');
    }
};
