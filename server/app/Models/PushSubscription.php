<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'channel', 'token', 'token_hash', 'keys', 'user_agent', 'last_seen_at'])]
class PushSubscription extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'keys' => 'encrypted:array',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
