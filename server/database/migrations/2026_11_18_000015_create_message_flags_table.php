<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A keyword hit on an inbound message. Holds the matched terms only: the body stays encrypted on the message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->jsonb('terms');
            $table->timestampTz('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_flags');
    }
};
