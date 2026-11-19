<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\MessageFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlagController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', 'in:open,reviewed']])['status'] ?? 'open';

        $flags = MessageFlag::query()
            ->with('message.thread:id,sender_id')
            ->when($status === 'open', fn ($q) => $q->whereNull('reviewed_at')->orderBy('created_at'))
            ->when($status === 'reviewed', fn ($q) => $q->whereNotNull('reviewed_at')->orderByDesc('reviewed_at'))
            ->orderBy('id')
            ->cursorPaginate(25);

        // The list shows message text, so opening it is audited like opening a thread's messages (first page only).
        if (! $request->filled('cursor')) {
            $this->audit->record($request, 'flags.viewed', null, ['status' => $status]);
        }

        $data = $flags->getCollection()->map(fn (MessageFlag $f) => [
            'id' => $f->id,
            'message_id' => $f->message_id,
            'thread_id' => $f->message->thread_id,
            'sender_kind' => $f->message->thread->sender_id === null ? 'guest' : 'user',
            'terms' => $f->terms,
            'body' => $f->message->body,
            'created_at' => $f->created_at,
            'reviewed_at' => $f->reviewed_at,
        ]);

        return response()->json([
            'data' => $data,
            'links' => ['next' => $flags->nextPageUrl(), 'prev' => $flags->previousPageUrl()],
            'meta' => ['per_page' => $flags->perPage(), 'next_cursor' => $flags->nextCursor()?->encode(), 'prev_cursor' => $flags->previousCursor()?->encode()],
        ]);
    }

    public function review(Request $request, MessageFlag $flag): JsonResponse
    {
        abort_if($flag->reviewed_at !== null, 409, 'This flag was already reviewed.');

        $flag->forceFill(['reviewed_at' => now(), 'reviewed_by' => $request->user()->id])->save();
        $this->audit->record($request, 'flag.reviewed', $flag);

        return response()->json(['data' => ['id' => $flag->id, 'reviewed_at' => $flag->reviewed_at]]);
    }
}
