<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A message as seen by one participant. Authorship is relative ("mine"), never an identity.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    public const VIEWER_IS_RECEIVER = 'psst.viewer_is_receiver';

    /**
     * Declare who is looking before returning messages. Stored on the app's current request, which
     * is the one resources read from (a FormRequest instance is a different object).
     */
    public static function viewingAs(bool $isReceiver): void
    {
        request()->attributes->set(self::VIEWER_IS_RECEIVER, $isReceiver);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerIsReceiver = (bool) $request->attributes->get(self::VIEWER_IS_RECEIVER, false);

        return [
            'id' => $this->id,
            'body' => $this->body,
            'mine' => $this->from_receiver === $viewerIsReceiver,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
