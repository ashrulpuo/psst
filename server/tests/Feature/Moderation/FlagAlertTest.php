<?php

namespace Tests\Feature\Moderation;

use App\Actions\Messaging\PostMessage;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\FlaggedMessageAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class FlagAlertTest extends TestCase
{
    use RefreshDatabase;

    private Thread $thread;

    protected function setUp(): void
    {
        parent::setUp();

        config(['psst.moderation.keywords' => ['kill you'], 'psst.moderation.alert_email' => 'owner@example.com']);
        $alice = User::factory()->create(['username' => 'alice']);
        $this->thread = Thread::create(['receiver_id' => $alice->id, 'anon_label' => 1, 'guest_key_hash' => hash('sha256', 't')]);
    }

    public function test_flagged_message_alerts_the_owner_once(): void
    {
        Notification::fake();

        app(PostMessage::class)->handle($this->thread, null, 'i will kill you');

        Notification::assertSentOnDemandTimes(FlaggedMessageAlert::class, 1);
        Notification::assertSentOnDemand(FlaggedMessageAlert::class, fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'owner@example.com');
    }

    public function test_mail_never_contains_the_body(): void
    {
        $mail = (new FlaggedMessageAlert)->toMail(new AnonymousNotifiable);
        $text = $mail->subject.' '.implode(' ', $mail->introLines);

        $this->assertStringContainsString('A message was flagged', $text);
        $this->assertStringEndsWith('/admin/flags', $mail->actionUrl);
    }

    public function test_no_alert_without_an_address(): void
    {
        config(['psst.moderation.alert_email' => '']);
        Notification::fake();

        app(PostMessage::class)->handle($this->thread, null, 'i will kill you');

        Notification::assertNothingSent();
    }

    public function test_clean_message_sends_no_alert(): void
    {
        Notification::fake();

        app(PostMessage::class)->handle($this->thread, null, 'hello');

        Notification::assertNothingSent();
    }

    public function test_a_mail_failure_never_breaks_delivery_or_the_flag(): void
    {
        Notification::shouldReceive('route')->andThrow(new RuntimeException('smtp down'));

        $message = app(PostMessage::class)->handle($this->thread, null, 'i will kill you');

        $this->assertNotNull($message->fresh());
        $this->assertSame(1, MessageFlag::query()->count());
    }
}
