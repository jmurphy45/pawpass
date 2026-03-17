<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class AuditLogControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        URL::forceRootUrl('http://platform.pawpass.com');

        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->admin)];
    }

    private function insertEntry(string $action, string $targetId = 'target-001', ?string $createdAt = null): void
    {
        DB::table('platform_audit_log')->insert([
            'id' => (string) Str::ulid(),
            'actor_id' => $this->admin->id,
            'actor_role' => 'platform_admin',
            'action' => $action,
            'target_type' => 'tenant',
            'target_id' => $targetId,
            'context' => json_encode(['reason' => 'test']),
            'ip_address' => '127.0.0.1',
            'created_at' => $createdAt ?? now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_entries_newest_first(): void
    {
        $this->insertEntry('tenant.suspended', 'target-a', now()->subMinutes(5)->toDateTimeString());
        $this->insertEntry('tenant.reinstated', 'target-b', now()->toDateTimeString());

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/audit-log');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('tenant.reinstated', $data[0]['action']);
        $this->assertSame('tenant.suspended', $data[1]['action']);
    }

    public function test_index_entries_include_expected_fields(): void
    {
        $this->insertEntry('tenant.cancelled', 'some-tenant-id');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/audit-log');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'actor_id', 'actor_role', 'action', 'target_type', 'target_id', 'context', 'created_at']],
                'meta' => ['total', 'current_page', 'last_page'],
            ])
            ->assertJsonPath('data.0.actor_id', $this->admin->id)
            ->assertJsonPath('data.0.action', 'tenant.cancelled')
            ->assertJsonPath('data.0.target_type', 'tenant')
            ->assertJsonPath('data.0.target_id', 'some-tenant-id');
    }

    public function test_index_returns_empty_when_no_entries(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/audit-log');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }
}
