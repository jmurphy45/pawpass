<?php

namespace Tests\Feature\Admin;

use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class InvoiceControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'owner@example.com',
        ]);

        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'total_amount' => '50.00',
            'subtotal_cents' => 5000,
            'invoice_number' => null,
            'sent_at' => null,
        ]);
    }

    public function test_send_invoice_emails_customer_and_sets_sent_at(): void
    {
        Mail::fake();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($this->staff)])
            ->postJson("/api/admin/v1/orders/{$this->order->id}/send-invoice");

        $response->assertOk();
        $response->assertJsonPath('data.sent_at', fn ($v) => ! is_null($v));
        $response->assertJsonPath('data.invoice_number', fn ($v) => str_starts_with($v, 'testco-'));

        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));

        $this->order->refresh();
        $this->assertNotNull($this->order->sent_at);
        $this->assertNotNull($this->order->invoice_number);
    }

    public function test_send_invoice_returns_422_when_customer_has_no_email(): void
    {
        Mail::fake();

        $this->customer->update(['email' => null]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($this->staff)])
            ->postJson("/api/admin/v1/orders/{$this->order->id}/send-invoice");

        $response->assertUnprocessable();
        $response->assertJsonPath('error', 'CUSTOMER_NO_EMAIL');

        Mail::assertNothingSent();
    }

    public function test_send_invoice_is_idempotent(): void
    {
        Mail::fake();

        $this->order->update([
            'sent_at' => now()->subHour(),
            'invoice_number' => 'testco-2026-0001',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($this->staff)])
            ->postJson("/api/admin/v1/orders/{$this->order->id}/send-invoice");

        $response->assertOk();
        $response->assertJsonPath('data.invoice_number', 'testco-2026-0001');

        // Should not re-send if already sent
        Mail::assertNothingSent();
    }

    public function test_send_invoice_blocked_for_wrong_tenant(): void
    {
        Mail::fake();

        $other = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherStaff = User::factory()->staff()->create(['tenant_id' => $other->id, 'status' => 'active']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($otherStaff)])
            ->postJson("/api/admin/v1/orders/{$this->order->id}/send-invoice");

        $response->assertNotFound();
    }
}
