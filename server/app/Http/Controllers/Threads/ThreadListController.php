<?php

namespace App\Http\Controllers\Threads;

use App\Http\Controllers\Controller;
use App\Http\Resources\InboxThreadResource;
use App\Http\Resources\SentThreadResource;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ThreadListController extends Controller
{
    private const PAGE_SIZE = 20;

    /**
     * Threads where I am the receiver. Senders appear only as "Anonymous #N".
     */
    public function inbox(Request $request): AnonymousResourceCollection
    {
        $threads = Thread::query()
            ->where('receiver_id', $request->user()->id)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PAGE_SIZE);

        return InboxThreadResource::collection($threads);
    }

    /**
     * Threads where I am the sender.
     */
    public function sent(Request $request): AnonymousResourceCollection
    {
        $threads = Thread::query()
            ->where('sender_id', $request->user()->id)
            ->with(['latestMessage', 'receiver:id,username,name,avatar_url'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PAGE_SIZE);

        return SentThreadResource::collection($threads);
    }
}
