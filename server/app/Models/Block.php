<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['receiver_id', 'sender_id'])]
class Block extends Model
{
    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $keyType = 'string';
}
