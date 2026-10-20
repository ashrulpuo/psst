<?php

namespace App\Actions\Admin;

use App\Admin\AuditLogger;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BanUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Suspend an account and cut every way it could still act: API tokens, browser sessions, push devices.
     */
    public function ban(Request $request, User $target, string $reason): void
    {
        if ($target->isAdmin()) {
            throw new HttpException(422, 'Admins cannot be banned. Remove the admin role first.');
        }

        DB::transaction(function () use ($request, $target, $reason) {
            $target->forceFill(['banned_at' => now(), 'ban_reason' => $reason])->save();

            $target->tokens()->delete();
            DB::table('sessions')->where('user_id', $target->id)->delete();
            PushSubscription::query()->where('user_id', $target->id)->delete();

            $this->audit->record($request, 'user.banned', $target, ['reason' => $reason]);
        });
    }

    public function unban(Request $request, User $target): void
    {
        DB::transaction(function () use ($request, $target) {
            $target->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
            $this->audit->record($request, 'user.unbanned', $target);
        });
    }
}
