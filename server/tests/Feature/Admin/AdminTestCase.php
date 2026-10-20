<?php

namespace Tests\Feature\Admin;

use App\Admin\Totp;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin();
    }

    /** An admin whose second factor is already set up. */
    protected function makeAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes)->forceFill([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ]);
    }

    /**
     * Act as an admin on a browser session (stateful origin), optionally with the second factor verified.
     */
    protected function asAdmin(?User $admin = null, bool $verified = true, ?int $verifiedAt = null): static
    {
        $admin ??= $this->admin;
        $admin->save();

        Sanctum::actingAs($admin);
        $this->withHeader('Origin', 'http://localhost:3000');

        if ($verified) {
            $this->withSession([EnsureAdminTwoFactor::SESSION_KEY => $verifiedAt ?? time()]);
        }

        return $this;
    }

    protected function currentCode(): string
    {
        return Totp::code(self::SECRET, intdiv(time(), 30));
    }

    /**
     * A conversation: $sender wrote to $receiver and got a reply.
     *
     * @return array{thread: Thread, first: Message, reply: Message}
     */
    protected function conversation(User $receiver, User $sender, string $body = 'hello there', string $reply = 'hi back'): array
    {
        $thread = Thread::create(['receiver_id' => $receiver->id, 'sender_id' => $sender->id, 'anon_label' => 1]);
        $first = $thread->messages()->create(['from_receiver' => false, 'body' => $body]);
        $second = $thread->messages()->create(['from_receiver' => true, 'body' => $reply]);
        $thread->forceFill(['last_message_at' => now(), 'last_message_id' => $second->id])->save();

        return ['thread' => $thread, 'first' => $first, 'reply' => $second];
    }
}
