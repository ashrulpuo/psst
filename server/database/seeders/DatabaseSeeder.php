<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo accounts for local testing (sign in via POST /api/dev/login with PSST_DEV_LOGIN=true).
     */
    public function run(): void
    {
        $people = [
            ['alice', 'Alice Rahman', 'alice@example.test', User::ROLE_USER],
            ['bob', 'Bob Tan', 'bob@example.test', User::ROLE_USER],
            ['carol', 'Carol Lim', 'carol@example.test', User::ROLE_USER],
            ['admin', 'Site Admin', 'admin@example.test', User::ROLE_ADMIN],
        ];

        foreach ($people as [$username, $name, $email, $role]) {
            User::factory()->create(compact('username', 'name', 'email', 'role'));
        }
    }
}
