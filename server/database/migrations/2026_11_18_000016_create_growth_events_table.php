<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregate growth counters: an event name and when it happened, nothing else. No user, guest token, IP or
 * message id, so a row cannot be linked back to a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_events');
    }
};
