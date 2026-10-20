<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('psst:make-admin {email : Email of an existing account}')]
#[Description('Give an account the admin role (they still must set up two-factor on first admin sign-in)')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account with that email. Ask them to sign in to Psst once first.');

            return self::FAILURE;
        }

        $user->forceFill(['role' => User::ROLE_ADMIN])->save();
        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
