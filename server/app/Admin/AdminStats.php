<?php

namespace App\Admin;

use App\Models\Message;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the dashboard. Cached briefly: a moderator glance does not need to be real-time, and these
 * are aggregate scans.
 */
class AdminStats
{
    public const DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return Cache::remember('admin.overview', 60, fn () => $this->compute());
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(): array
    {
        $now = now();

        return [
            'totals' => [
                'users' => User::query()->count(),
                'new_users_7d' => User::query()->where('created_at', '>=', $now->copy()->subDays(7))->count(),
                'active_users_7d' => User::query()->where('last_login_at', '>=', $now->copy()->subDays(7))->count(),
                'banned_users' => User::query()->whereNotNull('banned_at')->count(),
                'threads' => Thread::query()->count(),
                'messages' => Message::query()->count(),
                'messages_24h' => Message::query()->where('created_at', '>=', $now->copy()->subDay())->count(),
                'open_reports' => Report::query()->where('status', 'open')->count(),
            ],
            'series' => [
                'signups' => $this->daily(User::query(), 'created_at'),
                'messages' => $this->daily(Message::query(), 'created_at'),
            ],
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Counts per UTC day for the last DAYS days, zero-filled so charts have no gaps.
     *
     * @param  Builder<Model>  $query
     * @return list<array{date: string, count: int}>
     */
    private function daily($query, string $column): array
    {
        $start = Carbon::now('UTC')->startOfDay()->subDays(self::DAYS - 1);

        $rows = $query
            ->where($column, '>=', $start)
            ->select(DB::raw("to_char(date_trunc('day', $column AT TIME ZONE 'UTC'), 'YYYY-MM-DD') as day"), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, self::DAYS - 1))
            ->map(function (int $i) use ($start, $rows) {
                $date = $start->copy()->addDays($i)->format('Y-m-d');

                return ['date' => $date, 'count' => (int) ($rows[$date] ?? 0)];
            })
            ->all();
    }
}
