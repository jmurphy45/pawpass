<?php

namespace Tests\Feature\Models;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private OrderPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $order         = Order::factory()->create(['status' => 'pending']);
        $this->payment = OrderPayment::factory()->forOrder($order)->create(['status' => 'pending']);
    }

    // --- allowedTransitions ---

    public function test_pending_allows_authorized_paid_failed_canceled(): void
    {
        $this->payment->status = PaymentStatus::Pending;
        $this->assertEquals(
            ['authorized', 'paid', 'failed', 'canceled'],
            $this->payment->allowedTransitions()
        );
    }

    public function test_authorized_allows_paid_refunded_canceled(): void
    {
        $this->payment->status = PaymentStatus::Authorized;
        $this->assertEquals(
            ['paid', 'refunded', 'canceled'],
            $this->payment->allowedTransitions()
        );
    }

    public function test_paid_allows_partially_refunded_refunded_disputed(): void
    {
        $this->payment->status = PaymentStatus::Paid;
        $this->assertEquals(
            ['partially_refunded', 'refunded', 'disputed'],
            $this->payment->allowedTransitions()
        );
    }

    public function test_partially_refunded_allows_refunded_disputed(): void
    {
        $this->payment->status = PaymentStatus::PartiallyRefunded;
        $this->assertEquals(
            ['refunded', 'disputed'],
            $this->payment->allowedTransitions()
        );
    }

    public function test_failed_allows_canceled(): void
    {
        $this->payment->status = PaymentStatus::Failed;
        $this->assertEquals(['canceled'], $this->payment->allowedTransitions());
    }

    public function test_terminal_states_have_no_transitions(): void
    {
        foreach ([PaymentStatus::Refunded, PaymentStatus::Canceled, PaymentStatus::Disputed] as $terminal) {
            $this->payment->status = $terminal;
            $this->assertEquals([], $this->payment->allowedTransitions(), "{$terminal->value} should be terminal");
            $this->assertTrue($terminal->isTerminal());
        }
    }

    // --- canTransitionTo ---

    public function test_cannot_transition_to_invalid_status(): void
    {
        $this->payment->status = PaymentStatus::Pending;
        $this->assertFalse($this->payment->canTransitionTo(PaymentStatus::Refunded));
        $this->assertFalse($this->payment->canTransitionTo(PaymentStatus::Disputed));
        $this->assertFalse($this->payment->canTransitionTo(PaymentStatus::PartiallyRefunded));
    }

    public function test_can_transition_to_valid_status(): void
    {
        $this->payment->status = PaymentStatus::Pending;
        $this->assertTrue($this->payment->canTransitionTo(PaymentStatus::Paid));
        $this->assertTrue($this->payment->canTransitionTo(PaymentStatus::Authorized));
        $this->assertTrue($this->payment->canTransitionTo(PaymentStatus::Failed));
        $this->assertTrue($this->payment->canTransitionTo(PaymentStatus::Canceled));
    }

    // --- transitionTo ---

    public function test_transition_to_valid_status_persists(): void
    {
        $this->payment->transitionTo(PaymentStatus::Paid);
        $this->assertEquals(PaymentStatus::Paid, $this->payment->fresh()->status);
    }

    public function test_transition_to_invalid_status_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->payment->transitionTo(PaymentStatus::Refunded);
    }

    public function test_transition_chain_authorized_to_refunded(): void
    {
        $this->payment->transitionTo(PaymentStatus::Authorized);
        $this->payment->refresh();
        $this->payment->transitionTo(PaymentStatus::Refunded);
        $this->assertEquals(PaymentStatus::Refunded, $this->payment->fresh()->status);
    }

    // --- isTerminal helper ---

    public function test_non_terminal_statuses_return_false(): void
    {
        foreach ([PaymentStatus::Pending, PaymentStatus::Authorized, PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Failed] as $status) {
            $this->assertFalse($status->isTerminal(), "{$status->value} should not be terminal");
        }
    }

    // --- label helper ---

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertEquals('Partially Refunded', PaymentStatus::PartiallyRefunded->label());
        $this->assertEquals('Authorized', PaymentStatus::Authorized->label());
    }
}
