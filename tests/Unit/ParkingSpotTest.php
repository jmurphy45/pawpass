<?php

namespace Tests\Unit;

use App\Models\ParkingSpot;
use App\Models\QrCode;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ParkingSpotTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current.tenant.id', $this->tenant->id);
        URL::forceRootUrl("http://{$this->tenant->slug}.pawpass.test");
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_parking_spot_belongs_to_tenant(): void
    {
        $parkingSpot = ParkingSpot::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $parkingSpot->tenant_id);
        $this->assertInstanceOf(Tenant::class, $parkingSpot->tenant);
    }

    public function test_parking_spot_has_correct_fillable_attributes(): void
    {
        $expected = [
            'tenant_id',
            'spot_number',
            'name',
            'description',
            'location',
            'is_active',
            'sort_order',
        ];

        $parkingSpot = new ParkingSpot;

        $this->assertEquals($expected, $parkingSpot->getFillable());
    }

    public function test_parking_spot_casts_attributes_correctly(): void
    {
        $parkingSpot = ParkingSpot::factory()->create([
            'is_active' => 1,
            'sort_order' => '5',
        ]);

        $this->assertIsBool($parkingSpot->is_active);
        $this->assertIsInt($parkingSpot->sort_order);
        $this->assertTrue($parkingSpot->is_active);
        $this->assertEquals(5, $parkingSpot->sort_order);
    }

    public function test_qr_key_accessor_generates_correct_key(): void
    {
        $parkingSpot = ParkingSpot::factory()->create(['spot_number' => 'A123']);

        $this->assertEquals('parking-A123', $parkingSpot->qr_key);
    }

    public function test_parking_spot_can_have_qr_code(): void
    {
        $parkingSpot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'B456',
        ]);

        $qrCode = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'parking-B456',
        ]);

        $parkingSpotQrCode = $parkingSpot->qrCode;
        $this->assertNotNull($parkingSpotQrCode);
        $this->assertEquals($qrCode->id, $parkingSpotQrCode->id);
    }

    public function test_parking_spot_auto_assigns_tenant_id_when_created(): void
    {
        $parkingSpot = ParkingSpot::create([
            'spot_number' => 'C789',
            'name' => 'Test Spot',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertEquals($this->tenant->id, $parkingSpot->tenant_id);
    }

    public function test_parking_spot_respects_tenant_scope(): void
    {
        $otherTenant = Tenant::factory()->create();

        $mySpot = ParkingSpot::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherSpot = ParkingSpot::factory()->create(['tenant_id' => $otherTenant->id]);

        $spots = ParkingSpot::all();

        $this->assertCount(1, $spots);
        $this->assertEquals($mySpot->id, $spots->first()->id);
    }

    public function test_parking_spot_uses_ulid_primary_key(): void
    {
        $parkingSpot = ParkingSpot::factory()->create();

        $this->assertEquals('string', $parkingSpot->getKeyType());
        $this->assertFalse($parkingSpot->getIncrementing());
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $parkingSpot->id);
    }

    public function test_parking_spot_supports_soft_deletes(): void
    {
        $parkingSpot = ParkingSpot::factory()->create();

        $parkingSpot->delete();

        $this->assertSoftDeleted($parkingSpot);
        $this->assertNotNull($parkingSpot->deleted_at);
    }
}
