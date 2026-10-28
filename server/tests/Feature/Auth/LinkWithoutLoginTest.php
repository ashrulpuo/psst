<?php

namespace Tests\Feature\Auth;

use App\Messaging\GuestKey;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeOidcProvider;
use Tests\TestCase;

class LinkWithoutLoginTest extends TestCase
{
    use RefreshDatabase;

    /** A browser request from the SPA, which is what starts a cookie session. */
    private function spa(): static
    {
        return $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_a_visitor_claims_a_username_and_can_read_inbox_but_not_reply(): void
    {
        $token = GuestKey::newToken();

        $this->spa()
            ->withHeader('X-Guest-Token', $token)
            ->postJson('/api/guest/link', ['username' => 'New_Link'])
            ->assertOk()
            ->assertJsonPath('data.username', 'new_link')
            ->assertJsonPath('data.can_reply', false);

        $linkUser = User::query()->where('username', 'new_link')->firstOrFail();
        $this->assertTrue($linkUser->link_only);
        $this->assertNull($linkUser->email);

        $this->flushHeaders();
        $this->flushSession();

        $sender = User::factory()->create(['username' => 'bob']);
        Sanctum::actingAs($sender);
        $this->postJson('/api/profiles/new_link/messages', ['body' => 'hello there'])->assertCreated();

        Sanctum::actingAs($linkUser);
        $inbox = $this->getJson('/api/inbox')->assertOk();
        $inbox->assertJsonPath('data.0.last_message.body', 'hello there');

        $this->postJson('/api/threads/'.$inbox->json('data.0.id').'/messages', ['body' => 'nope'])
            ->assertForbidden();
    }

    public function test_the_same_browser_opens_its_link_again(): void
    {
        $token = GuestKey::newToken();

        $this->spa()->withHeader('X-Guest-Token', $token)
            ->postJson('/api/guest/link', ['username' => 'kept_name'])
            ->assertOk();

        $this->flushSession();

        $this->spa()->withHeader('X-Guest-Token', $token)
            ->postJson('/api/guest/link/resume')
            ->assertOk()
            ->assertJsonPath('data.username', 'kept_name')
            ->assertJsonPath('data.can_reply', false);

        $this->assertSame(1, User::query()->where('username', 'kept_name')->count());
    }

    public function test_a_taken_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'taken_name']);

        $this->spa()->withHeader('X-Guest-Token', GuestKey::newToken())
            ->postJson('/api/guest/link', ['username' => 'TAKEN_name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_signing_in_keeps_the_username_and_unlocks_replies(): void
    {
        $token = GuestKey::newToken();

        $this->spa()->withHeader('X-Guest-Token', $token)
            ->postJson('/api/guest/link', ['username' => 'ada_link'])
            ->assertOk();

        $google = new FakeOidcProvider('google');

        $this->spa()->postJson('/api/auth/google', [
            'id_token' => $google->token(),
            'nonce' => FakeOidcProvider::NONCE,
        ])->assertOk()
            ->assertJsonPath('data.username', 'ada_link')
            ->assertJsonPath('data.can_reply', true);

        $this->assertSame(1, User::query()->count());
        $this->assertFalse(User::query()->firstOrFail()->link_only);
    }

    public function test_a_link_holder_still_sends_anonymously(): void
    {
        $owner = User::factory()->create(['username' => 'carol']);
        $token = GuestKey::newToken();

        $this->spa()->withHeader('X-Guest-Token', $token)
            ->postJson('/api/guest/link', ['username' => 'quiet_one'])
            ->assertOk();

        $this->withHeader('X-Guest-Token', $token)
            ->postJson('/api/profiles/carol/messages', ['body' => 'from the link'])
            ->assertCreated()
            ->assertJsonPath('data.sent', true);

        $thread = Thread::query()->where('receiver_id', $owner->id)->firstOrFail();
        $this->assertNull($thread->sender_id);
    }
}
