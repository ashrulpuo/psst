<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Each user may listen only to their own channel; banned accounts get nothing.
Broadcast::channel('users.{id}', fn (User $user, string $id) => $user->id === $id && ! $user->isBanned());
