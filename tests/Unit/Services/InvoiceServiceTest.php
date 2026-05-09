<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    private Tenant $tenant;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceService;

        $owner = User::factory()->create(['role' => 'business_owner']);
        $this->tenant = Tenant::factory()->create([
            'slug' => 'pawsome',
            'owner_user_id' => $owner->id,
            'last_invoice_seq' => 0,
        ]);
        app()->instance('current.tenant.id', $this->tenant->id);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'total_amount' => '50.00',
            'subtotal_cents' => 5000,
            'invoice_number' => null,
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        app()->forgetInstance('current.tenant');
        parent::tearDown();
    }

    public function test_generates_invoice_number_with_correct_format(): void
    {
        $number = $this->service->generateInvoiceNumber($this->order);

        $year = now()->year;
        $this->assertSame("pawsome-{$year}-0001", $number);
    }

    public function test_saves_invoice_number_to_order(): void
    {
        $this->service->generateInvoiceNumber($this->order);

        $this->order->refresh();
        $this->assertNotNull($this->order->invoice_number);
        $this->assertStringStartsWith('pawsome-', $this->order->invoice_number);
    }

    public function test_increments_sequence_on_each_call(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $order2 = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'total_amount' => '25.00',
            'subtotal_cents' => 2500,
            'invoice_number' => null,
        ]);

        $n1 = $this->service->generateInvoiceNumber($this->order);
        $n2 = $this->service->generateInvoiceNumber($order2);

        $year = now()->year;
        $this->assertSame("pawsome-{$year}-0001", $n1);
        $this->assertSame("pawsome-{$year}-0002", $n2);
    }

    public function test_returns_existing_number_without_incrementing(): void
    {
        $this->order->update(['invoice_number' => 'pawsome-2026-0099']);
        $this->tenant->refresh();

        $number = $this->service->generateInvoiceNumber($this->order);

        $this->assertSame('pawsome-2026-0099', $number);

        // Sequence should not have been touched
        $this->tenant->refresh();
        $this->assertSame(0, $this->tenant->last_invoice_seq);
    }

    public function test_sequence_resets_to_1_in_new_year(): void
    {
        $this->tenant->update(['last_invoice_seq' => 42, 'last_invoice_year' => now()->year - 1]);

        $number = $this->service->generateInvoiceNumber($this->order);

        $year = now()->year;
        $this->assertSame("pawsome-{$year}-0001", $number);

        $this->tenant->refresh();
        $this->assertSame(1, $this->tenant->last_invoice_seq);
        $this->assertSame($year, $this->tenant->last_invoice_year);
    }

    public function test_sequence_does_not_reset_within_same_year(): void
    {
        $this->tenant->update(['last_invoice_seq' => 5, 'last_invoice_year' => now()->year]);

        $number = $this->service->generateInvoiceNumber($this->order);

        $year = now()->year;
        $this->assertSame("pawsome-{$year}-0006", $number);
    }

    public function test_sequences_are_per_tenant(): void
    {
        $owner2 = User::factory()->create(['role' => 'business_owner']);
        $tenant2 = Tenant::factory()->create([
            'slug' => 'fluffydog',
            'owner_user_id' => $owner2->id,
            'last_invoice_seq' => 0,
        ]);

        $customer2 = Customer::factory()->create(['tenant_id' => $tenant2->id]);
        $package2 = Package::factory()->create(['tenant_id' => $tenant2->id]);

        $order2 = Order::factory()->create([
            'tenant_id' => $tenant2->id,
            'customer_id' => $customer2->id,
            'package_id' => $package2->id,
            'status' => 'paid',
            'total_amount' => '30.00',
            'subtotal_cents' => 3000,
            'invoice_number' => null,
        ]);

        $year = now()->year;

        $n1 = $this->service->generateInvoiceNumber($this->order);
        $n2 = $this->service->generateInvoiceNumber($order2);

        $this->assertSame("pawsome-{$year}-0001", $n1);
        $this->assertSame("fluffydog-{$year}-0001", $n2);
    }
}
