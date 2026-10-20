<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Identity;
use App\Models\Message;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['username' => 'alice']);
        $this->bob = User::factory()->create(['username' => 'bobby', 'email' => 'bob@example.com']);
    }

    private function converse(): Thread
    {
        Sanctum::actingAs($this->bob);
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi alice'])->assertCreated();

        $thread = Thread::query()->firstOrFail();
        Sanctum::actingAs($this->alice);
        $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'hi bob'])->assertCreated();

        return $thread;
    }

    public function test_deleting_a_sender_erases_their_data_and_conversations(): void
    {
        $thread = $this->converse();
        Identity::create(['user_id' => $this->bob->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);
        $this->bob->createToken('phone');
        $message = Message::query()->where('thread_id', $thread->id)->oldest('id')->firstOrFail();
        Report::create(['reporter_id' => $this->alice->id, 'message_id' => $message->id, 'reason' => 'spam']);

        Sanctum::actingAs($this->bob);
        $this->deleteJson('/api/me', ['confirmation' => 'BOBBY'])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $this->bob->id]);
        $this->assertDatabaseMissing('identities', ['user_id' => $this->bob->id]);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->bob->id)->count());
        $this->assertSame(0, Thread::query()->count());
        $this->assertSame(0, Message::query()->count());
        $this->assertSame(0, Report::query()->count());
        $this->assertDatabaseHas('users', ['id' => $this->alice->id]);

        $audit = AuditLog::query()->where('action', 'account.deleted')->firstOrFail();
        $this->assertSame($this->bob->id, $audit->subject_id);
        $this->assertNull($audit->actor_id);
        $this->assertStringNotContainsString('bob', json_encode($audit->toArray()));
    }

    public function test_deleting_a_receiver_removes_their_inbox_and_leaves_others_untouched(): void
    {
        $this->converse();
        $carol = User::factory()->create(['username' => 'carol']);
        Sanctum::actingAs($this->bob);
        $this->postJson('/api/profiles/carol/messages', ['body' => 'hi carol'])->assertCreated();

        Sanctum::actingAs($this->alice);
        $this->deleteJson('/api/me', ['confirmation' => 'alice'])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $this->alice->id]);
        $this->assertSame(1, Thread::query()->count());
        $this->assertSame($carol->id, Thread::query()->firstOrFail()->receiver_id);
    }

    public function test_the_confirmation_must_match_and_nothing_is_deleted_otherwise(): void
    {
        Sanctum::actingAs($this->bob);

        $this->deleteJson('/api/me', ['confirmation' => 'someone_else'])->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        $this->deleteJson('/api/me')->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $this->bob->id]);
    }

    public function test_a_user_without_a_username_confirms_with_their_email(): void
    {
        $fresh = User::factory()->create(['username' => null, 'email' => 'new@example.com']);
        Sanctum::actingAs($fresh);

        $this->deleteJson('/api/me', ['confirmation' => 'new@example.com'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $fresh->id]);
    }

    public function test_admins_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['username' => 'boss', 'role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/me', ['confirmation' => 'boss'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_it_requires_sign_in(): void
    {
        $this->deleteJson('/api/me', ['confirmation' => 'bobby'])->assertUnauthorized();
    }

    public function test_the_username_is_free_to_claim_again_afterwards(): void
    {
        Sanctum::actingAs($this->bob);
        $this->deleteJson('/api/me', ['confirmation' => 'bobby'])->assertNoContent();

        $this->assertNull(User::query()->where('username', 'bobby')->first());
    }
}
