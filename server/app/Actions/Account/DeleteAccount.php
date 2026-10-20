<?php

namespace App\Actions\Account;

use App\Models\AuditLog;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently erases a user and everything they wrote or received.
 *
 * Both sides of every conversation go: the user's inbox, and the messages they sent to others (so nothing
 * traceable to them survives). Reports about those messages go with them. The only residue is an
 * audit record holding the opaque id, never a name, email or message text.
 */
class DeleteAccount
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Threads reference users with RESTRICT on purpose; messages, reports and blocks cascade from here.
            Thread::query()
                ->where('receiver_id', $user->id)
                ->orWhere('sender_id', $user->id)
                ->delete();

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();

            // Identities, push subscriptions, blocks and the user's own reports cascade in the database.
            $user->delete();

            AuditLog::create([
                'actor_id' => null,
                'action' => 'account.deleted',
                'subject_type' => 'User',
                'subject_id' => $user->id,
            ]);
        });
    }
}
