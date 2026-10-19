<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->foreignUuid('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['receiver_id', 'sender_id']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
