<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The server will POST to this URL, so it must be a real push service and nothing else.
 * Rejects non-https, credentials, odd ports, IP literals and any host outside the allow-list.
 */
class PushEndpoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $fail('The :attribute is not a valid push endpoint.');

            return;
        }

        $host = strtolower($parts['host']);
        $extra = app()->isProduction() ? [] : config('psst.push.extra_hosts');
        $isExtra = in_array($host, $extra, true);

        $secure = $parts['scheme'] === 'https' && ! isset($parts['port']) || ($parts['port'] ?? null) === 443;
        $plain = $isExtra && in_array($parts['scheme'], ['http', 'https'], true); // local test server only

        if ((! $secure && ! $plain) || isset($parts['user']) || isset($parts['pass'])) {
            $fail('The :attribute must be an https push-service URL.');

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && ! $isExtra) {
            $fail('The :attribute must not be an IP address.');

            return;
        }

        foreach ([...config('psst.push.allowed_hosts'), ...$extra] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return;
            }
        }

        $fail('That push service is not supported.');
    }
}
