<?php

namespace App\Http\Resources\Admin;

use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A thread with BOTH real identities. Admin-only: the receiver-facing resources never include the sender.
 *
 * @mixin Thread
 */
class AdminThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => 'Anonymous #'.$this->anon_label,
            'receiver' => new AdminUserBriefResource($this->whenLoaded('receiver')),
            // Null for a guest: they never signed in, so there is no account (and no identity) to reveal.
            'sender' => $this->sender_id === null ? null : new AdminUserBriefResource($this->whenLoaded('sender')),
            'sender_kind' => $this->sender_id === null ? 'guest' : 'user',
            'blocked_at' => $this->blocked_at,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
        ];
    }
}
