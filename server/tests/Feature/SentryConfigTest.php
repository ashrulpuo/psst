<?php

namespace Tests\Feature;

use App\Observability\ScrubEvent;
use Illuminate\Support\Facades\Route;
use Sentry\Event;
use Sentry\State\HubInterface;
use Tests\TestCase;

class SentryConfigTest extends TestCase
{
    public function test_sentry_is_inert_without_a_dsn(): void
    {
        $this->assertEmpty(config('sentry.dsn'));
        $this->assertNull(app(HubInterface::class)->getClient()?->getOptions()->getDsn());
    }

    public function test_pii_is_off_and_scrubber_is_registered(): void
    {
        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertSame([ScrubEvent::class, 'handle'], config('sentry.before_send'));
        $this->assertSame([ScrubEvent::class, 'dropTransaction'], config('sentry.before_send_transaction'));
        $this->assertFalse(config('sentry.breadcrumbs.sql_bindings'));
        $this->assertFalse(config('sentry.tracing.sql_bindings'));
    }

    public function test_tracing_is_hard_wired_off(): void
    {
        $this->assertNull(config('sentry.traces_sample_rate'));
        $this->assertNull(config('sentry.profiles_sample_rate'));

        foreach (config('sentry.tracing') as $key => $value) {
            if (is_bool($value)) {
                $this->assertFalse($value, "sentry.tracing.{$key} must stay off");
            }
        }
    }

    public function test_transactions_are_dropped(): void
    {
        $this->assertNull(ScrubEvent::dropTransaction(Event::createTransaction()));
    }

    public function test_request_url_is_masked_to_the_route_template(): void
    {
        $captured = null;
        Route::get('api/profiles/{username}', function () use (&$captured) {
            $event = Event::createEvent();
            $event->setTransaction('/api/profiles/alice');
            $event->setRequest(['method' => 'GET', 'url' => 'https://psst.example/api/profiles/alice?x=1']);
            $captured = ScrubEvent::handle($event);

            return 'ok';
        });

        $this->get('/api/profiles/alice')->assertOk();

        $this->assertSame('https://psst.example/api/profiles/{username}', $captured->getRequest()['url']);
        $this->assertSame('api/profiles/{username}', $captured->getTransaction());
        $this->assertStringNotContainsString('alice', json_encode([$captured->getRequest(), $captured->getTransaction()]));
    }
}
