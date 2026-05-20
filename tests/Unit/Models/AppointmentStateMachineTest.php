<?php

namespace Tests\Unit\Models;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create(['status' => 'active', 'business_type' => 'kennel']);
        app()->instance('current.tenant.id', $tenant->id);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id' => $dog->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // allowedTransitions
    // -------------------------------------------------------------------------

    public function test_draft_allows_pending_and_cancelled(): void
    {
        $this->appointment->status = 'draft';
        $this->assertEquals(['pending', 'cancelled'], $this->appointment->allowedTransitions());
    }

    public function test_pending_allows_confirmed_and_cancelled(): void
    {
        $this->appointment->status = 'pending';
        $this->assertEquals(['confirmed', 'cancelled'], $this->appointment->allowedTransitions());
    }

    public function test_confirmed_allows_checked_in_no_show_and_cancelled(): void
    {
        $this->appointment->status = 'confirmed';
        $this->assertEquals(['checked_in', 'no_show', 'cancelled'], $this->appointment->allowedTransitions());
    }

    public function test_checked_in_allows_checked_out_only(): void
    {
        $this->appointment->status = 'checked_in';
        $this->assertEquals(['checked_out'], $this->appointment->allowedTransitions());
    }

    public function test_checked_out_allows_nothing(): void
    {
        $this->appointment->status = 'checked_out';
        $this->assertEquals([], $this->appointment->allowedTransitions());
    }

    public function test_no_show_allows_nothing(): void
    {
        $this->appointment->status = 'no_show';
        $this->assertEquals([], $this->appointment->allowedTransitions());
    }

    public function test_cancelled_allows_nothing(): void
    {
        $this->appointment->status = 'cancelled';
        $this->assertEquals([], $this->appointment->allowedTransitions());
    }

    // -------------------------------------------------------------------------
    // canTransitionTo
    // -------------------------------------------------------------------------

    public function test_can_transition_to_valid_next_status(): void
    {
        $this->appointment->status = 'pending';
        $this->assertTrue($this->appointment->canTransitionTo('confirmed'));
        $this->assertTrue($this->appointment->canTransitionTo('cancelled'));
    }

    public function test_cannot_transition_to_invalid_status(): void
    {
        $this->appointment->status = 'pending';
        $this->assertFalse($this->appointment->canTransitionTo('checked_in'));
        $this->assertFalse($this->appointment->canTransitionTo('checked_out'));
        $this->assertFalse($this->appointment->canTransitionTo('no_show'));
    }

    public function test_cannot_go_backward(): void
    {
        $this->appointment->status = 'confirmed';
        $this->assertFalse($this->appointment->canTransitionTo('pending'));
        $this->assertFalse($this->appointment->canTransitionTo('draft'));
    }

    public function test_terminal_statuses_cannot_transition(): void
    {
        foreach (['checked_out', 'no_show', 'cancelled'] as $terminal) {
            $this->appointment->status = $terminal;
            $this->assertFalse($this->appointment->canTransitionTo('confirmed'));
            $this->assertFalse($this->appointment->canTransitionTo('pending'));
        }
    }

    // -------------------------------------------------------------------------
    // transitionTo
    // -------------------------------------------------------------------------

    public function test_transition_to_valid_status_updates_model(): void
    {
        $this->appointment->transitionTo('pending');

        $this->assertEquals('pending', $this->appointment->fresh()->status);
    }

    public function test_transition_to_invalid_status_throws(): void
    {
        $this->expectException(\LogicException::class);

        $this->appointment->transitionTo('checked_in'); // draft → checked_in is invalid
    }

    public function test_transition_to_cancelled_sets_cancelled_fields(): void
    {
        $tenant = Tenant::find($this->appointment->tenant_id);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->appointment->status = 'confirmed';
        $this->appointment->save();
        $this->appointment->transitionTo('cancelled', $user->id);

        $fresh = $this->appointment->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertEquals($user->id, $fresh->cancelled_by);
    }

    public function test_transition_to_cancelled_without_user_sets_cancelled_at_without_by(): void
    {
        $this->appointment->status = 'confirmed';
        $this->appointment->save();
        $this->appointment->transitionTo('cancelled');

        $fresh = $this->appointment->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertNull($fresh->cancelled_by);
    }

    public function test_happy_path_full_lifecycle(): void
    {
        $this->appointment->transitionTo('pending');
        $this->appointment->transitionTo('confirmed');
        $this->appointment->transitionTo('checked_in');
        $this->appointment->transitionTo('checked_out');

        $this->assertEquals('checked_out', $this->appointment->fresh()->status);
    }

    public function test_confirmed_can_go_no_show(): void
    {
        $this->appointment->status = 'confirmed';
        $this->appointment->save();
        $this->appointment->transitionTo('no_show');

        $this->assertEquals('no_show', $this->appointment->fresh()->status);
    }
}
