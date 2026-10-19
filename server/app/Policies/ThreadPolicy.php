<?php

namespace App\Policies;

use App\Models\Thread;
use App\Models\User;

/**
 * Route binding already limits threads to their participants; these rules separate the two roles.
 */
class ThreadPolicy
{
    public function manageBlock(User $user, Thread $thread): bool
    {
        return $thread->receiver_id === $user->id;
    }
}
