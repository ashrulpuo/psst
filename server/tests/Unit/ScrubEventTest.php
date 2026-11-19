<?php

namespace Tests\Unit;

use App\Observability\ScrubEvent;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\UserDataBag;

class ScrubEventTest extends TestCase
{
    private const BODY = 'meet me behind the library at 9';

    private const GUEST_TOKEN = 'gst_8f3a91c2d4e5b6a7';

    private const BEARER = 'Bearer 12|abcdefSECRETsanctumtoken';

    private const COOKIE = 'psst_session=eyJzZXNzaW9uIjoic2VjcmV0In0%3D';

    private const IP = '203.0.113.77';

    private const EMAIL = 'someone@example.org';

    private function event(?string $exceptionMessage = 'Call to undefined method Foo::bar()'): Event
    {
        $event = Event::createEvent();
        $event->setLevel(Severity::error());
        $event->setMessage('request failed for '.self::BODY);

        $event->setRequest([
            'url' => 'https://psst.example/api/rooms/42/messages?token='.self::GUEST_TOKEN,
            'method' => 'POST',
            'query_string' => 'token='.self::GUEST_TOKEN,
            'data' => ['body' => self::BODY, 'guest_token' => self::GUEST_TOKEN],
            'cookies' => ['psst_session' => self::COOKIE],
            'env' => ['REMOTE_ADDR' => self::IP],
            'headers' => [
                'Authorization' => [self::BEARER],
                'Cookie' => [self::COOKIE],
                'X-XSRF-TOKEN' => ['xsrf-secret-value'],
                'X-Guest-Token' => [self::GUEST_TOKEN],
                'X-Forwarded-For' => [self::IP],
                'Content-Type' => ['application/json'],
                'User-Agent' => ['Mozilla/5.0'],
            ],
        ]);

        $event->setUser(new UserDataBag('99', self::EMAIL, self::IP, 'secret-handle'));

        $event->setExtra(['body' => self::BODY, 'note' => 'also '.self::BODY]);
        $event->setContext('laravel', ['guest_token' => self::GUEST_TOKEN]);
        $event->setContext('runtime', ['name' => 'php', 'version' => '8.3.0']);
        $event->setTags(['guest' => self::GUEST_TOKEN]);

        $event->setBreadcrumb([
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'log', 'sent: '.self::BODY, ['body' => self::BODY]),
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'sql.query', 'insert into messages (body) values (?)', ['bindings' => [self::BODY], 'body' => self::BODY]),
        ]);

        $frame = new Frame('App\\Foo::bar', '/app/Foo.php', 12);
        $frame->setVars(['body' => self::BODY]);
        $frame->setContextLine('$x = "'.self::BODY.'";');
        $frame->setPreContext(['before '.self::BODY]);
        $frame->setPostContext(['after '.self::BODY]);

        if ($exceptionMessage !== null) {
            $event->setExceptions([
                new ExceptionDataBag(new \RuntimeException($exceptionMessage), new Stacktrace([$frame])),
            ]);
        }

        return $event;
    }

    /** Every sensitive string, anywhere in the serialised payload. */
    private function assertClean(Event $event): void
    {
        $dump = json_encode([
            $event->getMessage(), $event->getRequest(), $event->getExtra(), $event->getContexts(),
            $event->getTags(), $event->getUser()?->getEmail(), $event->getUser()?->getIpAddress(), $event->getUser()?->getUsername(),
            array_map(fn (Breadcrumb $b) => [$b->getMessage(), $b->getMetadata(), $b->getCategory()], $event->getBreadcrumbs()),
            array_map(fn (ExceptionDataBag $e) => [
                $e->getValue(),
                array_map(fn (Frame $f) => [$f->getVars(), $f->getContextLine(), $f->getPreContext(), $f->getPostContext()], $e->getStacktrace()?->getFrames() ?? []),
            ], $event->getExceptions()),
        ], JSON_UNESCAPED_SLASHES);

        foreach ([self::BODY, self::GUEST_TOKEN, 'sanctumtoken', 'xsrf-secret-value', 'eyJzZXNzaW9u', self::IP, self::EMAIL, 'secret-handle'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump, "leaked: {$secret}");
        }
    }

    public function test_scrubs_every_sensitive_field(): void
    {
        $this->assertClean(ScrubEvent::handle($this->event()));
    }

    public function test_request_keeps_only_method_and_masked_url(): void
    {
        $request = ScrubEvent::handle($this->event())->getRequest();

        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://psst.example/[path]', $request['url']);
        $this->assertArrayNotHasKey('data', $request);
        $this->assertArrayNotHasKey('cookies', $request);
        $this->assertArrayNotHasKey('query_string', $request);
        $this->assertArrayNotHasKey('env', $request);
        $this->assertArrayNotHasKey('Authorization', $request['headers'] ?? []);
        $this->assertArrayNotHasKey('Cookie', $request['headers'] ?? []);
        $this->assertArrayNotHasKey('X-Guest-Token', $request['headers'] ?? []);
        $this->assertSame(['application/json'], $request['headers']['Content-Type'] ?? null);
    }

    public function test_user_is_removed_and_body_keys_are_dropped(): void
    {
        $result = ScrubEvent::handle($this->event());

        $this->assertNull($result->getUser());
        $this->assertArrayNotHasKey('body', $result->getExtra());
        foreach ($result->getBreadcrumbs() as $crumb) {
            $this->assertArrayNotHasKey('body', $crumb->getMetadata());
            $this->assertArrayNotHasKey('bindings', $crumb->getMetadata());
        }
    }

    public function test_event_stays_useful(): void
    {
        $result = ScrubEvent::handle($this->event());

        $exception = $result->getExceptions()[0];
        $this->assertSame(\RuntimeException::class, $exception->getType());
        $this->assertSame('Call to undefined method Foo::bar()', $exception->getValue());
        $frame = $exception->getStacktrace()->getFrames()[0];
        $this->assertSame('/app/Foo.php', $frame->getFile());
        $this->assertSame(12, $frame->getLine());
        $this->assertSame('App\\Foo::bar', $frame->getFunctionName());
        $this->assertTrue(Severity::error()->isEqualTo($result->getLevel()));
        $this->assertSame('php', $result->getContexts()['runtime']['name']);
        $this->assertNotEmpty($result->getBreadcrumbs());
    }

    public function test_sql_error_messages_lose_their_bindings(): void
    {
        $message = 'SQLSTATE[22001]: String data, right truncated: 7 ERROR: value too long (Connection: pgsql, SQL: insert into "messages" ("body") values ('.self::BODY.'))';

        $result = ScrubEvent::handle($this->event($message));
        $value = $result->getExceptions()[0]->getValue();

        $this->assertStringNotContainsString(self::BODY, $value);
        $this->assertStringContainsString('SQLSTATE[22001]', $value);
        $this->assertClean($result);
    }

    public function test_postgres_detail_values_are_redacted(): void
    {
        $message = 'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "u" DETAIL:  Key (token_hash)=('.self::GUEST_TOKEN.') already exists.';

        $value = ScrubEvent::handle($this->event($message))->getExceptions()[0]->getValue();

        $this->assertStringNotContainsString(self::GUEST_TOKEN, $value);
        $this->assertSame('SQLSTATE[23505]: Unique violation', $value);
    }

    public function test_embedded_quotes_leave_no_value_fragments(): void
    {
        $message = 'SQLSTATE[22P02]: Invalid text representation: 7 ERROR: invalid input syntax for type uuid: "he said "hi""';

        $value = ScrubEvent::handle($this->event($message))->getExceptions()[0]->getValue();

        $this->assertSame('SQLSTATE[22P02]: Invalid text representation', $value);
        $this->assertStringNotContainsString('hi', $value);
    }

    public function test_transaction_tag_does_not_survive(): void
    {
        $event = $this->event();
        $event->setTags(['transaction' => '/api/profiles/alice', 'runtime' => 'php']);

        $tags = ScrubEvent::handle($event)->getTags();

        $this->assertArrayNotHasKey('transaction', $tags);
        $this->assertSame('php', $tags['runtime']);
    }

    public function test_handles_a_bare_event(): void
    {
        $result = ScrubEvent::handle(Event::createEvent());

        $this->assertSame([], $result->getExceptions());
        $this->assertNull($result->getUser());
    }

    public function test_exception_values_are_truncated(): void
    {
        $value = ScrubEvent::handle($this->event(str_repeat('x', 500)))->getExceptions()[0]->getValue();

        $this->assertLessThanOrEqual(220, strlen($value));
        $this->assertStringEndsWith('[truncated]', $value);
    }

    public function test_raw_pdo_message_without_connection_suffix_is_redacted(): void
    {
        $message = 'SQLSTATE[22P02]: Invalid text representation: 7 ERROR: invalid input syntax for type json DETAIL: Token "'.self::BODY.'" is invalid. CONTEXT: JSON data, line 1: "'.self::BODY.'"';

        $result = ScrubEvent::handle($this->event($message));

        $this->assertStringNotContainsString(self::BODY, $result->getExceptions()[0]->getValue());
        $this->assertStringContainsString('SQLSTATE[22P02]', $result->getExceptions()[0]->getValue());

        $quoted = 'SQLSTATE[22P02]: Invalid text representation: 7 ERROR: invalid input syntax for type uuid: "'.self::BODY.'"';
        $this->assertStringNotContainsString(self::BODY, ScrubEvent::handle($this->event($quoted))->getExceptions()[0]->getValue());
    }

    public function test_event_level_stacktrace_is_cleared(): void
    {
        $event = $this->event();
        $frame = new Frame('f', '/app/F.php', 3);
        $frame->setVars(['body' => self::BODY]);
        $frame->setContextLine(self::BODY);
        $frame->setPreContext([self::BODY]);
        $frame->setPostContext([self::BODY]);
        $event->setStacktrace(new Stacktrace([$frame]));

        $out = ScrubEvent::handle($event)->getStacktrace()->getFrames()[0];

        $this->assertSame([], $out->getVars());
        $this->assertNull($out->getContextLine());
        $this->assertSame([], $out->getPreContext());
        $this->assertSame([], $out->getPostContext());
        $this->assertSame('/app/F.php', $out->getFile());
    }
}
