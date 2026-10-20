<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @mixin User
 */
class AdminUserResource extends AdminUserBriefResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'ban_reason' => $this->ban_reason,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'two_factor' => $this->hasTwoFactor(),
        ];
    }
}
