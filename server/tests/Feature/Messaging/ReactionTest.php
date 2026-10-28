<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReactionTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $sender;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = User::factory()->create(['username' => 'alice']);
        $this->sender = User::factory()->create(['username' => 'bob']);

        Sanctum::actingAs($this->sender);
        $this->postJson('/api/profiles/alice/messages', ['body' => 'you were great'])->assertCreated();

        $this->message = Message::query()->sole();
    }

    private function react(User $user, string $emoji = '🔥'): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson("/api/messages/{$this->message->id}/reactions", ['emoji' => $emoji]);
    }

    public function test_the_receiver_reacts_and_sees_it_as_theirs(): void
    {
        $this->react($this->receiver)
            ->assertOk()
            ->assertJsonPath('data.reactions.0.emoji', '🔥')
            ->assertJsonPath('data.reactions.0.count', 1)
            ->assertJsonPath('data.reactions.0.mine', true);
    }

    public function test_reacting_twice_with_the_same_emoji_removes_it(): void
    {
        $this->react($this->receiver)->assertOk();

        $this->react($this->receiver)
            ->assertOk()
            ->assertJsonPath('data.reactions', []);
    }

    public function test_both_participants_count_towards_the_same_emoji(): void
    {
        $this->react($this->receiver, '❤️')->assertOk();

        $this->react($this->sender, '❤️')
            ->assertOk()
            ->assertJsonPath('data.reactions.0.count', 2)
            ->assertJsonPath('data.reactions.0.mine', true);
    }

    public function test_a_second_emoji_appears_next_to_the_first(): void
    {
        $this->react($this->receiver, '🔥')->assertOk();

        $this->react($this->receiver, '😂')
            ->assertOk()
            ->assertJsonCount(2, 'data.reactions');
    }

    public function test_only_whitelisted_emoji_are_accepted(): void
    {
        $this->react($this->receiver, '💩')
            ->assertStatus(422)
            ->assertJsonValidationErrors('emoji');
    }

    public function test_a_stranger_cannot_react(): void
    {
        $stranger = User::factory()->create(['username' => 'eve']);

        // The route binding hides other people's messages entirely, so this is a 404, not a 403.
        $this->react($stranger)->assertNotFound();
    }

    public function test_reactions_ship_with_the_thread_history(): void
    {
        $this->react($this->receiver)->assertOk();

        Sanctum::actingAs($this->receiver);
        $this->getJson('/api/threads/'.Thread::query()->sole()->id.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.reactions.0.emoji', '🔥')
            ->assertJsonPath('data.0.reactions.0.mine', true);
    }
}
