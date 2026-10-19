<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What anyone may know about a user: enough to render their link page. No id, no email.
 *
 * @mixin User
 */
class PublicProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'username' => $this->username,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
