<?php

namespace App\Http\Controllers;

use App\Actions\Messaging\ReportMessage;
use App\Http\Requests\ReportMessageRequest;
use App\Models\Message;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function store(ReportMessageRequest $request, Message $message, ReportMessage $reportMessage): Response
    {
        Gate::authorize('report', $message);
        $reportMessage->handle($message, $request->user(), $request->validated('reason'));

        return response()->noContent(202);
    }
}
