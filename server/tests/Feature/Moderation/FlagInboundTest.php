<?php

namespace Tests\Feature\Moderation;

use App\Actions\Messaging\PostMessage;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use App\Moderation\FlagInbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagInboundTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private Thread $thread;

    protected function setUp(): void
    {
        parent::setUp();

        config(['psst.moderation.keywords' => ['kill you']]);
        $this->alice = User::factory()->create(['username' => 'alice']);
        $this->thread = Thread::create(['receiver_id' => $this->alice->id, 'anon_label' => 1, 'guest_key_hash' => hash('sha256', 't')]);
    }

    public function test_guest_message_with_a_term_creates_a_flag_and_is_still_delivered(): void
    {
        $message = app(PostMessage::class)->handle($this->thread, null, 'I will KILL you!');

        $this->assertSame('I will KILL you!', $message->fresh()->body);
        $flag = MessageFlag::query()->sole();
        $this->assertSame($message->id, $flag->message_id);
        $this->assertSame(['kill you'], $flag->terms);
        $this->assertNull($flag->reviewed_at);
        $this->assertArrayNotHasKey('body', $flag->getAttributes());
    }

    public function test_receiver_replies_are_never_flagged(): void
    {
        $sender = User::factory()->create();
        $thread = Thread::create(['receiver_id' => $this->alice->id, 'sender_id' => $sender->id, 'anon_label' => 2]);

        app(PostMessage::class)->handle($thread, $this->alice, 'kill you');

        $this->assertSame(0, MessageFlag::query()->count());
    }

    public function test_clean_message_creates_no_flag(): void
    {
        app(PostMessage::class)->handle($this->thread, null, 'have a nice day');

        $this->assertSame(0, MessageFlag::query()->count());
    }

    public function test_a_flagger_failure_never_breaks_delivery(): void
    {
        $this->app->bind(FlagInbound::class, fn () => new class extends FlagInbound
        {
            public function __construct() {}

            public function handle(Message $message, string $body): ?MessageFlag
            {
                throw new \RuntimeException('boom');
            }
        });

        $message = app(PostMessage::class)->handle($this->thread, null, 'kill you');

        $this->assertNotNull($message->fresh());
    }
}
