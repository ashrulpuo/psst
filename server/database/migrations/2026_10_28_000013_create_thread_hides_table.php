<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A hide is per user and per thread: it clears a note out of one person's list and
 * nothing else. Any new message in the thread deletes the hide (see PostMessage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_hides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['thread_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_hides');
    }
};
