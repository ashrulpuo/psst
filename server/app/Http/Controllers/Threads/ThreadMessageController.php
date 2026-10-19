<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Messaging\PostMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageBodyRequest;
use App\Http\Resources\MessageResource;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ThreadMessageController extends Controller
{
    private const PAGE_SIZE = 30;

    /**
     * Newest first; follow `next_cursor` for older messages (keyset pagination).
     */
    public function index(Request $request, Thread $thread): AnonymousResourceCollection
    {
        MessageResource::viewingAs($thread->receiver_id === $request->user()->id);

        return MessageResource::collection(
            $thread->messages()->orderByDesc('id')->cursorPaginate(self::PAGE_SIZE)
        );
    }

    public function store(MessageBodyRequest $request, Thread $thread, PostMessage $postMessage): JsonResponse
    {
        $message = $postMessage->handle($thread, $request->user(), $request->validated('body'));

        MessageResource::viewingAs($thread->receiver_id === $request->user()->id);

        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
