<?php

namespace Tests\Feature\Growth;

use App\Actions\Messaging\SendGuestMessage;
use App\Admin\AdminStats;
use App\Growth\GrowthEvents;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthEventsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'abcdefghijklmnopqrstuvwxyz012345';

    private function guestSend(string $username = 'alice', string $token = self::TOKEN): void
    {
        $this->flushHeaders();
        $this->withHeaders(['X-Guest-Token' => $token])
            ->postJson("/api/profiles/$username/messages", ['body' => 'hello'])
            ->assertCreated();
    }

    public function test_guest_send_records_guest_sent(): void
    {
        User::factory()->create(['username' => 'alice']);

        $this->guestSend();

        $this->assertSame(1, DB::table('growth_events')->where('name', 'guest_sent')->count());
    }

    public function test_dropped_guest_message_records_nothing(): void
    {
        $alice = User::factory()->create(['username' => 'alice']);
        Thread::unguarded(fn () => Thread::create([
            'receiver_id' => $alice->id, 'anon_label' => 1,
            'guest_key_hash' => hash('sha256', self::TOKEN), 'blocked_at' => now(),
        ]));

        $this->guestSend();

        $this->assertSame(0, DB::table('growth_events')->count());
    }

    public function test_a_recording_failure_never_breaks_a_guest_send(): void
    {
        $alice = User::factory()->create(['username' => 'alice']);
        Schema::drop('growth_events');

        $delivered = app(SendGuestMessage::class)->handle($alice, 'tok', '203.0.113.5', 'hello');

        $this->assertTrue($delivered);
    }

    public function test_events_endpoint_requires_auth(): void
    {
        $this->postJson('/api/events', ['name' => 'link_shared'])->assertUnauthorized();
    }

    public function test_events_endpoint_records_known_client_names(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['link_shared', 'note_shared', 'own_link_after_guest'] as $name) {
            $this->postJson('/api/events', ['name' => $name])->assertNoContent();
        }

        $this->assertSame(3, DB::table('growth_events')->count());
    }

    public function test_events_endpoint_rejects_unknown_names(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['guest_sent', 'bogus', '', null] as $name) {
            $this->postJson('/api/events', ['name' => $name])->assertUnprocessable();
        }

        $this->assertSame(0, DB::table('growth_events')->count());
    }

    public function test_events_store_no_ip_or_user(): void
    {
        $this->assertEqualsCanonicalizing(['id', 'name', 'created_at'], Schema::getColumnListing('growth_events'));
        $this->assertSame(['guest_sent', 'link_shared', 'note_shared', 'own_link_after_guest'], GrowthEvents::NAMES);
    }

    public function test_overview_growth_block_and_null_conversion_when_no_sends(): void
    {
        Cache::forget('admin.overview');

        $this->assertSame([
            'guest_sends_7d' => 0,
            'link_shares_7d' => 0,
            'note_shares_7d' => 0,
            'own_links_after_guest_7d' => 0,
            'conversion_rate' => null,
        ], app(AdminStats::class)->overview()['growth']);
    }

    public function test_overview_growth_counts_the_last_seven_days_and_conversion(): void
    {
        foreach (['guest_sent', 'guest_sent', 'guest_sent', 'guest_sent', 'link_shared', 'note_shared', 'own_link_after_guest'] as $n) {
            GrowthEvents::record($n);
        }
        DB::table('growth_events')->insert(['name' => 'guest_sent', 'created_at' => now()->subDays(8)]);
        Cache::forget('admin.overview');

        $growth = app(AdminStats::class)->overview()['growth'];

        $this->assertSame(4, $growth['guest_sends_7d']);
        $this->assertSame(1, $growth['link_shares_7d']);
        $this->assertSame(1, $growth['note_shares_7d']);
        $this->assertSame(1, $growth['own_links_after_guest_7d']);
        $this->assertSame(0.25, $growth['conversion_rate']);
    }

    public function test_events_are_stored_at_midnight_utc(): void
    {
        $this->travelTo(now()->utc()->setTime(15, 42, 7));

        GrowthEvents::record('link_shared');

        $this->assertSame(
            now()->utc()->startOfDay()->toDateTimeString(),
            Carbon::parse(DB::table('growth_events')->value('created_at'))->utc()->toDateTimeString(),
        );
    }

    public function test_stats_window_is_seven_utc_days(): void
    {
        $this->travelTo(now()->utc()->setTime(15, 0));
        $day = fn (int $ago) => now()->utc()->startOfDay()->subDays($ago);
        DB::table('growth_events')->insert([
            ['name' => 'guest_sent', 'created_at' => $day(6)],
            ['name' => 'guest_sent', 'created_at' => $day(7)],
            ['name' => 'guest_sent', 'created_at' => $day(8)],
        ]);
        Cache::forget('admin.overview');

        $this->assertSame(1, app(AdminStats::class)->overview()['growth']['guest_sends_7d']);
    }

    public function test_prune_deletes_rows_older_than_thirty_days_only(): void
    {
        DB::table('growth_events')->insert([
            ['name' => 'guest_sent', 'created_at' => now()->subDays(31)],
            ['name' => 'guest_sent', 'created_at' => now()->subDays(29)],
        ]);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => $e->description === 'growth-prune');
        $this->assertNotNull($event);
        $event->run(app());

        $this->assertSame(1, DB::table('growth_events')->count());
    }

    public function test_record_ignores_unknown_names(): void
    {
        GrowthEvents::record('bogus');

        $this->assertSame(0, DB::table('growth_events')->count());
    }
}
