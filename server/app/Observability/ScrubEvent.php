<?php

namespace App\Observability;

use Illuminate\Support\Facades\Facade;
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

    private const TAGS = ['environment', 'level', 'release', 'server_name', 'runtime', 'runtime.name', 'mechanism', 'handled'];

    private const MAX_MESSAGE = 200;

    /** before_send_transaction: tracing is not used and a half-scrubbed transaction has no value, so drop it. */
    public static function dropTransaction(Event $event): ?Event
    {
        return null;
    }

    public static function handle(Event $event): Event
    {
        $route = self::routeTemplate();
        $event->setRequest(self::request($event->getRequest(), $route));
        $event->setTransaction(self::transaction($event->getTransaction(), $route));
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

        self::frames($event->getStacktrace()?->getFrames() ?? []);

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
    private static function request(array $request, ?string $route): array
    {
        $clean = [];

        if (isset($request['method'])) {
            $clean['method'] = $request['method'];
        }

        if (isset($request['url']) && is_string($request['url'])) {
            // Paths carry usernames (profiles/{username}); keep scheme and host plus the route template only.
            $origin = parse_url($request['url'], PHP_URL_SCHEME).'://'.parse_url($request['url'], PHP_URL_HOST);
            $clean['url'] = $origin.'/'.($route !== null ? ltrim($route, '/') : '[path]');
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

        self::frames($exception->getStacktrace()?->getFrames() ?? []);
    }

    /** @param  Frame[]  $frames */
    private static function frames(array $frames): void
    {
        foreach ($frames as $frame) {
            $frame->setVars([]);
            $frame->setContextLine(null);
            $frame->setPreContext([]);
            $frame->setPostContext([]);
        }
    }

    /** The matched route's URI template (e.g. api/profiles/{username}), when a request is being handled. */
    private static function routeTemplate(): ?string
    {
        $app = Facade::getFacadeApplication();
        if ($app === null || ! $app->bound('request')) {
            return null;
        }

        return $app->make('request')->route()?->uri();
    }

    private static function transaction(?string $name, ?string $route): ?string
    {
        if ($name === null) {
            return null;
        }

        if ($route !== null) {
            return $route;
        }

        // Unmatched requests are named after the raw path; jobs and commands have no slash.
        return str_contains($name, '/') && ! str_contains($name, '{') ? '[path]' : $name;
    }

    /** Database errors embed the bound values (the message body) in the SQL text and in Postgres DETAIL lines. */
    private static function message(string $message): string
    {
        $message = preg_replace('/\s*\((?:Connection|SQL):.*$/s', ' [query redacted]', $message) ?? $message;
        $message = preg_replace('/DETAIL:.*$/s', 'DETAIL: [redacted]', $message) ?? $message;

        if (str_starts_with($message, 'SQLSTATE[')) {
            // Keep only the code and error class; anything after the first colon, quote or bracket may hold the value.
            preg_match('/^SQLSTATE\[[^\]]*\]:?\s*[^:"\'(]*/', $message, $m);
            $message = rtrim($m[0] ?? 'SQLSTATE');
        }

        return mb_strlen($message) > self::MAX_MESSAGE ? mb_substr($message, 0, self::MAX_MESSAGE).'... [truncated]' : $message;
    }
}
