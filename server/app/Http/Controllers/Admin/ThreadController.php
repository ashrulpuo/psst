<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminMessageResource;
use App\Http\Resources\Admin\AdminThreadResource;
use App\Models\Message;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ThreadController extends Controller
{
    private const USER_COLUMNS = 'id,username,name,email,avatar_url,role,banned_at';

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'uuid'],
            'blocked' => ['nullable', 'boolean'],
        ]);

        $threads = Thread::query()
            ->with(['receiver:'.self::USER_COLUMNS, 'sender:'.self::USER_COLUMNS])
            ->when($filters['user_id'] ?? null, fn ($q, string $id) => $q->where(fn ($w) => $w->where('receiver_id', $id)->orWhere('sender_id', $id)))
            ->when($request->boolean('blocked'), fn ($q) => $q->whereNotNull('blocked_at'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->cursorPaginate(25);

        return AdminThreadResource::collection($threads);
    }

    public function show(Request $request, Thread $adminThread): JsonResponse
    {
        $thread = $adminThread;
        $thread->load(['receiver:'.self::USER_COLUMNS, 'sender:'.self::USER_COLUMNS]);
        $this->audit->record($request, 'thread.viewed', $thread);

        return response()->json(['data' => [
            ...(new AdminThreadResource($thread))->resolve($request),
            'message_count' => Message::query()->where('thread_id', $thread->id)->count(),
        ]]);
    }

    /**
     * Message text is the most sensitive thing an admin can read, so opening a thread's messages is audited
     * (once per conversation open, not per scrolled page).
     */
    public function messages(Request $request, Thread $adminThread): AnonymousResourceCollection
    {
        $thread = $adminThread;

        if (! $request->filled('cursor')) {
            $this->audit->record($request, 'thread.messages_viewed', $thread);
        }

        return AdminMessageResource::collection(
            $thread->messages()->orderByDesc('id')->cursorPaginate(50)
        );
    }
}
