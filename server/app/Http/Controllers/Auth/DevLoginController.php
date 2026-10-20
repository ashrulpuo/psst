<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Local development only (see config/psst.php `dev_login`): trade an existing user's email for a login.
 * Browser requests from the SPA origin get a cookie session, like real sign-in; anything else gets a token.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request): MeResource|JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        $user = User::query()->where('email', $email)->firstOrFail();

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return new MeResource($user);
        }

        return response()->json([
            'token' => $user->createToken('dev', ['*'], now()->addDay())->plainTextToken,
            'user' => new MeResource($user),
        ]);
    }
}
