<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\MessageFlag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class AdminFlagsTest extends AdminTestCase
{
    private function flagFor(string $body, array $terms = ['kill you']): MessageFlag
    {
        $c = $this->conversation(User::factory()->create(), User::factory()->create(), $body);

        return MessageFlag::create(['message_id' => $c['first']->id, 'terms' => $terms]);
    }

    public function test_lists_open_flags_with_decrypted_body_and_audits(): void
    {
        $a = $this->flagFor('i will kill you');
        $b = $this->flagFor('other', ['suicide']);
        $b->forceFill(['reviewed_at' => now()])->save();

        $res = $this->asAdmin()->getJson('/api/admin/flags')->assertOk();

        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.id', $a->id);
        $res->assertJsonPath('data.0.body', 'i will kill you');
        $res->assertJsonPath('data.0.terms', ['kill you']);
        $res->assertJsonPath('data.0.sender_kind', 'user');
        $res->assertJsonPath('data.0.message_id', $a->message_id);
        $res->assertJsonStructure(['data' => [['id', 'message_id', 'thread_id', 'sender_kind', 'terms', 'body', 'created_at', 'reviewed_at']]]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'flags.viewed', 'actor_id' => $this->admin->id]);

        $this->asAdmin()->getJson('/api/admin/flags?status=reviewed')->assertOk()->assertJsonPath('data.0.id', $b->id);
    }

    public function test_list_does_not_run_a_query_per_flag(): void
    {
        foreach (range(1, 5) as $i) {
            $this->flagFor("kill you $i");
        }
        $this->asAdmin();

        DB::enableQueryLog();
        $this->getJson('/api/admin/flags')->assertOk()->assertJsonCount(5, 'data');

        $this->assertLessThan(15, count(DB::getQueryLog()));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->flagFor('x');
        Sanctum::actingAs(User::factory()->create());
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->getJson('/api/admin/flags')->assertForbidden();
    }

    public function test_review_marks_reviewed_and_audits(): void
    {
        $flag = $this->flagFor('kill you');

        $this->asAdmin()->postJson("/api/admin/flags/{$flag->id}/review")->assertOk();

        $flag->refresh();
        $this->assertNotNull($flag->reviewed_at);
        $this->assertSame($this->admin->id, $flag->reviewed_by);
        $this->assertSame(1, AuditLog::query()->where('action', 'flag.reviewed')->where('subject_id', $flag->id)->count());
    }

    public function test_reviewing_twice_conflicts(): void
    {
        $flag = $this->flagFor('kill you');
        $this->asAdmin()->postJson("/api/admin/flags/{$flag->id}/review")->assertOk();

        $this->asAdmin()->postJson("/api/admin/flags/{$flag->id}/review")->assertStatus(409);
    }

    public function test_review_requires_verified_second_factor(): void
    {
        $flag = $this->flagFor('kill you');

        $this->asAdmin(verified: false)->postJson("/api/admin/flags/{$flag->id}/review")->assertForbidden();
    }
}
