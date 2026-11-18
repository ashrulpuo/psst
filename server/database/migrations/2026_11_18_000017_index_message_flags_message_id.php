<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres does not index foreign keys on its own: this keeps cascade deletes of messages and flag lookups by
 * message from scanning the whole table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_flags', function (Blueprint $table) {
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::table('message_flags', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
        });
    }
};
