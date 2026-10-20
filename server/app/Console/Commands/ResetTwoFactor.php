<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('psst:reset-2fa {email : Email of the admin who lost their authenticator and recovery codes}')]
#[Description("Remove an admin's two-factor setup so they can enrol again (run only after verifying who is asking)")]
class ResetTwoFactor extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        $this->info("Two-factor reset for {$user->email}. They will be asked to set it up again.");

        return self::SUCCESS;
    }
}
