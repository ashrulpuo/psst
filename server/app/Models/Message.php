<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thread_id', 'from_receiver', 'body'])]
class Message extends Model
{
    use HasUuids;

    public const MAX_BODY_LENGTH = 1000;

    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = ['read_at' => null];

    protected function casts(): array
    {
        return [
            'from_receiver' => 'boolean',
            'body' => 'encrypted',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Thread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
