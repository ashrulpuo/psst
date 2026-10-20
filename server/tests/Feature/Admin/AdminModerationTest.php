<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Identity;
use App\Models\PushSubscription;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AdminModerationTest extends AdminTestCase
{
    private function audit(string $action): ?AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->first();
    }

    // ---- overview ----

    public function test_the_overview_counts_and_zero_fills_the_series(): void
    {
        Cache::flush();
        [$receiver, $sender] = User::factory()->count(2)->create();
        User::factory()->banned()->create();
        $c = $this->conversation($receiver, $sender);
        Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'rude']);

        $data = $this->asAdmin()->getJson('/api/admin/overview')->assertOk()->json('data');

        $this->assertSame(1 + 2 + 1, $data['totals']['users']); // admin + 2 + banned
        $this->assertSame(1, $data['totals']['banned_users']);
        $this->assertSame(1, $data['totals']['threads']);
        $this->assertSame(2, $data['totals']['messages']);
        $this->assertSame(2, $data['totals']['messages_24h']);
        $this->assertSame(1, $data['totals']['open_reports']);

        $this->assertCount(30, $data['series']['signups']);
        $this->assertCount(30, $data['series']['messages']);
        $this->assertSame(4, collect($data['series']['signups'])->sum('count'));
        $this->assertSame(now('UTC')->format('Y-m-d'), $data['series']['messages'][29]['date']);
        $this->assertSame(2, $data['series']['messages'][29]['count']);
        $this->assertSame(0, $data['series']['messages'][0]['count']);
    }

    // ---- users ----

    public function test_users_can_be_searched_case_insensitively_and_wildcards_are_literal(): void
    {
        User::factory()->create(['username' => 'maria_k', 'name' => 'Maria Kowalski', 'email' => 'maria@example.test']);
        User::factory()->create(['username' => 'other_one', 'name' => 'Someone Else', 'email' => 'other@example.test']);
        User::factory()->create(['username' => 'pct_user', 'name' => '100% Real', 'email' => 'pct@example.test']);

        $find = fn (string $q) => collect($this->asAdmin()->getJson('/api/admin/users?search='.urlencode($q))->assertOk()->json('data'))->pluck('username')->all();

        $this->assertSame(['maria_k'], $find('KOWAL'));
        $this->assertSame(['maria_k'], $find('MARIA@EXAMPLE'));
        $this->assertSame(['pct_user'], $find('100%'));
        // A bare % is a literal: it matches only the one name that really contains a percent sign.
        $this->assertSame(['pct_user'], $find('%'));
    }

    public function test_user_filters_and_cursor_pagination(): void
    {
        User::factory()->count(30)->create();
        User::factory()->banned()->create(['username' => 'banned_one']);

        $first = $this->asAdmin()->getJson('/api/admin/users')->assertOk();
        $first->assertJsonCount(25, 'data');
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->asAdmin()->getJson('/api/admin/users?cursor='.urlencode($cursor))->assertOk();
        $this->assertCount(32 - 25, $second->json('data')); // 30 + banned + the admin

        $this->assertSame(['banned_one'], collect($this->asAdmin()->getJson('/api/admin/users?status=banned')->json('data'))->pluck('username')->all());
        $this->assertSame([$this->admin->id], collect($this->asAdmin()->getJson('/api/admin/users?status=admin')->json('data'))->pluck('id')->all());
    }

    public function test_opening_a_profile_returns_activity_and_is_audited(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        Identity::create(['user_id' => $sender->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);
        $c = $this->conversation($receiver, $sender);
        Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'rude']);

        $data = $this->asAdmin()->getJson("/api/admin/users/{$sender->id}")->assertOk()->json('data');

        $this->assertSame($sender->email, $data['email']);
        $this->assertSame(['google'], $data['providers']);
        $this->assertSame(1, $data['stats']['sent_threads']);
        $this->assertSame(1, $data['stats']['messages_sent']);
        $this->assertSame(1, $data['stats']['reports_against']);
        $this->assertSame(0, $data['stats']['reports_filed']);

        $log = $this->audit('user.viewed');
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($sender->id, $log->subject_id);
    }

    public function test_banning_cuts_off_every_way_the_account_could_still_act(): void
    {
        $target = User::factory()->create();
        $target->createToken('phone');
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()]);
        PushSubscription::create(['user_id' => $target->id, 'channel' => 'webpush', 'token' => 'https://fcm.googleapis.com/x', 'token_hash' => 'h', 'keys' => ['p256dh' => 'a', 'auth' => 'b']]);

        $this->asAdmin()->putJson("/api/admin/users/{$target->id}/ban", ['reason' => 'harassment'])
            ->assertOk()
            ->assertJsonPath('data.ban_reason', 'harassment');

        $this->assertNotNull($target->refresh()->banned_at);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(0, PushSubscription::where('user_id', $target->id)->count());
        $this->assertSame('harassment', $this->audit('user.banned')->meta['reason']);
    }

    public function test_a_banned_user_is_locked_out_of_the_api(): void
    {
        $target = User::factory()->create();
        $this->asAdmin()->putJson("/api/admin/users/{$target->id}/ban", ['reason' => 'spam'])->assertOk();

        Sanctum::actingAs($target->refresh());
        $this->getJson('/api/me')->assertForbidden();
    }

    public function test_admins_and_yourself_cannot_be_banned_and_a_reason_is_required(): void
    {
        $other = $this->makeAdmin();
        $other->save();

        $this->asAdmin()->putJson("/api/admin/users/{$other->id}/ban", ['reason' => 'nope'])->assertUnprocessable();
        $this->asAdmin()->putJson("/api/admin/users/{$this->admin->id}/ban", ['reason' => 'nope'])->assertUnprocessable();

        $target = User::factory()->create();
        $this->asAdmin()->putJson("/api/admin/users/{$target->id}/ban", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertNull($target->refresh()->banned_at);
    }

    public function test_unbanning_restores_access_and_is_audited(): void
    {
        $target = User::factory()->banned()->create();

        $this->asAdmin()->deleteJson("/api/admin/users/{$target->id}/ban")->assertOk()->assertJsonPath('data.banned_at', null);

        $this->assertNull($target->refresh()->banned_at);
        $this->assertNotNull($this->audit('user.unbanned'));
    }

    // ---- threads ----

    public function test_threads_show_both_real_identities(): void
    {
        $receiver = User::factory()->create(['username' => 'alice', 'name' => 'Alice R', 'email' => 'alice@example.test']);
        $sender = User::factory()->create(['username' => 'bob', 'name' => 'Bob S', 'email' => 'bob@example.test']);
        $this->conversation($receiver, $sender);

        $row = $this->asAdmin()->getJson('/api/admin/threads')->assertOk()->json('data.0');

        $this->assertSame('Anonymous #1', $row['label']);
        $this->assertSame('alice@example.test', $row['receiver']['email']);
        $this->assertSame('bob@example.test', $row['sender']['email']);
    }

    public function test_threads_can_be_filtered_by_participant(): void
    {
        [$a, $b, $c] = User::factory()->count(3)->create();
        $this->conversation($a, $b);
        $this->conversation($c, $a);

        $ids = fn (User $u) => count($this->asAdmin()->getJson('/api/admin/threads?user_id='.$u->id)->json('data'));

        $this->assertSame(2, $ids($a));
        $this->assertSame(1, $ids($b));
        $this->assertSame(1, $ids($c));
    }

    public function test_thread_detail_and_messages_are_audited_but_scrolling_is_not(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $c = $this->conversation($receiver, $sender, 'secret hello', 'secret reply');
        foreach (range(1, 55) as $i) { // long enough to need a second page
            $c['thread']->messages()->create(['from_receiver' => false, 'body' => "filler $i"]);
        }

        $detail = $this->asAdmin()->getJson("/api/admin/threads/{$c['thread']->id}")->assertOk();
        $detail->assertJsonPath('data.message_count', 57)->assertJsonPath('data.sender.email', $sender->email);
        $this->assertNotNull($this->audit('thread.viewed'));

        $messages = $this->asAdmin()->getJson("/api/admin/threads/{$c['thread']->id}/messages")->assertOk();
        $this->assertCount(50, $messages->json('data'));
        $this->assertSame('sender', $messages->json('data.0.author'));
        $this->assertSame(1, AuditLog::where('action', 'thread.messages_viewed')->count());

        $older = $this->asAdmin()->getJson("/api/admin/threads/{$c['thread']->id}/messages?cursor=".urlencode((string) $messages->json('meta.next_cursor')))->assertOk();
        $this->assertSame(['secret reply', 'secret hello'], collect($older->json('data'))->pluck('body')->take(7)->slice(-2)->values()->all());
        $this->assertSame(1, AuditLog::where('action', 'thread.messages_viewed')->count(), 'cursor pages must not re-log');
    }

    public function test_unknown_threads_are_not_found_and_participant_only_binding_does_not_apply_to_admins(): void
    {
        $this->asAdmin()->getJson('/api/admin/threads/'.Str::uuid())->assertNotFound();

        [$receiver, $sender] = User::factory()->count(2)->create();
        $c = $this->conversation($receiver, $sender);
        $this->asAdmin()->getJson("/api/admin/threads/{$c['thread']->id}")->assertOk(); // admin is not a participant
    }

    // ---- reports ----

    public function test_the_report_queue_names_who_wrote_the_reported_message(): void
    {
        $receiver = User::factory()->create(['email' => 'receiver@example.test']);
        $sender = User::factory()->create(['email' => 'sender@example.test']);
        $c = $this->conversation($receiver, $sender);

        // The receiver reports the sender's message; the sender reports the receiver's reply.
        Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'rude']);
        $this->travel(5)->seconds();
        Report::create(['reporter_id' => $sender->id, 'message_id' => $c['reply']->id, 'reason' => 'weird']);

        $rows = collect($this->asAdmin()->getJson('/api/admin/reports')->assertOk()->json('data'));

        $this->assertSame(['rude', 'weird'], $rows->pluck('reason')->all(), 'oldest open report first');
        $this->assertSame('sender@example.test', $rows[0]['reported_user']['email']);
        $this->assertSame('receiver@example.test', $rows[0]['reporter']['email']);
        $this->assertSame('receiver@example.test', $rows[1]['reported_user']['email']);
        $this->assertSame('hello there', $rows[0]['message']['body']);
    }

    public function test_a_report_comes_with_the_conversation_around_it(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $c = $this->conversation($receiver, $sender);
        $report = Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'rude']);

        $data = $this->asAdmin()->getJson("/api/admin/reports/{$report->id}")->assertOk()->json('data');

        $this->assertSame(['hello there', 'hi back'], collect($data['context'])->pluck('body')->all());
        $this->assertNotNull($this->audit('report.viewed'));
    }

    public function test_dismissing_a_report_closes_it_with_the_reviewer(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $c = $this->conversation($receiver, $sender);
        $report = Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'rude']);

        $this->asAdmin()->postJson("/api/admin/reports/{$report->id}/resolve", ['status' => 'dismissed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed')
            ->assertJsonPath('data.reviewer.id', $this->admin->id);

        $this->assertNull($sender->refresh()->banned_at);
        $this->assertNotNull($this->audit('report.dismissed'));
        $this->assertCount(0, $this->asAdmin()->getJson('/api/admin/reports')->json('data'), 'closed reports leave the open queue');
        $this->assertCount(1, $this->asAdmin()->getJson('/api/admin/reports?status=dismissed')->json('data'));
    }

    public function test_resolving_with_a_ban_suspends_the_author_of_the_message(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $c = $this->conversation($receiver, $sender);
        $report = Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'threats']);

        $this->asAdmin()->postJson("/api/admin/reports/{$report->id}/resolve", ['status' => 'reviewed', 'ban_author' => true])->assertOk();

        $this->assertNotNull($sender->refresh()->banned_at, 'the sender wrote the reported message');
        $this->assertNull($receiver->refresh()->banned_at);
        $this->assertNotNull($this->audit('user.banned'));
        $this->assertNotNull($this->audit('report.reviewed'));
    }

    public function test_a_report_can_only_be_handled_once(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $c = $this->conversation($receiver, $sender);
        $report = Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'rude']);

        $this->asAdmin()->postJson("/api/admin/reports/{$report->id}/resolve", ['status' => 'reviewed'])->assertOk();
        $this->asAdmin()->postJson("/api/admin/reports/{$report->id}/resolve", ['status' => 'dismissed', 'ban_author' => true])->assertStatus(409);
        $this->assertNull($sender->refresh()->banned_at);
    }

    public function test_resolving_validates_the_status(): void
    {
        [$receiver, $sender] = User::factory()->count(2)->create();
        $c = $this->conversation($receiver, $sender);
        $report = Report::create(['reporter_id' => $receiver->id, 'message_id' => $c['first']->id, 'reason' => 'rude']);

        $this->asAdmin()->postJson("/api/admin/reports/{$report->id}/resolve", ['status' => 'open'])->assertUnprocessable();
    }

    // ---- audit log ----

    public function test_the_audit_log_lists_newest_first_and_filters(): void
    {
        $target = User::factory()->create();
        $this->asAdmin()->getJson("/api/admin/users/{$target->id}")->assertOk();
        $this->travel(2)->seconds();
        $this->asAdmin()->putJson("/api/admin/users/{$target->id}/ban", ['reason' => 'spam'])->assertOk();

        $all = $this->asAdmin()->getJson('/api/admin/audit')->assertOk()->json('data');
        $this->assertSame(['user.banned', 'user.viewed'], collect($all)->pluck('action')->all());
        $this->assertSame($this->admin->email, $all[0]['actor']['email']);
        $this->assertNotEmpty($all[0]['ip']);

        $filtered = $this->asAdmin()->getJson('/api/admin/audit?action=user.viewed')->json('data');
        $this->assertSame(['user.viewed'], collect($filtered)->pluck('action')->all());
    }

    // ---- regression: the receiver-facing API still hides the sender ----

    public function test_receivers_still_never_see_the_sender_in_their_own_api(): void
    {
        $receiver = User::factory()->create(['username' => 'alice']);
        $sender = User::factory()->create(['email' => 'sender.secret@example.test', 'name' => 'Secret Sender']);
        $this->conversation($receiver, $sender);

        Sanctum::actingAs($receiver);
        $json = $this->getJson('/api/inbox')->assertOk()->getContent();

        $this->assertStringNotContainsString('sender.secret@example.test', $json);
        $this->assertStringNotContainsString('Secret Sender', $json);
        $this->assertStringNotContainsString($sender->id, $json);
    }
}
