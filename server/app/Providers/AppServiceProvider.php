<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\Thread;
use App\Push\MinishlinkWebPushSender;
use App\Push\WebPushSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebPushSender::class, MinishlinkWebPushSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly in dev/test on N+1 queries, silently discarded attributes and missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('profile', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('messages', fn (Request $request) => [
            Limit::perMinute(20)->by($request->user()->id),
            Limit::perDay(300)->by($request->user()->id),
        ]);
        // Second-factor attempts are the brute-force target: a 6-digit code must not be guessable.
        RateLimiter::for('admin-2fa', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(30)->by($request->user()->id));

        // Participants only: strangers get a 404, so thread and message ids cannot be probed.
        Route::bind('thread', fn (string $id) => Thread::query()
            ->forParticipant(request()->user())
            ->findOrFail($id));

        Route::bind('message', fn (string $id) => Message::query()
            ->whereIn('thread_id', Thread::query()->forParticipant(request()->user())->select('id'))
            ->findOrFail($id));
    }
}
