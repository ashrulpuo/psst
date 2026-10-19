<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->string('status', 16)->default('open');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['reporter_id', 'message_id']);
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('open', 'reviewed', 'dismissed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
