<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A link can exist before Google or Apple. Email stays unique, and Postgres allows many nulls.
        DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('link_only')->default(false);
            $table->char('link_token_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['link_only', 'link_token_hash']);
        });

        DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
    }
};
