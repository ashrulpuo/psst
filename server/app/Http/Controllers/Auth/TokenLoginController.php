<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SignInWithIdentity;
use App\Auth\IdTokenVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;

/**
 * Mobile and desktop sign-in: same verification, but returns an expiring bearer token.
 */
class TokenLoginController extends Controller
{
    public function __invoke(
        SignInRequest $request,
        string $provider,
        IdTokenVerifier $verifier,
        SignInWithIdentity $signIn,
    ): JsonResponse {
        $request->validate(['device_name' => ['required']]);

        $identity = $verifier->verify($provider, $request->string('id_token')->value(), $request->string('nonce')->value(), $request->input('name'));
        $user = $signIn->handle($identity);

        $token = $user->createToken(
            $request->string('device_name')->value(),
            ['*'],
            now()->addDays(config('psst.auth.token_ttl_days')),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new MeResource($user),
        ]);
    }
}
