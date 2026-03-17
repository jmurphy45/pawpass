<?php

namespace Tests\Unit\Concerns;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasUlidTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_receives_ulid_on_creation(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->id);
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $tenant->id);
    }

    public function test_model_is_not_auto_incrementing(): void
    {
        $tenant = new Tenant;

        $this->assertFalse($tenant->getIncrementing());
        $this->assertSame('string', $tenant->getKeyType());
    }

    public function test_custom_id_is_not_overwritten(): void
    {
        $customId = '01ABCDEFGHJKMNPQRSTVWXYZ12';
        $tenant = Tenant::factory()->create(['id' => $customId]);

        $this->assertSame($customId, $tenant->id);
    }

    public function test_each_model_gets_unique_ulid(): void
    {
        $tenants = Tenant::factory()->count(5)->create();
        $ids = $tenants->pluck('id')->unique();

        $this->assertCount(5, $ids);
    }
}
