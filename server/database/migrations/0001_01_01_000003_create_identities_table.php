<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('provider_user_id');
            $table->timestampsTz();

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE identities ADD CONSTRAINT identities_provider_check CHECK (provider IN ('google', 'apple'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('identities');
    }
};
