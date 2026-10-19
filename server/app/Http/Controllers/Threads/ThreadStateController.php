<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Messaging\BlockSender;
use App\Actions\Messaging\MarkThreadRead;
use App\Http\Controllers\Controller;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ThreadStateController extends Controller
{
    public function read(Request $request, Thread $thread, MarkThreadRead $markRead): Response
    {
        $markRead->handle($thread, $request->user());

        return response()->noContent();
    }

    public function block(Thread $thread, BlockSender $blocks): Response
    {
        Gate::authorize('manageBlock', $thread);
        $blocks->block($thread);

        return response()->noContent();
    }

    public function unblock(Thread $thread, BlockSender $blocks): Response
    {
        Gate::authorize('manageBlock', $thread);
        $blocks->unblock($thread);

        return response()->noContent();
    }
}
