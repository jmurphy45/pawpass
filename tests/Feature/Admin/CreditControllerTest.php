<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class CreditControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'creditadmin', 'status' => 'active']);
        URL::forceRootUrl('http://creditadmin.pawpass.com');

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->withCredits(10)->create();
    }

    private function authHeaders(?string $idempotencyKey = null): array
    {
        $headers = ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    public function test_goodwill_adds_credits(): void
    {
        $response = $this->withHeaders($this->authHeaders('key-goodwill-1'))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/goodwill", [
                'credits' => 5,
                'note' => 'Sorry for the inconvenience',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.credit_balance', 15);

        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $this->dog->id,
            'type' => 'goodwill',
            'delta' => 5,
        ]);
    }

    public function test_correction_adjusts_credits_positive(): void
    {
        $response = $this->withHeaders($this->authHeaders('key-correct-1'))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/correction", [
                'delta' => 3,
                'note' => 'Manual correction',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.credit_balance', 13);
    }

    public function test_correction_adjusts_credits_negative(): void
    {
        $response = $this->withHeaders($this->authHeaders('key-correct-2'))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/correction", [
                'delta' => -3,
                'note' => 'Remove credits',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.credit_balance', 7);
    }

    public function test_transfer_between_same_customer_dogs(): void
    {
        $dog2 = Dog::factory()->forCustomer($this->customer)->withCredits(0)->create();

        $response = $this->withHeaders($this->authHeaders('key-transfer-1'))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/transfer", [
                'to_dog_id' => $dog2->id,
                'credits' => 4,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.from_balance', 6)
            ->assertJsonPath('data.to_balance', 4);
    }

    public function test_cross_customer_transfer_returns_409(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->withCredits(0)->create();

        $response = $this->withHeaders($this->authHeaders('key-transfer-2'))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/transfer", [
                'to_dog_id' => $otherDog->id,
                'credits' => 4,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'CROSS_CUSTOMER_TRANSFER');
    }

    public function test_missing_idempotency_key_returns_400(): void
    {
        $response = $this->withHeaders($this->authHeaders()) // no key
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/goodwill", [
                'credits' => 5,
                'note' => 'No key test',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_idempotency_replay_returns_same_response(): void
    {
        $key = 'key-replay-1';

        $first = $this->withHeaders($this->authHeaders($key))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/goodwill", [
                'credits' => 5,
                'note' => 'First call',
            ]);

        $first->assertStatus(200);
        $firstBalance = $first->json('data.credit_balance');

        $second = $this->withHeaders($this->authHeaders($key))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/goodwill", [
                'credits' => 5,
                'note' => 'Second call - should replay',
            ]);

        $second->assertStatus(200)
            ->assertJsonPath('data.credit_balance', $firstBalance);

        // Only one ledger entry should exist (replayed, not re-applied)
        $this->assertDatabaseCount('credit_ledger', 1);
    }

    public function test_goodwill_requires_note(): void
    {
        $response = $this->withHeaders($this->authHeaders('key-validate-1'))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/goodwill", [
                'credits' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    public function test_correction_requires_note(): void
    {
        $response = $this->withHeaders($this->authHeaders('key-validate-2'))
            ->postJson("/api/admin/v1/dogs/{$this->dog->id}/credits/correction", [
                'delta' => 3,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }
}
