<?php

namespace App\Http\Controllers;

use App\Actions\Messaging\OpenThread;
use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\SendGuestMessage;
use App\Http\Requests\MessageBodyRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\PublicProfileResource;
use App\Messaging\GuestKey;
use App\Models\User;
use App\Support\Turnstile;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Public: powers the link page before anyone signs in.
     */
    public function show(string $username): PublicProfileResource
    {
        return new PublicProfileResource($this->findReceiver($username));
    }

    /**
     * Send an anonymous message to the owner of a link. No account needed: a signed-in sender gets a thread they
     * can chat in, a guest gets one that becomes chat-able once they sign in (see GuestController::claim).
     */
    public function send(
        MessageBodyRequest $request,
        string $username,
        OpenThread $openThread,
        PostMessage $postMessage,
        SendGuestMessage $sendGuest,
    ): JsonResponse {
        $receiver = $this->findReceiver($username);
        $sender = $request->user('sanctum');

        if ($sender) {
            abort_if($sender->isBanned(), 403, 'This account has been suspended.');

            $thread = $openThread->handle($sender, $receiver);
            $message = $postMessage->handle($thread, $sender, $request->validated('body'));

            MessageResource::viewingAs(false);

            return (new MessageResource($message))
                ->additional(['thread_id' => $thread->id])
                ->response()
                ->setStatusCode(201);
        }

        // Bots fill every field: a hidden one that must stay empty. Pretend it worked.
        if ($request->filled('website')) {
            return $this->guestAccepted(GuestKey::presented($request) ?? GuestKey::newToken());
        }

        abort_unless(
            Turnstile::passes($request->input('turnstile_token'), $request->ip()),
            422,
            'We could not verify you are human. Please try again.',
        );

        $token = GuestKey::presented($request) ?? GuestKey::newToken();
        $sendGuest->handle($receiver, $token, $request->ip(), $request->validated('body'));

        return $this->guestAccepted($token);
    }

    /** Identical for delivered and silently dropped messages. */
    private function guestAccepted(string $token): JsonResponse
    {
        return response()->json(['data' => ['sent' => true], 'guest_token' => $token], 201);
    }

    private function findReceiver(string $username): User
    {
        return User::query()
            ->where('username', $username)
            ->whereNull('banned_at')
            ->firstOrFail();
    }
}
