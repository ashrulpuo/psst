<?php

namespace App\Admin;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Every identity reveal and moderation action by an admin is recorded here (append-only table).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(Request $request, string $action, ?Model $subject = null, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'ip' => $request->ip(),
        ]);
    }
}
