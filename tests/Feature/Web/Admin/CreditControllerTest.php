<?php

namespace Tests\Feature\Web\Admin;

use App\Models\CreditLedger;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CreditControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create(['credit_balance' => 5]);
    }

    public function test_goodwill_grants_credits(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post("/admin/dogs/{$this->dog->id}/credits/goodwill", [
            'credits' => 3,
            'note'    => 'Test goodwill',
        ]);

        $response->assertRedirect(route('admin.dogs.show', $this->dog));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $this->dog->id,
            'type'   => 'goodwill',
            'delta'  => 3,
        ]);
    }

    public function test_correction_creates_ledger_entry(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post("/admin/dogs/{$this->dog->id}/credits/correction", [
            'delta' => -2,
            'note'  => 'Correction note',
        ]);

        $response->assertRedirect(route('admin.dogs.show', $this->dog));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $this->dog->id,
            'type'   => 'correction_remove',
            'delta'  => -2,
        ]);
    }

    public function test_correction_requires_note(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post("/admin/dogs/{$this->dog->id}/credits/correction", [
            'delta' => -2,
        ]);

        $response->assertSessionHasErrors('note');
    }

    public function test_transfer_between_same_customer_dogs(): void
    {
        $dog2 = Dog::factory()->forCustomer($this->customer)->create(['credit_balance' => 0]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/dogs/{$this->dog->id}/credits/transfer", [
            'to_dog_id' => $dog2->id,
            'credits'   => 2,
        ]);

        $response->assertRedirect(route('admin.dogs.show', $this->dog));
        $response->assertSessionHas('success');
    }

    public function test_transfer_blocked_across_customers(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create(['credit_balance' => 0]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/dogs/{$this->dog->id}/credits/transfer", [
            'to_dog_id' => $otherDog->id,
            'credits'   => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_customer_role_cannot_add_goodwill(): void
    {
        $customerUser = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
            'status'      => 'active',
        ]);

        $this->actingAs($customerUser);

        $response = $this->post("/admin/dogs/{$this->dog->id}/credits/goodwill", [
            'credits' => 3,
        ]);

        $response->assertRedirect(route('admin.login'));
    }
}
