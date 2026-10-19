<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_can_claim_a_username_once(): void
    {
        Sanctum::actingAs(User::factory()->create(['username' => null]));

        $this->patchJson('/api/me/username', ['username' => '  Sam_Example '])
            ->assertOk()
            ->assertJsonPath('data.username', 'sam_example')
            ->assertJsonPath('data.needs_username', false);

        $this->patchJson('/api/me/username', ['username' => 'another_one'])->assertStatus(409);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidUsernames(): array
    {
        return [
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 25)],
            'spaces' => ['sam example'],
            'symbols' => ['sam!'],
            'reserved' => ['admin'],
            'reserved mixed case' => ['Support'],
        ];
    }

    #[DataProvider('invalidUsernames')]
    public function test_invalid_usernames_are_rejected(string $username): void
    {
        Sanctum::actingAs(User::factory()->create(['username' => null]));

        $this->patchJson('/api/me/username', ['username' => $username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_taken_usernames_are_rejected_case_insensitively(): void
    {
        User::factory()->create(['username' => 'taken_name']);
        Sanctum::actingAs(User::factory()->create(['username' => null]));

        $this->patchJson('/api/me/username', ['username' => 'TAKEN_name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_the_role_cannot_be_changed_through_the_api(): void
    {
        $user = User::factory()->create(['username' => null]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/me/username', ['username' => 'sneaky', 'role' => 'admin'])->assertOk();

        $this->assertSame('user', $user->fresh()->role);
    }
}
