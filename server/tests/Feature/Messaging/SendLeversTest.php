<?php

namespace Tests\Feature\Messaging;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendLeversTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_per_ip_limit_follows_config(): void
    {
        User::factory()->create(['username' => 'alice']);
        config(['psst.rate_limits.guest_per_ip_minute' => 1]);

        $send = fn () => $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->postJson('/api/profiles/alice/messages', ['body' => 'hello']);

        $send()->assertCreated();
        $send()->assertStatus(429);
    }

    public function test_user_per_minute_limit_follows_config(): void
    {
        $bob = User::factory()->create();
        User::factory()->create(['username' => 'alice']);
        config(['psst.rate_limits.user_per_minute' => 1]);
        Sanctum::actingAs($bob);

        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi'])->assertCreated();
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi again'])->assertStatus(429);
    }

    public function test_pause_returns_503_for_guests_and_201_for_signed_in_senders(): void
    {
        User::factory()->create(['username' => 'alice']);
        config(['psst.guest_sends_enabled' => false]);

        $this->postJson('/api/profiles/alice/messages', ['body' => 'hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Anonymous sending is paused for a moment.');

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hello'])->assertCreated();
    }
}
