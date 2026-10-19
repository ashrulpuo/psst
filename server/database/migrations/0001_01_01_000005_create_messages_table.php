<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained()->cascadeOnDelete();
            $table->boolean('from_receiver');
            // Stored encrypted (Eloquent `encrypted` cast); the length cap is enforced in the app.
            $table->text('body');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // Keyset pagination for chat history: WHERE thread_id = ? AND id < ? ORDER BY id DESC.
            $table->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
