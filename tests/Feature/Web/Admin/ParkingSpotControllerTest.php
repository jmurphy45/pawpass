<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ParkingSpot;
use App\Models\PlatformPlan;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ParkingSpotControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['parking_management']]);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'testco',
            'status' => 'active',
            'plan' => 'starter',
        ]);

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

    public function test_owner_can_view_parking_spots_index(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'A1',
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.parking-spots.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ParkingSpots/Index')
                ->has('parkingSpots', 1)
                ->where('parkingSpots.0.id', $spot->id)
                ->where('parkingSpots.0.spot_number', 'A1')
            );
    }

    public function test_staff_can_view_parking_spots_index(): void
    {
        ParkingSpot::factory()->create();

        $response = $this->actingAs($this->staff)
            ->get(route('admin.parking-spots.index'));

        $response->assertOk();
    }

    public function test_owner_can_create_parking_spot(): void
    {
        $data = [
            'spot_number' => 'B12',
            'name' => 'Premium Spot B12',
            'description' => 'Front row parking',
            'location' => 'Main Entrance',
            'is_active' => true,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->owner)
            ->post(route('admin.parking-spots.store'), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('parking_spots', [
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'B12',
            'name' => 'Premium Spot B12',
            'is_active' => true,
        ]);
    }

    public function test_creating_parking_spot_automatically_creates_qr_code(): void
    {
        $data = [
            'spot_number' => 'C15',
            'name' => 'Side Lot C15',
            'is_active' => true,
            'sort_order' => 2,
        ];

        $this->actingAs($this->owner)
            ->post(route('admin.parking-spots.store'), $data);

        $spot = ParkingSpot::where('spot_number', 'C15')->firstOrFail();

        $this->assertDatabaseHas('qr_codes', [
            'tenant_id' => $this->tenant->id,
            'key' => 'parking-C15',
            'label' => 'Parking Spot C15',
            'is_active' => true,
        ]);

        $qrCode = QrCode::where('key', 'parking-C15')->firstOrFail();
        $this->assertEquals("/my/arrive/{$this->tenant->id}/{$spot->id}", $qrCode->target_url);
    }

    public function test_staff_cannot_create_parking_spot(): void
    {
        $data = [
            'spot_number' => 'D99',
            'name' => 'Test Spot',
            'is_active' => true,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->staff)
            ->post(route('admin.parking-spots.store'), $data);

        $response->assertForbidden();
        $this->assertDatabaseMissing('parking_spots', ['spot_number' => 'D99']);
    }

    public function test_create_parking_spot_validates_required_fields(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('admin.parking-spots.store'), []);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['spot_number', 'name']);
    }

    public function test_owner_can_update_parking_spot(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'E1',
            'name' => 'Old Name',
        ]);

        $data = [
            'spot_number' => 'E2',
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'location' => 'Updated location',
            'is_active' => false,
            'sort_order' => 10,
        ];

        $response = $this->actingAs($this->owner)
            ->patch(route('admin.parking-spots.update', $spot), $data);

        $response->assertRedirect();

        $spot->refresh();
        $this->assertEquals('E2', $spot->spot_number);
        $this->assertEquals('Updated Name', $spot->name);
        $this->assertEquals('Updated description', $spot->description);
        $this->assertEquals('Updated location', $spot->location);
        $this->assertFalse($spot->is_active);
        $this->assertEquals(10, $spot->sort_order);
    }

    public function test_updating_spot_number_updates_qr_code_key(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'F1',
        ]);

        QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'parking-F1',
            'label' => 'Parking Spot F1',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('admin.parking-spots.update', $spot), [
                'spot_number' => 'F2',
                'name' => $spot->name,
                'is_active' => $spot->is_active,
                'sort_order' => $spot->sort_order,
            ]);

        $this->assertDatabaseHas('qr_codes', [
            'tenant_id' => $this->tenant->id,
            'key' => 'parking-F2',
            'label' => 'Parking Spot F2',
        ]);
    }

    public function test_staff_cannot_update_parking_spot(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->patch(route('admin.parking-spots.update', $spot), [
                'spot_number' => 'NEW',
                'name' => 'New Name',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $response->assertForbidden();
    }

    public function test_owner_can_delete_parking_spot(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $qrCode = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'parking-'.$spot->spot_number,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->delete(route('admin.parking-spots.destroy', $spot));

        $response->assertRedirect();
        $this->assertSoftDeleted($spot);

        $qrCode->refresh();
        $this->assertFalse($qrCode->is_active);
    }

    public function test_staff_cannot_delete_parking_spot(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->delete(route('admin.parking-spots.destroy', $spot));

        $response->assertForbidden();
        $this->assertDatabaseHas('parking_spots', ['id' => $spot->id, 'deleted_at' => null]);
    }

    public function test_can_get_qr_code_image(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'G1',
        ]);

        QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'parking-G1',
            'token' => '12345678901234567890',
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.parking-spots.qr-image', $spot));

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('svg', $data);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $data['svg']);
    }

    public function test_can_download_qr_code_png(): void
    {
        $spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'H1',
        ]);

        QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'parking-H1',
            'token' => '12345678901234567890',
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.parking-spots.qr-download', $spot));

        $response->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'attachment; filename="h1-qr.png"');
    }

    public function test_parking_spots_are_tenant_scoped(): void
    {
        $otherTenant = Tenant::factory()->create(['plan' => 'starter']);
        $otherSpot = ParkingSpot::factory()->create(['tenant_id' => $otherTenant->id]);
        $mySpot = ParkingSpot::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.parking-spots.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parkingSpots', 1)
                ->where('parkingSpots.0.id', $mySpot->id)
            );
    }

    public function test_routes_require_parking_management_feature(): void
    {
        $tenantWithoutFeature = Tenant::factory()->create([
            'slug' => 'noaccess',
            'status' => 'active',
            'plan' => 'free',
        ]);

        $ownerWithoutFeature = User::factory()->create([
            'tenant_id' => $tenantWithoutFeature->id,
            'role' => 'business_owner',
            'status' => 'active',
        ]);

        URL::forceRootUrl('http://noaccess.pawpass.com');
        app()->instance('current.tenant.id', $tenantWithoutFeature->id);

        $response = $this->actingAs($ownerWithoutFeature)
            ->get('/admin/parking-spots');

        $response->assertRedirect();
    }
}
