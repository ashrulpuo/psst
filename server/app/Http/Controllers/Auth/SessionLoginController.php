<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SignInWithIdentity;
use App\Auth\IdTokenVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Resources\MeResource;
use Illuminate\Support\Facades\Auth;

/**
 * Browser sign-in: verifies the provider ID token, then starts a cookie session (Sanctum SPA mode).
 */
class SessionLoginController extends Controller
{
    public function __invoke(
        SignInRequest $request,
        string $provider,
        IdTokenVerifier $verifier,
        SignInWithIdentity $signIn,
    ): MeResource {
        // Sanctum only starts a session for requests from a configured SPA origin (SANCTUM_STATEFUL_DOMAINS).
        abort_unless($request->hasSession(), 400, 'Cookie sign-in is only available to the web app. Use the token endpoint.');

        $identity = $verifier->verify($provider, $request->string('id_token')->value(), $request->string('nonce')->value(), $request->input('name'));
        $user = $signIn->handle($identity);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new MeResource($user);
    }
}
