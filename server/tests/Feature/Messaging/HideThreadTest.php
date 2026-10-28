<?php

namespace Tests\Feature\Messaging;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HideThreadTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = User::factory()->create(['username' => 'alice']);
        $this->sender = User::factory()->create(['username' => 'bob']);

        Sanctum::actingAs($this->sender);
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi'])->assertCreated();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inboxFor(User $user): array
    {
        Sanctum::actingAs($user);

        return $this->getJson('/api/inbox')->assertOk()->json('data');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sentFor(User $user): array
    {
        Sanctum::actingAs($user);

        return $this->getJson('/api/sent')->assertOk()->json('data');
    }

    public function test_hiding_removes_the_thread_from_only_my_list(): void
    {
        $thread = Thread::query()->sole();

        Sanctum::actingAs($this->receiver);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();

        $this->assertCount(0, $this->inboxFor($this->receiver));
        $this->assertCount(1, $this->sentFor($this->sender));
    }

    public function test_a_new_message_restores_a_hidden_thread(): void
    {
        $thread = Thread::query()->sole();

        Sanctum::actingAs($this->receiver);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();
        $this->assertCount(0, $this->inboxFor($this->receiver));

        Sanctum::actingAs($this->sender);
        $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'back again'])->assertCreated();

        $this->assertCount(1, $this->inboxFor($this->receiver));
    }

    public function test_unhiding_is_idempotent(): void
    {
        $thread = Thread::query()->sole();

        Sanctum::actingAs($this->receiver);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();
        $this->deleteJson("/api/threads/{$thread->id}/hide")->assertNoContent();
        $this->deleteJson("/api/threads/{$thread->id}/hide")->assertNoContent();

        $this->assertCount(1, $this->inboxFor($this->receiver));
    }

    public function test_hiding_twice_is_harmless(): void
    {
        $thread = Thread::query()->sole();

        Sanctum::actingAs($this->receiver);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();

        $this->assertCount(0, $this->inboxFor($this->receiver));
        $this->assertSame(1, \DB::table('thread_hides')->count());
    }

    public function test_a_stranger_cannot_touch_a_thread(): void
    {
        $thread = Thread::query()->sole();
        $stranger = User::factory()->create(['username' => 'eve']);

        Sanctum::actingAs($stranger);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNotFound();
    }
}
