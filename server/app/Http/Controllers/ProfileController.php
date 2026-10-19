<?php

namespace App\Http\Controllers;

use App\Actions\Messaging\OpenThread;
use App\Actions\Messaging\PostMessage;
use App\Http\Requests\MessageBodyRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\PublicProfileResource;
use App\Models\User;
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
     * Send a first (or follow-up) anonymous message to the owner of a link.
     */
    public function send(
        MessageBodyRequest $request,
        string $username,
        OpenThread $openThread,
        PostMessage $postMessage,
    ): JsonResponse {
        $sender = $request->user();
        $thread = $openThread->handle($sender, $this->findReceiver($username));
        $message = $postMessage->handle($thread, $sender, $request->validated('body'));

        MessageResource::viewingAs(false);

        return (new MessageResource($message))
            ->additional(['thread_id' => $thread->id])
            ->response()
            ->setStatusCode(201);
    }

    private function findReceiver(string $username): User
    {
        return User::query()
            ->where('username', $username)
            ->whereNull('banned_at')
            ->firstOrFail();
    }
}
