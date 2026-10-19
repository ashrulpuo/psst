<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        DB::statement('ALTER TABLE audit_logs ADD COLUMN ip inet');

        // Append-only: reject UPDATE/DELETE regardless of which DB role issues them.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_change
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_immutable()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_immutable()');
    }
};
