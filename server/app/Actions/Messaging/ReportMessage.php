<?php

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\Report;
use App\Models\User;

class ReportMessage
{
    /**
     * Idempotent: reporting the same message twice keeps the first report.
     */
    public function handle(Message $message, User $reporter, string $reason): Report
    {
        return Report::query()->firstOrCreate(
            ['reporter_id' => $reporter->id, 'message_id' => $message->id],
            ['reason' => $reason],
        );
    }
}
