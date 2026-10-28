<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thread_id', 'user_id'])]
class ThreadHide extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Thread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
