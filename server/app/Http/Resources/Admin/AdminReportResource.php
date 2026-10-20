<?php

namespace App\Http\Resources\Admin;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relations `reporter`, `message` (with `thread`), `reportedUser` and `reviewer` are loaded by the controller.
 *
 * @mixin Report
 */
class AdminReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'reviewer' => new AdminUserBriefResource($this->whenLoaded('reviewer')),
            'reporter' => new AdminUserBriefResource($this->whenLoaded('reporter')),
            'reported_user' => new AdminUserBriefResource($this->whenLoaded('reportedUser')),
            'thread_id' => $this->whenLoaded('message', fn () => $this->message->thread_id),
            'message' => $this->whenLoaded('message', fn () => [
                'id' => $this->message->id,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at,
            ]),
        ];
    }
}
