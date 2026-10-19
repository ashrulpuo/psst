<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['receiver_id', 'sender_id', 'anon_label'])]
class Thread extends Model
{
    use HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = [
        'receiver_unread_count' => 0,
        'sender_unread_count' => 0,
        'blocked_at' => null,
        'last_message_at' => null,
        'last_message_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Real sender identity. Admin-only: never expose through receiver-facing resources.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function latestMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /**
     * Threads the user takes part in, as either receiver or sender.
     *
     * @param  Builder<Thread>  $query
     */
    public function scopeForParticipant(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('receiver_id', $user->id)->orWhere('sender_id', $user->id));
    }
}
