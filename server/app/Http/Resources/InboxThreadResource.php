<?php

namespace App\Http\Resources;

use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A thread as the RECEIVER sees it. The sender is only ever "Anonymous #N".
 * Do not add sender fields here; tests assert none of them leak.
 *
 * @mixin Thread
 */
class InboxThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        MessageResource::viewingAs(true);

        return [
            'id' => $this->id,
            'label' => 'Anonymous #'.$this->anon_label,
            'unread_count' => $this->receiver_unread_count,
            'blocked' => $this->blocked_at !== null,
            // False while the sender has not signed in: there is no one to reply to yet.
            'can_chat' => $this->sender_id !== null,
            'last_message_at' => $this->last_message_at,
            'last_message' => new MessageResource($this->whenLoaded('latestMessage')),
        ];
    }
}
