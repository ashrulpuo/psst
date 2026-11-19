<?php

namespace Tests\Feature\Moderation;

use App\Actions\Messaging\PostMessage;
use App\Jobs\NotifyAdminOfFlag;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\FlaggedMessageAlert;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Mockery;
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

    public function test_three_flagged_messages_in_the_window_send_one_alert_then_another_after_it(): void
    {
        Notification::fake();

        foreach (range(1, 3) as $i) {
            app(PostMessage::class)->handle($this->thread, null, "i will kill you $i");
        }

        $this->assertSame(3, MessageFlag::query()->count());
        Notification::assertSentOnDemandTimes(FlaggedMessageAlert::class, 1);

        $this->travel(11)->minutes();
        app(PostMessage::class)->handle($this->thread, null, 'i will kill you again');

        Notification::assertSentOnDemandTimes(FlaggedMessageAlert::class, 2);
    }

    public function test_the_window_is_not_claimed_when_no_address_is_set(): void
    {
        config(['psst.moderation.alert_email' => '']);

        app(PostMessage::class)->handle($this->thread, null, 'i will kill you');

        $this->assertFalse(Cache::has('psst:flag-alert'));
    }

    public function test_a_throwing_dispatch_still_delivers_and_flags(): void
    {
        // Only the alert job fails to queue; every other dispatch (push, broadcast) still goes through.
        $dispatcher = Mockery::mock($this->app->make(Dispatcher::class))->makePartial();
        $dispatcher->shouldReceive('dispatch')->with(Mockery::type(NotifyAdminOfFlag::class))->andThrow(new RuntimeException('queue down'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $message = app(PostMessage::class)->handle($this->thread, null, 'i will kill you');

        $this->assertNotNull($message->fresh());
        $this->assertSame(1, MessageFlag::query()->count());
        $this->assertFalse(Cache::has('psst:flag-alert'));
    }
}
