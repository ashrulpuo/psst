<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous messages without an account (NGL-style). A guest is identified only by a random token held in their
 * browser (stored hashed) and an HMAC of their IP; neither is reversible. Signing in later "claims" the thread so
 * the receiver can reply: sign-in is for chatting, not for sending.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE threads ALTER COLUMN sender_id DROP NOT NULL');
        DB::statement('ALTER TABLE threads ADD COLUMN guest_key_hash char(64)');
        DB::statement('ALTER TABLE threads ADD COLUMN ip_hash char(64)');

        DB::statement('ALTER TABLE threads DROP CONSTRAINT threads_receiver_id_sender_id_unique');
        DB::statement('CREATE UNIQUE INDEX threads_receiver_sender_unique ON threads (receiver_id, sender_id) WHERE sender_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX threads_receiver_guest_unique ON threads (receiver_id, guest_key_hash) WHERE guest_key_hash IS NOT NULL');
        DB::statement('CREATE INDEX threads_receiver_ip_idx ON threads (receiver_id, ip_hash) WHERE ip_hash IS NOT NULL');

        // Every thread has exactly one kind of sender.
        DB::statement('ALTER TABLE threads ADD CONSTRAINT threads_sender_kind_check CHECK ((sender_id IS NOT NULL) <> (guest_key_hash IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM threads WHERE sender_id IS NULL');
        DB::statement('ALTER TABLE threads DROP CONSTRAINT threads_sender_kind_check');
        DB::statement('DROP INDEX threads_receiver_ip_idx');
        DB::statement('DROP INDEX threads_receiver_guest_unique');
        DB::statement('DROP INDEX threads_receiver_sender_unique');
        DB::statement('ALTER TABLE threads ADD CONSTRAINT threads_receiver_id_sender_id_unique UNIQUE (receiver_id, sender_id)');
        DB::statement('ALTER TABLE threads DROP COLUMN ip_hash');
        DB::statement('ALTER TABLE threads DROP COLUMN guest_key_hash');
        DB::statement('ALTER TABLE threads ALTER COLUMN sender_id SET NOT NULL');
    }
};
