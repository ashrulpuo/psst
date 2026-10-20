<?php

namespace Tests\Feature\Admin;

use App\Admin\Totp;
use App\Admin\TwoFactor;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class AdminAccessTest extends AdminTestCase
{
    // ---- the access matrix ----

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/admin/overview')->assertUnauthorized();
        $this->getJson('/api/admin/2fa')->assertUnauthorized();
    }

    public function test_regular_users_are_forbidden_everywhere(): void
    {
        $user = User::factory()->create();

        foreach (['overview', 'users', 'threads', 'reports', 'audit', '2fa'] as $path) {
            Sanctum::actingAs($user);
            $this->withHeader('Origin', 'http://localhost:3000')->getJson("/api/admin/$path")->assertForbidden();
        }
    }

    public function test_an_admin_bearer_token_is_refused_because_it_would_skip_the_second_factor(): void
    {
        $token = $this->admin->createToken('script')->plainTextToken;
        $this->admin->save();

        // No Origin header: not a browser session, so no session to hold the 2FA proof.
        $this->withToken($token)->getJson('/api/admin/overview')->assertForbidden();
    }

    public function test_an_admin_without_two_factor_is_told_to_set_it_up(): void
    {
        $fresh = User::factory()->admin()->create();

        $this->asAdmin($fresh, verified: false)->getJson('/api/admin/overview')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_setup_required');
    }

    public function test_an_enrolled_admin_must_verify_in_this_session(): void
    {
        $this->asAdmin(verified: false)->getJson('/api/admin/overview')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');
    }

    public function test_a_verified_admin_gets_through_and_an_old_verification_expires(): void
    {
        $this->asAdmin()->getJson('/api/admin/overview')->assertOk();

        $stale = time() - EnsureAdminTwoFactor::MAX_AGE_SECONDS - 60;
        $this->asAdmin(verifiedAt: $stale)->getJson('/api/admin/overview')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');
    }

    public function test_a_banned_admin_is_locked_out(): void
    {
        $this->admin->forceFill(['banned_at' => now()]);

        $this->asAdmin()->getJson('/api/admin/overview')->assertForbidden();
    }

    // ---- enrolment and verification ----

    public function test_enrolment_confirm_and_recovery_codes(): void
    {
        $fresh = User::factory()->admin()->create(['email' => 'new.admin@example.test']);

        $enroll = $this->asAdmin($fresh, verified: false)->postJson('/api/admin/2fa/enroll')->assertOk();
        $secret = $enroll->json('data.secret');
        $this->assertStringContainsString('otpauth://totp/', $enroll->json('data.uri'));
        $this->assertStringContainsString('new.admin%40example.test', $enroll->json('data.uri'));

        $this->asAdmin($fresh->refresh(), verified: false)->postJson('/api/admin/2fa/confirm', ['code' => '000000'])
            ->assertUnprocessable();
        $this->assertFalse($fresh->refresh()->hasTwoFactor());

        $code = Totp::code($secret, intdiv(time(), 30));
        $confirm = $this->asAdmin($fresh->refresh(), verified: false)->postJson('/api/admin/2fa/confirm', ['code' => $code])->assertOk();

        $codes = $confirm->json('data.recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertTrue($fresh->refresh()->hasTwoFactor());

        // Only hashes are stored: the plaintext codes exist nowhere in the database.
        $stored = json_encode($fresh->two_factor_recovery_codes);
        foreach ($codes as $plain) {
            $this->assertStringNotContainsString($plain, $stored);
        }
        $raw = (string) DB::table('users')->where('id', $fresh->id)->value('two_factor_secret');
        $this->assertStringNotContainsString($secret, $raw, 'secret must be encrypted at rest');

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.2fa_enrolled', 'actor_id' => $fresh->id]);
    }

    public function test_an_active_factor_cannot_be_replaced_from_a_session(): void
    {
        $this->asAdmin(verified: false)->postJson('/api/admin/2fa/enroll')->assertStatus(409);

        $this->assertSame(self::SECRET, $this->admin->refresh()->two_factor_secret);
    }

    public function test_verify_accepts_a_valid_code_and_audits_failures(): void
    {
        $this->asAdmin(verified: false)->postJson('/api/admin/2fa/verify', ['code' => '000000'])->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.2fa_failed', 'actor_id' => $this->admin->id]);

        $this->asAdmin(verified: false)->postJson('/api/admin/2fa/verify', ['code' => $this->currentCode()])
            ->assertOk()
            ->assertJsonPath('data.verified', true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.2fa_verified', 'actor_id' => $this->admin->id]);
    }

    public function test_a_used_code_cannot_be_replayed(): void
    {
        $code = $this->currentCode();

        $this->asAdmin(verified: false)->postJson('/api/admin/2fa/verify', ['code' => $code])->assertOk();
        $this->asAdmin($this->admin->refresh(), verified: false)->postJson('/api/admin/2fa/verify', ['code' => $code])->assertUnprocessable();
    }

    public function test_recovery_codes_work_once(): void
    {
        $service = app(TwoFactor::class);
        $user = User::factory()->admin()->create();
        $service->enroll($user);
        $codes = $service->confirm($user->refresh(), Totp::code($user->two_factor_secret, intdiv(time(), 30)));
        $user->refresh();

        $this->assertTrue($service->verify($user, $codes[0]));
        $this->assertFalse($service->verify($user->refresh(), $codes[0]), 'recovery code reused');
        $this->assertSame(7, $service->recoveryCodesLeft($user->refresh()));
        $this->assertTrue($service->verify($user, strtoupper($codes[1])), 'recovery codes ignore case');
    }

    public function test_second_factor_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->asAdmin(verified: false)->postJson('/api/admin/2fa/verify', ['code' => '000000'])->assertUnprocessable();
        }

        $this->asAdmin(verified: false)->postJson('/api/admin/2fa/verify', ['code' => $this->currentCode()])->assertStatus(429);
    }

    public function test_status_reports_where_the_session_stands(): void
    {
        $this->asAdmin(verified: false)->getJson('/api/admin/2fa')
            ->assertOk()
            ->assertJsonPath('data.enrolled', true)
            ->assertJsonPath('data.verified', false);

        $this->asAdmin()->getJson('/api/admin/2fa')->assertJsonPath('data.verified', true);
    }

    public function test_the_audit_log_cannot_be_rewritten_even_by_an_admin_endpoint_bug(): void
    {
        $log = AuditLog::create(['action' => 'test.entry']);

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->delete();
    }
}
