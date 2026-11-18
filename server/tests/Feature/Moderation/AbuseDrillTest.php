<?php

namespace Tests\Feature\Moderation;

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\FlaggedMessageAlert;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\AdminTestCase;

/**
 * The owner is the only moderator, so the whole path has to work as one flow: abusive message arrives,
 * is flagged, the owner is alerted (without the text), reviews it, and acts on the sender.
 */
class AbuseDrillTest extends AdminTestCase
{
    private const ABUSE = 'i will kill you';

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        config(['psst.moderation.keywords' => ['kill you'], 'psst.moderation.alert_email' => 'owner@example.com']);
        $this->alice = User::factory()->create(['username' => 'alice']);
    }

    /** Switch the acting user mid-test (the guard caches the previous one). */
    private function actAs(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        Sanctum::actingAs($user);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    private function guestSend(string $username, string $body, ?string $token = null, string $ip = '203.0.113.5'): string
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withHeaders($token ? ['X-Guest-Token' => $token] : [])
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson("/api/profiles/$username/messages", ['body' => $body])
            ->assertCreated()
            ->assertJsonPath('data.sent', true)
            ->json('guest_token');
    }

    public function test_flagged_guest_message_reaches_admin_and_owner_alert(): void
    {
        Notification::fake();

        // 1. A guest sends something abusive: it is delivered, flagged, and the owner is alerted once.
        $token = $this->guestSend('alice', self::ABUSE);

        $message = Message::query()->firstOrFail();
        $thread = Thread::query()->firstOrFail();
        $this->assertSame(self::ABUSE, $message->body);
        $this->assertNull($thread->sender_id);
        $this->assertSame(1, MessageFlag::query()->where('message_id', $message->id)->count());

        Notification::assertSentOnDemandTimes(FlaggedMessageAlert::class, 1);
        Notification::assertSentOnDemand(FlaggedMessageAlert::class, function ($n, $channels, AnonymousNotifiable $to) {
            $mail = $n->toMail($to);
            $text = $mail->subject.' '.implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines).' '.$mail->actionUrl.' '.$mail->render();

            $this->assertSame('owner@example.com', $to->routes['mail']);
            $this->assertStringNotContainsString('kill', $text);
            $this->assertStringNotContainsString('alice', $text);
            $this->assertStringContainsString('/admin/flags', $text);

            return true;
        });

        // A second abusive message in the debounce window does not email again, but is still flagged.
        $this->guestSend('alice', 'i will kill you tomorrow', $token);
        $this->assertSame(2, MessageFlag::query()->count());
        Notification::assertSentOnDemandTimes(FlaggedMessageAlert::class, 1);

        // 2. The admin sees the flags with the decrypted body, and the view is audited.
        $flags = $this->asAdmin()->getJson('/api/admin/flags')->assertOk();
        $flags->assertJsonCount(2, 'data');
        $flags->assertJsonPath('data.0.body', self::ABUSE);
        $flags->assertJsonPath('data.0.sender_kind', 'guest');
        $flags->assertJsonPath('data.0.terms', ['kill you']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'flags.viewed', 'actor_id' => $this->admin->id]);

        // 3. Reviewing one removes it from the open list; reviewing again conflicts.
        $flagId = $flags->json('data.0.id');
        $this->asAdmin()->postJson("/api/admin/flags/$flagId/review")->assertOk();
        $this->asAdmin()->postJson("/api/admin/flags/$flagId/review")->assertStatus(409);
        $this->asAdmin()->getJson('/api/admin/flags')->assertJsonCount(1, 'data');
        $this->assertSame(1, AuditLog::query()->where('action', 'flag.reviewed')->where('subject_id', $flagId)->count());

        // 4. The receiver blocks the guest's thread: further messages are silently dropped.
        $this->actAs($this->alice);
        $this->putJson("/api/threads/{$thread->id}/block")->assertNoContent();
        $this->guestSend('alice', 'still here', $token, '198.51.100.9');
        $this->assertSame(2, Message::query()->count());
    }

    public function test_banned_signed_in_sender_is_refused(): void
    {
        Notification::fake();
        $sender = User::factory()->create();

        // A signed-in sender's abusive message is flagged as 'user', and the receiver reports it.
        $this->actAs($sender);
        $this->postJson('/api/profiles/alice/messages', ['body' => self::ABUSE])->assertCreated();

        $message = Message::query()->firstOrFail();
        $this->assertSame(1, MessageFlag::query()->where('message_id', $message->id)->count());
        Notification::assertSentOnDemandTimes(FlaggedMessageAlert::class, 1);

        $this->asAdmin()->getJson('/api/admin/flags')->assertOk()->assertJsonPath('data.0.sender_kind', 'user');

        $this->actAs($this->alice);
        $this->postJson("/api/messages/{$message->id}/report", ['reason' => 'threat'])->assertAccepted();
        $report = Report::query()->firstOrFail();

        // The admin opens the report (audited) and bans the sender (audited).
        $this->asAdmin()->getJson("/api/admin/reports/{$report->id}")->assertOk();
        $this->asAdmin()->putJson("/api/admin/users/{$sender->id}/ban", ['reason' => 'threats'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.viewed', 'subject_id' => $report->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.banned', 'subject_id' => $sender->id, 'actor_id' => $this->admin->id]);

        // Consequences: the banned account is refused, including on the public send route.
        $this->actAs($sender->refresh());
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hello again'])
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been suspended.');
        $this->getJson('/api/me')->assertForbidden();
        $this->assertSame(1, Message::query()->count());
    }

    public function test_guest_blocked_by_one_receiver_is_dropped_only_for_that_receiver(): void
    {
        $carol = User::factory()->create(['username' => 'carol']);

        $token = $this->guestSend('alice', 'first to alice');
        $thread = Thread::query()->where('receiver_id', $this->alice->id)->firstOrFail();

        $this->actAs($this->alice);
        $this->putJson("/api/threads/{$thread->id}/block")->assertNoContent();

        // To alice: identical success response, nothing stored.
        $this->guestSend('alice', 'blocked now', $token);
        $this->assertSame(['first to alice'], Message::query()->pluck('body')->all());

        // To carol: same guest, same device, delivered.
        $this->guestSend('carol', 'hi carol', $token);
        $this->assertSame(1, Message::query()->whereHas('thread', fn ($q) => $q->where('receiver_id', $carol->id))->count());
        $this->assertSame(2, Message::query()->count());
    }
}
