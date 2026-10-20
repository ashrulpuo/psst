<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'uuid'],
            'action' => ['nullable', 'string', 'max:64'],
        ]);

        $logs = AuditLog::query()
            ->with('actor:id,username,name,email,avatar_url,role,banned_at')
            ->when($filters['actor_id'] ?? null, fn ($q, string $id) => $q->where('actor_id', $id))
            ->when($filters['action'] ?? null, fn ($q, string $action) => $q->where('action', $action))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return AuditLogResource::collection($logs);
    }
}
