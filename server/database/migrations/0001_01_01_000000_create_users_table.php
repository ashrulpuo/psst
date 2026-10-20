<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Chosen during onboarding after the first SSO sign-in, hence nullable.
            $table->string('username', 24)->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('avatar_url')->nullable();
            $table->string('role', 16)->default('user');
            // Lock screens are public: message text in push notifications is opt-in.
            $table->boolean('push_previews')->default(false);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('banned_at')->nullable();
            $table->string('ban_reason')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            // Admin two-factor (TOTP). Secret and recovery-code hashes are encrypted at rest.
            $table->text('two_factor_secret')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            // Last accepted 30-second step: a code can never be used twice.
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
            $table->timestampsTz();
        });

        // Case-insensitive uniqueness for handles and emails.
        DB::statement('ALTER TABLE users ALTER COLUMN username TYPE citext');
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user', 'admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_username_format_check CHECK (username ~ '^[a-z0-9_.]{3,24}$')");

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
