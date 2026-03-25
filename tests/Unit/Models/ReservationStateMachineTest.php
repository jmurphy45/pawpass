<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create(['status' => 'active', 'business_type' => 'kennel']);
        app()->instance('current.tenant.id', $tenant->id);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog      = Dog::factory()->forCustomer($customer)->create();
        $user     = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->reservation = Reservation::factory()->create([
            'tenant_id'   => $tenant->id,
            'dog_id'      => $dog->id,
            'customer_id' => $customer->id,
            'created_by'  => $user->id,
            'status'      => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // allowedTransitions
    // -------------------------------------------------------------------------

    public function test_pending_allows_confirmed_and_cancelled(): void
    {
        $this->reservation->status = 'pending';
        $this->assertEquals(['confirmed', 'cancelled'], $this->reservation->allowedTransitions());
    }

    public function test_confirmed_allows_checked_in_and_cancelled(): void
    {
        $this->reservation->status = 'confirmed';
        $this->assertEquals(['checked_in', 'cancelled'], $this->reservation->allowedTransitions());
    }

    public function test_checked_in_allows_checked_out_only(): void
    {
        $this->reservation->status = 'checked_in';
        $this->assertEquals(['checked_out'], $this->reservation->allowedTransitions());
    }

    public function test_checked_out_allows_nothing(): void
    {
        $this->reservation->status = 'checked_out';
        $this->assertEquals([], $this->reservation->allowedTransitions());
    }

    public function test_cancelled_allows_nothing(): void
    {
        $this->reservation->status = 'cancelled';
        $this->assertEquals([], $this->reservation->allowedTransitions());
    }

    // -------------------------------------------------------------------------
    // canTransitionTo
    // -------------------------------------------------------------------------

    public function test_can_transition_to_valid_next_status(): void
    {
        $this->reservation->status = 'pending';
        $this->assertTrue($this->reservation->canTransitionTo('confirmed'));
        $this->assertTrue($this->reservation->canTransitionTo('cancelled'));
    }

    public function test_cannot_transition_to_invalid_status(): void
    {
        $this->reservation->status = 'pending';
        $this->assertFalse($this->reservation->canTransitionTo('checked_in'));
        $this->assertFalse($this->reservation->canTransitionTo('checked_out'));
    }

    public function test_cannot_go_backward(): void
    {
        $this->reservation->status = 'confirmed';
        $this->assertFalse($this->reservation->canTransitionTo('pending'));
    }

    public function test_terminal_statuses_cannot_transition(): void
    {
        foreach (['checked_out', 'cancelled'] as $terminal) {
            $this->reservation->status = $terminal;
            $this->assertFalse($this->reservation->canTransitionTo('confirmed'));
            $this->assertFalse($this->reservation->canTransitionTo('pending'));
        }
    }

    // -------------------------------------------------------------------------
    // transitionTo
    // -------------------------------------------------------------------------

    public function test_transition_to_valid_status_updates_model(): void
    {
        $this->reservation->transitionTo('confirmed');

        $this->assertEquals('confirmed', $this->reservation->fresh()->status);
    }

    public function test_transition_to_invalid_status_throws(): void
    {
        $this->expectException(\LogicException::class);

        $this->reservation->transitionTo('checked_in'); // pending → checked_in is invalid
    }

    public function test_transition_to_cancelled_sets_cancelled_fields(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->reservation->tenant_id]);

        $this->reservation->transitionTo('cancelled', $user->id);

        $fresh = $this->reservation->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertEquals($user->id, $fresh->cancelled_by);
    }

    public function test_transition_without_user_sets_cancelled_at_without_by(): void
    {
        $this->reservation->transitionTo('cancelled');

        $fresh = $this->reservation->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertNull($fresh->cancelled_by);
    }
}
