<?php

namespace App\Growth;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Aggregate growth counters. Stores the event name and the UTC day (midnight) only; never who did it, and no time of day.
 */
class GrowthEvents
{
    public const NAMES = ['guest_sent', 'link_shared', 'note_shared', 'own_link_after_guest'];

    /** The names the browser may report; guest_sent is recorded by the server when a message is delivered. */
    public const CLIENT_NAMES = ['link_shared', 'note_shared', 'own_link_after_guest'];

    /** Never throws: measurement must not break the action being measured. */
    public static function record(string $name): void
    {
        if (! in_array($name, self::NAMES, true)) {
            return;
        }

        try {
            DB::table('growth_events')->insert(['name' => $name, 'created_at' => now()->utc()->startOfDay()]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
