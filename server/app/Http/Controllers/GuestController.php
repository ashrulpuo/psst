<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ClaimLink;
use App\Actions\Messaging\ClaimGuestThreads;
use App\Http\Requests\SetUsernameRequest;
use App\Http\Resources\MeResource;
use App\Messaging\GuestKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestController extends Controller
{
    /**
     * After sign-in: attach this browser's anonymous threads to the account so receivers can reply.
     */
    public function claim(Request $request, ClaimGuestThreads $claim): JsonResponse
    {
        $token = GuestKey::presented($request);

        return response()->json(['data' => ['claimed' => $token ? $claim->handle($request->user(), $token) : 0]]);
    }

    /**
     * Pick a username and keep the link in this browser. No Google or Apple account yet.
     */
    public function link(Request $request, ClaimLink $claim): JsonResponse
    {
        $token = GuestKey::presented($request) ?? GuestKey::newToken();

        if ($existing = $claim->find($token)) {
            return $this->start($request, $existing, $token);
        }

        $username = is_string($request->input('username'))
            ? mb_strtolower(trim($request->input('username')))
            : $request->input('username');

        $validated = validator(
            ['username' => $username],
            (new SetUsernameRequest)->rules(),
            (new SetUsernameRequest)->messages(),
        )->validate();

        return $this->start($request, $claim->handle($token, $validated['username']), $token);
    }

    /**
     * Open the link this browser already claimed, after the cookie session has gone.
     */
    public function resume(Request $request, ClaimLink $claim): JsonResponse
    {
        $token = GuestKey::presented($request);
        $user = $token ? $claim->find($token) : null;
        abort_unless($user, 404);

        return $this->start($request, $user, $token);
    }

    private function start(Request $request, User $user, string $token): JsonResponse
    {
        abort_if($user->isBanned(), 403, 'This account has been suspended.');

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return (new MeResource($user))
            ->additional(['guest_token' => $token])
            ->response();
    }
}
