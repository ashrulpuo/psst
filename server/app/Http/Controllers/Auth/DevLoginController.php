<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Local development only (see config/psst.php `dev_login`): trade an existing user's email for a token.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validate(['email' => ['required', 'email']])['email'])->firstOrFail();

        return response()->json([
            'token' => $user->createToken('dev', ['*'], now()->addDay())->plainTextToken,
            'user' => new MeResource($user),
        ]);
    }
}
