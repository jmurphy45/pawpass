<?php

namespace Tests\Feature\Web\Admin;

use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class QrCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active']);
        URL::forceRootUrl('http://testco.pawpass.com');
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
            'status' => 'active',
        ]);

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_index_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $this->get('/admin/qr-codes')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/QrCodes/Index'));
    }

    public function test_index_auto_creates_portal_qr_when_none_exist(): void
    {
        $this->actingAs($this->staff);

        $this->assertDatabaseCount('qr_codes', 0);

        $this->get('/admin/qr-codes')->assertOk();

        $this->assertDatabaseHas('qr_codes', [
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
            'target_url' => '/my',
        ]);
    }

    public function test_index_does_not_duplicate_portal_qr(): void
    {
        QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
        ]);

        $this->actingAs($this->staff);
        $this->get('/admin/qr-codes')->assertOk();

        $this->assertDatabaseCount('qr_codes', 1);
    }

    public function test_store_creates_qr_code_for_owner(): void
    {
        $this->actingAs($this->owner);

        $this->post('/admin/qr-codes', [
            'key' => 'checkin',
            'target_url' => '/my/attendance',
            'label' => 'Check-In',
        ])->assertRedirect('/admin/qr-codes');

        $this->assertDatabaseHas('qr_codes', [
            'tenant_id' => $this->tenant->id,
            'key' => 'checkin',
            'target_url' => '/my/attendance',
        ]);
    }

    public function test_store_is_forbidden_for_staff(): void
    {
        $this->actingAs($this->staff);

        $this->post('/admin/qr-codes', [
            'key' => 'checkin',
            'target_url' => '/my',
        ])->assertForbidden();
    }

    public function test_update_changes_target_url_for_owner(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
            'target_url' => '/my',
        ]);

        $this->actingAs($this->owner);

        $this->patch("/admin/qr-codes/{$qr->id}", [
            'target_url' => '/my/packages',
        ])->assertRedirect('/admin/qr-codes');

        $this->assertDatabaseHas('qr_codes', [
            'id' => $qr->id,
            'target_url' => '/my/packages',
        ]);
    }

    public function test_update_is_forbidden_for_staff(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
        ]);

        $this->actingAs($this->staff);

        $this->patch("/admin/qr-codes/{$qr->id}", [
            'target_url' => '/my/packages',
        ])->assertForbidden();
    }

    public function test_destroy_deactivates_qr_code(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner);

        $this->delete("/admin/qr-codes/{$qr->id}")
            ->assertRedirect('/admin/qr-codes');

        $this->assertDatabaseHas('qr_codes', [
            'id' => $qr->id,
            'is_active' => false,
        ]);
    }

    public function test_image_returns_svg_data_uri(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
        ]);

        $this->actingAs($this->staff);

        $response = $this->getJson("/admin/qr-codes/{$qr->id}/image");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['svg', 'stable_url']]);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('data.svg'));
    }

    public function test_download_returns_png_response(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
        ]);

        $this->actingAs($this->staff);

        $this->get("/admin/qr-codes/{$qr->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_cross_tenant_qr_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active']);
        $qr = QrCode::factory()->create([
            'tenant_id' => $otherTenant->id,
            'key' => 'portal',
        ]);

        $this->actingAs($this->staff);

        $this->getJson("/admin/qr-codes/{$qr->id}/image")->assertNotFound();
    }

    public function test_unauthenticated_redirects(): void
    {
        $this->get('/admin/qr-codes')->assertRedirect();
    }
}
