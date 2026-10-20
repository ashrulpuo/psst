<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PushController extends Controller
{
    /**
     * Public key the browser needs to subscribe. `null` means push is not configured on this server.
     */
    public function config(): JsonResponse
    {
        return response()->json(['data' => ['public_key' => config('services.vapid.public_key') ?: null]]);
    }

    /**
     * Register this device. Idempotent by endpoint; a device that changes hands moves to the new account,
     * so notifications never keep arriving for the previous user.
     */
    public function store(StorePushSubscriptionRequest $request): Response
    {
        $user = $request->user();
        $endpoint = $request->validated('endpoint');

        PushSubscription::query()->updateOrCreate(
            ['channel' => 'webpush', 'token_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $user->id,
                'token' => $endpoint,
                'keys' => $request->validated('keys'),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'last_seen_at' => now(),
            ],
        );

        // Keep only the most recently seen devices.
        $stale = PushSubscription::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->skip((int) config('psst.push.max_subscriptions_per_user'))
            ->take(PHP_INT_MAX)
            ->pluck('id');
        PushSubscription::query()->whereIn('id', $stale)->delete();

        return response()->noContent();
    }

    public function destroy(DeletePushSubscriptionRequest $request): Response
    {
        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('channel', 'webpush')
            ->where('token_hash', hash('sha256', $request->validated('endpoint')))
            ->delete();

        return response()->noContent();
    }
}
