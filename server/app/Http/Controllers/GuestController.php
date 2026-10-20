<?php

namespace App\Http\Controllers;

use App\Actions\Messaging\ClaimGuestThreads;
use App\Messaging\GuestKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
