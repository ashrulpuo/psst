<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private function thread(User $receiver, User $sender, int $label = 1): Thread
    {
        return Thread::create([
            'receiver_id' => $receiver->id,
            'sender_id' => $sender->id,
            'anon_label' => $label,
        ]);
    }

    public function test_usernames_and_emails_are_case_insensitively_unique(): void
    {
        User::factory()->create(['username' => 'alice', 'email' => 'a@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['username' => 'ALICE', 'email' => 'b@example.com']);
    }

    public function test_username_format_is_enforced_by_the_database(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['username' => 'bad name!']);
    }

    public function test_role_must_be_known(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['role' => 'superuser']);
    }

    public function test_primary_keys_are_time_ordered_uuids(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-/', $first->id);
        $this->assertGreaterThan($first->id, $second->id);
    }

    public function test_a_user_cannot_message_themselves(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        $this->thread($user, $user);
    }

    public function test_one_thread_per_receiver_sender_pair(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $this->thread($receiver, $sender);

        $this->expectException(QueryException::class);
        $this->thread($receiver, $sender, 2);
    }

    public function test_message_body_is_encrypted_at_rest(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $message = Message::create([
            'thread_id' => $this->thread($receiver, $sender)->id,
            'from_receiver' => false,
            'body' => 'hello there',
        ]);

        $raw = DB::table('messages')->where('id', $message->id)->value('body');

        $this->assertStringNotContainsString('hello there', $raw);
        $this->assertSame('hello there', $message->fresh()->body);
    }

    public function test_audit_logs_are_append_only(): void
    {
        $log = AuditLog::create(['action' => 'sender.revealed']);

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
    }
}
