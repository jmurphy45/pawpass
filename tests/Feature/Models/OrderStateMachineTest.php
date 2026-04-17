<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = Order::factory()->create(['status' => 'pending']);
    }

    // --- allowedTransitions ---

    public function test_pending_allows_authorized_paid_failed_canceled(): void
    {
        $this->order->status = OrderStatus::Pending;
        $this->assertEquals(
            ['authorized', 'paid', 'failed', 'canceled'],
            $this->order->allowedTransitions()
        );
    }

    public function test_authorized_allows_paid_failed_canceled_refunded(): void
    {
        $this->order->status = OrderStatus::Authorized;
        $this->assertEquals(
            ['paid', 'failed', 'canceled', 'refunded'],
            $this->order->allowedTransitions()
        );
    }

    public function test_paid_allows_partially_refunded_refunded_disputed(): void
    {
        $this->order->status = OrderStatus::Paid;
        $this->assertEquals(
            ['partially_refunded', 'refunded', 'disputed'],
            $this->order->allowedTransitions()
        );
    }

    public function test_partially_refunded_allows_refunded_disputed(): void
    {
        $this->order->status = OrderStatus::PartiallyRefunded;
        $this->assertEquals(
            ['refunded', 'disputed'],
            $this->order->allowedTransitions()
        );
    }

    public function test_failed_allows_canceled(): void
    {
        $this->order->status = OrderStatus::Failed;
        $this->assertEquals(['paid', 'canceled'], $this->order->allowedTransitions());
    }

    public function test_terminal_states_have_no_transitions(): void
    {
        foreach ([OrderStatus::Refunded, OrderStatus::Canceled, OrderStatus::Disputed] as $terminal) {
            $this->order->status = $terminal;
            $this->assertEquals([], $this->order->allowedTransitions(), "{$terminal->value} should be terminal");
            $this->assertTrue($terminal->isTerminal());
        }
    }

    // --- canTransitionTo ---

    public function test_cannot_transition_to_invalid_status(): void
    {
        $this->order->status = OrderStatus::Pending;
        $this->assertFalse($this->order->canTransitionTo(OrderStatus::Refunded));
        $this->assertFalse($this->order->canTransitionTo(OrderStatus::Disputed));
        $this->assertFalse($this->order->canTransitionTo(OrderStatus::PartiallyRefunded));
    }

    public function test_can_transition_to_valid_status(): void
    {
        $this->order->status = OrderStatus::Pending;
        $this->assertTrue($this->order->canTransitionTo(OrderStatus::Paid));
        $this->assertTrue($this->order->canTransitionTo(OrderStatus::Authorized));
        $this->assertTrue($this->order->canTransitionTo(OrderStatus::Failed));
        $this->assertTrue($this->order->canTransitionTo(OrderStatus::Canceled));
    }

    // --- transitionTo ---

    public function test_transition_to_valid_status_persists(): void
    {
        $this->order->transitionTo(OrderStatus::Paid);
        $this->assertEquals(OrderStatus::Paid, $this->order->fresh()->status);
    }

    public function test_transition_to_invalid_status_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->order->transitionTo(OrderStatus::Refunded);
    }

    public function test_transition_chain_pending_to_paid_to_refunded(): void
    {
        $this->order->transitionTo(OrderStatus::Paid);
        $this->order->refresh();
        $this->order->transitionTo(OrderStatus::Refunded);
        $this->assertEquals(OrderStatus::Refunded, $this->order->fresh()->status);
    }

    public function test_transition_chain_pending_to_authorized_to_paid(): void
    {
        $this->order->transitionTo(OrderStatus::Authorized);
        $this->order->refresh();
        $this->order->transitionTo(OrderStatus::Paid);
        $this->assertEquals(OrderStatus::Paid, $this->order->fresh()->status);
    }

    public function test_transition_chain_authorized_to_refunded(): void
    {
        $this->order->transitionTo(OrderStatus::Authorized);
        $this->order->refresh();
        $this->order->transitionTo(OrderStatus::Refunded);
        $this->assertEquals(OrderStatus::Refunded, $this->order->fresh()->status);
    }

    // --- isTerminal helper ---

    public function test_non_terminal_statuses_return_false(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Authorized, OrderStatus::Paid, OrderStatus::PartiallyRefunded, OrderStatus::Failed] as $status) {
            $this->assertFalse($status->isTerminal(), "{$status->value} should not be terminal");
        }
    }

    // --- label helper ---

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertEquals('Partially Refunded', OrderStatus::PartiallyRefunded->label());
        $this->assertEquals('Paid', OrderStatus::Paid->label());
    }
}
