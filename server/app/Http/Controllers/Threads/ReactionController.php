<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Messaging\ToggleReaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReactionRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReactionController extends Controller
{
    public function store(
        ReactionRequest $request,
        Message $message,
        ToggleReaction $toggle
    ): JsonResponse {
        Gate::authorize('react', $message);

        $toggle->handle($message, $request->user(), $request->validated('emoji'));

        MessageResource::viewingAs($message->thread->receiver_id === $request->user()->id);

        return (new MessageResource($message->load('reactions')))->response();
    }
}
