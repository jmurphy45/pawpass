<?php

namespace Tests\Unit;

use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Services\KennelAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KennelAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private KennelAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KennelAvailabilityService;
    }

    public function test_is_available_returns_true_when_no_reservations(): void
    {
        $unit = KennelUnit::factory()->create();

        $result = $this->service->isAvailable(
            $unit,
            now()->addDay(),
            now()->addDays(3),
        );

        $this->assertTrue($result);
    }

    public function test_is_available_returns_false_when_dates_overlap(): void
    {
        $unit = KennelUnit::factory()->create();
        Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $unit->tenant_id,
            'starts_at' => now()->addDays(1),
            'ends_at'   => now()->addDays(4),
        ]);

        $result = $this->service->isAvailable(
            $unit,
            now()->addDays(2),
            now()->addDays(5),
        );

        $this->assertFalse($result);
    }

    public function test_is_available_excludes_cancelled_reservations(): void
    {
        $unit = KennelUnit::factory()->create();
        Reservation::factory()->withUnit($unit)->cancelled()->create([
            'tenant_id' => $unit->tenant_id,
            'starts_at' => now()->addDays(1),
            'ends_at'   => now()->addDays(4),
        ]);

        $result = $this->service->isAvailable(
            $unit,
            now()->addDays(2),
            now()->addDays(5),
        );

        $this->assertTrue($result);
    }

    public function test_is_available_excludes_the_reservation_being_updated(): void
    {
        $unit = KennelUnit::factory()->create();
        $reservation = Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $unit->tenant_id,
            'starts_at' => now()->addDays(1),
            'ends_at'   => now()->addDays(4),
        ]);

        // Same dates as the existing reservation — would normally conflict
        $result = $this->service->isAvailable(
            $unit,
            now()->addDays(1),
            now()->addDays(4),
            $reservation->id,
        );

        $this->assertTrue($result);
    }

    public function test_adjacent_reservations_do_not_conflict(): void
    {
        $unit = KennelUnit::factory()->create();
        Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $unit->tenant_id,
            'starts_at' => now()->addDays(1),
            'ends_at'   => now()->addDays(3),
        ]);

        // Starts exactly when the existing one ends
        $result = $this->service->isAvailable(
            $unit,
            now()->addDays(3),
            now()->addDays(5),
        );

        $this->assertTrue($result);
    }

    public function test_available_units_returns_only_unblocked_active_units(): void
    {
        $unit1 = KennelUnit::factory()->create();
        $unit2 = KennelUnit::factory()->create(['tenant_id' => $unit1->tenant_id]);
        $inactiveUnit = KennelUnit::factory()->inactive()->create(['tenant_id' => $unit1->tenant_id]);

        // Block unit1 with an active reservation
        Reservation::factory()->withUnit($unit1)->confirmed()->create([
            'tenant_id' => $unit1->tenant_id,
            'starts_at' => now()->addDays(1),
            'ends_at'   => now()->addDays(4),
        ]);

        $available = $this->service->availableUnits(
            $unit1->tenant_id,
            now()->addDays(2),
            now()->addDays(3),
        );

        $ids = $available->pluck('id');
        $this->assertContains($unit2->id, $ids);
        $this->assertNotContains($unit1->id, $ids);
        $this->assertNotContains($inactiveUnit->id, $ids);
    }
}
