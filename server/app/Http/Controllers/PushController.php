<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushDeviceRequest;
use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushDeviceRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PushController extends Controller
{
    /**
     * What this server can push to. `public_key` is what browsers need to subscribe (null = Web Push is off);
     * `fcm` says whether the native apps will receive pushes.
     */
    public function config(): JsonResponse
    {
        return response()->json(['data' => [
            'public_key' => config('services.vapid.public_key') ?: null,
            'fcm' => (bool) (config('services.fcm.project_id') && config('services.fcm.credentials')),
        ]]);
    }

    /**
     * Register this device. Idempotent by endpoint; a device that changes hands moves to the new account,
     * so notifications never keep arriving for the previous user.
     */
    public function store(StorePushSubscriptionRequest $request): Response
    {
        $this->register($request->user(), 'webpush', $request->validated('endpoint'), $request->validated('keys'), (string) $request->userAgent());

        return response()->noContent();
    }

    /**
     * Register a native (iOS/Android) device by its FCM token. Same hand-over rule as the browser endpoint.
     */
    public function storeDevice(StorePushDeviceRequest $request): Response
    {
        $this->register($request->user(), 'fcm', $request->validated('token'), null, 'capacitor-'.$request->validated('platform'));

        return response()->noContent();
    }

    public function destroyDevice(DeletePushDeviceRequest $request): Response
    {
        $this->unregister($request->user(), 'fcm', $request->validated('token'));

        return response()->noContent();
    }

    public function destroy(DeletePushSubscriptionRequest $request): Response
    {
        $this->unregister($request->user(), 'webpush', $request->validated('endpoint'));

        return response()->noContent();
    }

    /**
     * Idempotent by (channel, token). A device that changes hands moves to the new account, so notifications
     * never keep arriving for the previous user.
     *
     * @param  array<string, string>|null  $keys
     */
    private function register(User $user, string $channel, string $token, ?array $keys, string $userAgent): void
    {
        PushSubscription::query()->updateOrCreate(
            ['channel' => $channel, 'token_hash' => hash('sha256', $token)],
            [
                'user_id' => $user->id,
                'token' => $token,
                'keys' => $keys,
                'user_agent' => Str::limit($userAgent, 255, ''),
                'last_seen_at' => now(),
            ],
        );

        // Keep only the most recently seen devices (across all channels).
        $stale = PushSubscription::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->skip((int) config('psst.push.max_subscriptions_per_user'))
            ->take(PHP_INT_MAX)
            ->pluck('id');
        PushSubscription::query()->whereIn('id', $stale)->delete();
    }

    private function unregister(User $user, string $channel, string $token): void
    {
        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('token_hash', hash('sha256', $token))
            ->delete();
    }
}
