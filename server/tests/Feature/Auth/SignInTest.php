<?php

namespace Tests\Feature\Auth;

use App\Models\Identity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeOidcProvider;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    /** A browser request from the configured SPA origin, which is what enables cookie sessions. */
    private function spa(): static
    {
        return $this->withHeader('Origin', 'http://localhost:3000');
    }

    private function body(string $token, array $extra = []): array
    {
        return ['id_token' => $token, 'nonce' => FakeOidcProvider::NONCE, ...$extra];
    }

    public function test_google_sign_in_creates_a_user_and_starts_a_session(): void
    {
        $google = new FakeOidcProvider('google');

        $this->spa()->postJson('/api/auth/google', $this->body($google->token()))
            ->assertOk()
            ->assertJsonPath('data.email', 'sam@example.com')
            ->assertJsonPath('data.needs_username', true);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'sam@example.com', 'role' => 'user']);
        $this->assertDatabaseHas('identities', ['provider' => 'google', 'provider_user_id' => 'provider-subject-1']);
    }

    public function test_mobile_token_sign_in_returns_a_working_bearer_token(): void
    {
        $google = new FakeOidcProvider('google');

        $token = $this->postJson('/api/auth/google/token', $this->body($google->token(), ['device_name' => 'Pixel 9']))
            ->assertOk()
            ->json('token');

        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'sam@example.com');
    }

    public function test_cookie_sign_in_is_refused_outside_the_web_app(): void
    {
        $google = new FakeOidcProvider('google');

        $this->postJson('/api/auth/google', $this->body($google->token()))->assertStatus(400);

        $this->assertSame(0, User::count());
    }

    public function test_token_sign_in_requires_a_device_name(): void
    {
        $google = new FakeOidcProvider('google');

        $this->postJson('/api/auth/google/token', $this->body($google->token()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_name');
    }

    public function test_apple_accepts_a_hashed_nonce_and_string_email_verified(): void
    {
        $apple = new FakeOidcProvider('apple');

        $token = $apple->token([
            'nonce' => hash('sha256', FakeOidcProvider::NONCE),
            'email_verified' => 'true',
            'name' => null,
        ]);

        $this->spa()->postJson('/api/auth/apple', $this->body($token, ['name' => 'Ada Lovelace']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Lovelace');
    }

    public function test_signing_in_again_reuses_the_same_user(): void
    {
        $google = new FakeOidcProvider('google');

        $this->spa()->postJson('/api/auth/google', $this->body($google->token()))->assertOk();
        $this->spa()->postJson('/api/auth/google', $this->body($google->token()))->assertOk();

        $this->assertSame(1, User::count());
        $this->assertSame(1, Identity::count());
    }

    public function test_a_second_provider_with_the_same_verified_email_links_to_the_existing_user(): void
    {
        $google = new FakeOidcProvider('google');
        $apple = new FakeOidcProvider('apple');

        $this->spa()->postJson('/api/auth/google', $this->body($google->token()))->assertOk();
        $this->spa()->postJson('/api/auth/apple', $this->body($apple->token(['sub' => 'apple-subject'])))->assertOk();

        $this->assertSame(1, User::count());
        $this->assertSame(2, Identity::count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function badClaims(): array
    {
        return [
            'wrong audience' => [['aud' => 'someone-elses-client']],
            'wrong issuer' => [['iss' => 'https://evil.example.com']],
            'expired' => [['exp' => time() - 3600, 'iat' => time() - 7200]],
            'wrong nonce' => [['nonce' => 'a-different-nonce-entirely']],
            'missing nonce' => [['nonce' => null]],
            'email not verified' => [['email_verified' => false]],
            'missing email' => [['email' => null]],
            'missing subject' => [['sub' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    #[DataProvider('badClaims')]
    public function test_tokens_with_bad_claims_are_rejected_generically(array $claims): void
    {
        $google = new FakeOidcProvider('google');

        $this->spa()->postJson('/api/auth/google', $this->body($google->token($claims)))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Sign-in failed. Please try again.']);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_a_token_signed_by_another_key_is_rejected(): void
    {
        $google = new FakeOidcProvider('google');

        $this->spa()->postJson('/api/auth/google', $this->body($google->token(signWith: FakeOidcProvider::strangerKey())))
            ->assertUnauthorized();

        $this->assertSame(0, User::count());
    }

    public function test_garbage_tokens_are_rejected(): void
    {
        new FakeOidcProvider('google');

        $this->spa()->postJson('/api/auth/google', $this->body('not.a.jwt'))->assertUnauthorized();
        $this->spa()->postJson('/api/auth/google', $this->body('x'))->assertUnauthorized();
    }

    public function test_an_unconfigured_provider_rejects_everything(): void
    {
        $google = new FakeOidcProvider('google');
        $token = $google->token();
        config(['services.oidc.google.client_ids' => []]);

        $this->spa()->postJson('/api/auth/google', $this->body($token))->assertUnauthorized();
    }

    public function test_unknown_providers_do_not_route(): void
    {
        $this->postJson('/api/auth/facebook', $this->body('x'))->assertNotFound();
    }

    public function test_banned_users_cannot_sign_in(): void
    {
        $google = new FakeOidcProvider('google');
        User::factory()->banned()->create(['email' => 'sam@example.com']);

        $this->spa()->postJson('/api/auth/google', $this->body($google->token()))->assertForbidden();

        $this->assertGuest();
    }

    public function test_banned_users_with_a_live_token_are_locked_out(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;
        $user->forceFill(['banned_at' => now()])->save();

        $this->withToken($token)->getJson('/api/me')->assertForbidden();
    }

    public function test_sign_in_is_rate_limited(): void
    {
        new FakeOidcProvider('google');

        for ($i = 0; $i < 10; $i++) {
            $this->spa()->postJson('/api/auth/google', $this->body('x'))->assertUnauthorized();
        }

        $this->spa()->postJson('/api/auth/google', $this->body('x'))->assertTooManyRequests();
    }

    public function test_logout_revokes_the_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
