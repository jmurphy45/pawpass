<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProvisionPortalQrCode;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionPortalQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_portal_qr_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertDatabaseHas('qr_codes', [
            'tenant_id' => $tenant->id,
            'key' => 'portal',
            'target_url' => '/my',
            'label' => 'Customer Portal',
        ]);
    }

    public function test_idempotent_when_portal_qr_already_exists(): void
    {
        $tenant = Tenant::factory()->create();

        ProvisionPortalQrCode::dispatchSync($tenant->id);

        $this->assertDatabaseCount('qr_codes', 1);
    }

    public function test_silently_skips_missing_tenant(): void
    {
        ProvisionPortalQrCode::dispatchSync('01nonexistent0000000000000');

        $this->assertDatabaseCount('qr_codes', 0);
    }
}
