<?php

namespace App\Observability;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;

/**
 * Sentry `before_send` hook. psst is anonymous: message bodies, guest tokens, IPs, cookies and request
 * payloads must never leave the server. Everything is dropped by default and only a short allow-list of
 * request, context and tag fields survives, so an exception still arrives with its type, message,
 * stack trace, route and release.
 */
final class ScrubEvent
{
    private const REQUEST_HEADERS = ['content-type', 'content-length', 'accept'];

    private const CONTEXTS = ['os', 'runtime', 'device', 'app', 'trace', 'culture'];

    private const TAGS = ['environment', 'level', 'release', 'server_name', 'runtime', 'runtime.name', 'transaction', 'mechanism', 'handled'];

    public static function handle(Event $event): Event
    {
        $event->setRequest(self::request($event->getRequest()));
        $event->setUser(null);
        $event->setExtra([]);
        self::keepContexts($event);
        $event->setTags(array_intersect_key($event->getTags(), array_flip(self::TAGS)));

        if ($event->getMessage() !== null) {
            $event->setMessage('[scrubbed]');
        }

        $event->setBreadcrumb(array_map(
            fn (Breadcrumb $crumb) => new Breadcrumb($crumb->getLevel(), $crumb->getType(), $crumb->getCategory(), null, [], $crumb->getTimestamp()),
            $event->getBreadcrumbs(),
        ));

        foreach ($event->getExceptions() as $exception) {
            self::exception($exception);
        }

        return $event;
    }

    /** Event has no way to remove a context (setContext ignores empty data), so the property is reassigned. */
    private static function keepContexts(Event $event): void
    {
        $kept = array_intersect_key($event->getContexts(), array_flip(self::CONTEXTS));

        \Closure::bind(function () use ($kept): void {
            $this->contexts = $kept;
        }, $event, Event::class)();
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private static function request(array $request): array
    {
        $clean = [];

        if (isset($request['method'])) {
            $clean['method'] = $request['method'];
        }

        if (isset($request['url']) && is_string($request['url'])) {
            $clean['url'] = preg_replace('/[?#].*$/', '', $request['url']);
        }

        $headers = array_filter(
            (array) ($request['headers'] ?? []),
            fn ($name) => in_array(strtolower((string) $name), self::REQUEST_HEADERS, true),
            ARRAY_FILTER_USE_KEY,
        );
        if ($headers !== []) {
            $clean['headers'] = $headers;
        }

        return $clean;
    }

    private static function exception(ExceptionDataBag $exception): void
    {
        $exception->setValue(self::message($exception->getValue()));

        foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
            /** @var Frame $frame */
            $frame->setVars([]);
            $frame->setContextLine(null);
            $frame->setPreContext([]);
            $frame->setPostContext([]);
        }
    }

    /** Database errors embed the bound values (the message body) in the SQL text and in Postgres DETAIL lines. */
    private static function message(string $message): string
    {
        $message = preg_replace('/\s*\((?:Connection|SQL):.*$/s', ' [query redacted]', $message) ?? $message;

        return preg_replace('/DETAIL:.*$/s', 'DETAIL: [redacted]', $message) ?? $message;
    }
}
