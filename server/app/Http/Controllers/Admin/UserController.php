<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BanUser;
use App\Admin\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BanUser $bans,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,admin,banned'],
        ]);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, string $term) {
                // Escape LIKE wildcards so a search for "100%" is literal.
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(fn ($q) => $q->where('username', 'ilike', $like)->orWhere('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
            })
            ->when(($filters['status'] ?? 'all') === 'admin', fn ($q) => $q->where('role', User::ROLE_ADMIN))
            ->when(($filters['status'] ?? 'all') === 'banned', fn ($q) => $q->whereNotNull('banned_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(25);

        return AdminUserResource::collection($users);
    }

    /**
     * Opening a profile reveals identity and activity, so it is audited.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->audit->record($request, 'user.viewed', $user);

        return response()->json(['data' => [
            ...(new AdminUserResource($user))->resolve($request),
            'providers' => $user->identities()->pluck('provider')->all(),
            'stats' => [
                'received_threads' => Thread::query()->where('receiver_id', $user->id)->count(),
                'sent_threads' => Thread::query()->where('sender_id', $user->id)->count(),
                'messages_sent' => Message::query()
                    ->join('threads', 'threads.id', '=', 'messages.thread_id')
                    ->where(fn ($q) => $q
                        ->where(fn ($s) => $s->where('threads.sender_id', $user->id)->where('messages.from_receiver', false))
                        ->orWhere(fn ($r) => $r->where('threads.receiver_id', $user->id)->where('messages.from_receiver', true)))
                    ->count(),
                'reports_filed' => Report::query()->where('reporter_id', $user->id)->count(),
                'reports_against' => $this->reportsAgainst($user),
                'push_devices' => PushSubscription::query()->where('user_id', $user->id)->count(),
            ],
        ]]);
    }

    public function ban(Request $request, User $user): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        abort_if($user->is($request->user()), 422, 'You cannot ban yourself.');
        $this->bans->ban($request, $user, $reason);

        return response()->json(['data' => (new AdminUserResource($user->refresh()))->resolve($request)]);
    }

    public function unban(Request $request, User $user): JsonResponse
    {
        $this->bans->unban($request, $user);

        return response()->json(['data' => (new AdminUserResource($user->refresh()))->resolve($request)]);
    }

    /** Reports whose reported message was written by this user. */
    private function reportsAgainst(User $user): int
    {
        return Report::query()
            ->join('messages', 'messages.id', '=', 'reports.message_id')
            ->join('threads', 'threads.id', '=', 'messages.thread_id')
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('threads.sender_id', $user->id)->where('messages.from_receiver', false))
                ->orWhere(fn ($r) => $r->where('threads.receiver_id', $user->id)->where('messages.from_receiver', true)))
            ->count();
    }
}
