<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person as admins see them: real identity included. Never return this from a non-admin endpoint.
 *
 * @mixin User
 */
class AdminUserBriefResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role,
            'banned_at' => $this->banned_at,
        ];
    }
}
