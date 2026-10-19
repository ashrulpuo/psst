<?php

namespace App\Http\Resources;

use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A thread as the SENDER sees it: the person they wrote to (public profile only).
 *
 * @mixin Thread
 */
class SentThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        MessageResource::viewingAs(false);

        return [
            'id' => $this->id,
            'receiver' => new PublicProfileResource($this->whenLoaded('receiver')),
            'unread_count' => $this->sender_unread_count,
            'can_reply' => $this->blocked_at === null,
            'last_message_at' => $this->last_message_at,
            'last_message' => new MessageResource($this->whenLoaded('latestMessage')),
        ];
    }
}
