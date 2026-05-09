<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvoicePdfControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'total_amount' => '50.00',
            'subtotal_cents' => 5000,
            'tax_amount_cents' => 0,
            'invoice_number' => 'testco-2026-0001',
        ]);

        OrderLineItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'description' => '10-Day Pack',
            'quantity' => 1,
            'unit_price_cents' => 5000,
            'sort_order' => 0,
            'item_type' => 'package',
            'item_id' => $package->id,
        ]);
    }

    public function test_staff_can_download_invoice_pdf(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('admin.orders.invoice', $this->order));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_filename_includes_invoice_number(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('admin.orders.invoice', $this->order));

        $response->assertOk();
        $this->assertStringContainsString(
            'testco-2026-0001',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    public function test_guest_cannot_access_invoice_pdf(): void
    {
        $response = $this->get(route('admin.orders.invoice', $this->order));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_staff_from_other_tenant_cannot_access(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherStaff = User::factory()->staff()->create(['tenant_id' => $otherTenant->id, 'status' => 'active']);

        $response = $this->actingAs($otherStaff)
            ->get(route('admin.orders.invoice', $this->order));

        // StaffPortalWebMiddleware redirects mismatched-tenant staff before reaching the controller
        $response->assertRedirect(route('admin.login'));
    }

    public function test_invoice_number_generated_if_missing(): void
    {
        $this->order->update(['invoice_number' => null]);
        $this->tenant->update(['last_invoice_seq' => 0]);

        $this->actingAs($this->staff)
            ->get(route('admin.orders.invoice', $this->order));

        $this->order->refresh();
        $this->assertNotNull($this->order->invoice_number);
    }
}
