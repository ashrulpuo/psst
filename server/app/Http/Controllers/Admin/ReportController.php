<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BanUser;
use App\Admin\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminMessageResource;
use App\Http\Resources\Admin\AdminReportResource;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const USER_COLUMNS = 'id,username,name,email,avatar_url,role,banned_at';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BanUser $bans,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->validate(['status' => ['nullable', 'in:open,reviewed,dismissed,all']])['status'] ?? 'open';

        $reports = Report::query()
            ->with(['reporter:'.self::USER_COLUMNS, 'reviewer:'.self::USER_COLUMNS, 'message.thread'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            // Oldest open reports first: that is the order a moderator works the queue in.
            ->orderBy($status === 'open' ? 'created_at' : 'reviewed_at', $status === 'open' ? 'asc' : 'desc')
            ->orderBy('id')
            ->cursorPaginate(25);

        $this->attachReportedUsers($reports->getCollection());

        return AdminReportResource::collection($reports);
    }

    /**
     * The report plus the conversation around the reported message, so a moderator can judge it in context.
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        $report->load(['reporter:'.self::USER_COLUMNS, 'reviewer:'.self::USER_COLUMNS, 'message.thread']);
        $this->attachReportedUsers(new Collection([$report]));
        $this->audit->record($request, 'report.viewed', $report);

        $threadId = $report->message->thread_id;
        $before = Message::query()->where('thread_id', $threadId)->where('id', '<=', $report->message_id)->orderByDesc('id')->limit(6)->get();
        $after = Message::query()->where('thread_id', $threadId)->where('id', '>', $report->message_id)->orderBy('id')->limit(3)->get();
        $context = $before->reverse()->concat($after)->values();

        return response()->json(['data' => [
            ...(new AdminReportResource($report))->resolve($request),
            'context' => AdminMessageResource::collection($context)->resolve($request),
        ]]);
    }

    public function resolve(Request $request, Report $report): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:reviewed,dismissed'],
            'ban_author' => ['sometimes', 'boolean'],
        ]);

        abort_if($report->status !== 'open', 409, 'This report was already handled.');

        DB::transaction(function () use ($request, $report, $data) {
            $report->forceFill([
                'status' => $data['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->record($request, 'report.'.$data['status'], $report);

            if ($request->boolean('ban_author') && $data['status'] === 'reviewed') {
                $report->load('message.thread');
                $this->attachReportedUsers(new Collection([$report]));
                $author = $report->reportedUser;

                abort_if($author->is($request->user()), 422, 'You cannot ban yourself.');
                $this->bans->ban($request, $author, 'Report '.$report->id);
            }
        });

        $report->load(['reporter:'.self::USER_COLUMNS, 'reviewer:'.self::USER_COLUMNS, 'message.thread']);
        $this->attachReportedUsers(new Collection([$report]));

        return response()->json(['data' => (new AdminReportResource($report))->resolve($request)]);
    }

    /**
     * The reported user is whoever wrote the message. Resolved in one query for the whole page.
     *
     * @param  Collection<int, Report>  $reports
     */
    private function attachReportedUsers(Collection $reports): void
    {
        $authorIds = $reports->map(fn (Report $r) => $r->message->from_receiver ? $r->message->thread->receiver_id : $r->message->thread->sender_id)->unique();
        $users = User::query()->select(explode(',', self::USER_COLUMNS))->whereIn('id', $authorIds)->get()->keyBy('id');

        foreach ($reports as $report) {
            $id = $report->message->from_receiver ? $report->message->thread->receiver_id : $report->message->thread->sender_id;
            $report->setRelation('reportedUser', $users[$id]);
        }
    }
}
