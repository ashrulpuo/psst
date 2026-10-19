<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // restrictOnDelete: removing a user goes through an explicit, audited action.
            $table->foreignUuid('receiver_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->restrictOnDelete();
            // Per-receiver counter shown to the receiver as "Anonymous #N".
            $table->unsignedInteger('anon_label');
            $table->timestampTz('last_message_at')->nullable();
            $table->unsignedInteger('receiver_unread_count')->default(0);
            $table->unsignedInteger('sender_unread_count')->default(0);
            $table->timestampTz('blocked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['receiver_id', 'sender_id']);
            $table->unique(['receiver_id', 'anon_label']);
            $table->index(['receiver_id', 'last_message_at']);
            $table->index(['sender_id', 'last_message_at']);
        });

        DB::statement('ALTER TABLE threads ADD CONSTRAINT threads_not_self_check CHECK (receiver_id <> sender_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
