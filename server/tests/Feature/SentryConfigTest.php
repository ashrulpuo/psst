<?php

namespace Tests\Feature;

use App\Observability\ScrubEvent;
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
        $this->assertSame([ScrubEvent::class, 'handle'], config('sentry.before_send_transaction'));
        $this->assertFalse(config('sentry.breadcrumbs.sql_bindings'));
        $this->assertFalse(config('sentry.tracing.sql_bindings'));
    }
}
