<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class CreateInvoiceControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_creates_invoice_with_line_items(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/v1/invoices', [
                'customer_id' => $this->customer->id,
                'line_items' => [
                    ['description' => 'Grooming session', 'quantity' => 1, 'unit_price_cents' => 5000],
                    ['description' => 'Nail trim', 'quantity' => 2, 'unit_price_cents' => 1500],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.type', 'invoice');
        $response->assertJsonPath('data.customer_id', $this->customer->id);
        $response->assertJsonPath('data.total_amount', '80.00');

        $this->assertDatabaseHas('order_line_items', ['description' => 'Grooming session', 'quantity' => 1, 'unit_price_cents' => 5000]);
        $this->assertDatabaseHas('order_line_items', ['description' => 'Nail trim', 'quantity' => 2, 'unit_price_cents' => 1500]);
    }

    public function test_creates_invoice_with_due_date(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/v1/invoices', [
                'customer_id' => $this->customer->id,
                'due_date' => '2026-05-31',
                'line_items' => [
                    ['description' => 'Daycare pack', 'quantity' => 1, 'unit_price_cents' => 10000],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.due_date', '2026-05-31');
    }

    public function test_defaults_due_date_to_30_days_from_now(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/v1/invoices', [
                'customer_id' => $this->customer->id,
                'line_items' => [
                    ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => 2000],
                ],
            ]);

        $response->assertCreated();

        $due = $response->json('data.due_date');
        $this->assertNotNull($due);
        $this->assertEquals(now()->addDays(30)->toDateString(), $due);
    }

    public function test_validates_line_items_required(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/v1/invoices', [
                'customer_id' => $this->customer->id,
                'line_items' => [],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['line_items']);
    }

    public function test_validates_customer_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/v1/invoices', [
                'customer_id' => $otherCustomer->id,
                'line_items' => [
                    ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => 2000],
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['customer_id']);
    }

    public function test_item_type_and_item_id_stored_when_provided(): void
    {
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/v1/invoices', [
                'customer_id' => $this->customer->id,
                'line_items' => [
                    [
                        'description' => $package->name,
                        'quantity' => 1,
                        'unit_price_cents' => 5000,
                        'item_type' => 'package',
                        'item_id' => $package->id,
                    ],
                ],
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('order_line_items', [
            'item_type' => 'package',
            'item_id' => $package->id,
        ]);
    }
}
