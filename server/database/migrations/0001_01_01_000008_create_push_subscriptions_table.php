<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->text('token');
            // sha256 of the token: web-push endpoints/FCM tokens are too long for a safe unique btree key.
            $table->char('token_hash', 64);
            $table->jsonb('keys')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->unique(['channel', 'token_hash']);
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE push_subscriptions ADD CONSTRAINT push_subscriptions_channel_check CHECK (channel IN ('webpush', 'fcm'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
