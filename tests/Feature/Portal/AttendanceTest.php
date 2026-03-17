<?php

namespace Tests\Feature\Portal;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class AttendanceTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'attendancetest', 'status' => 'active']);
        URL::forceRootUrl('http://attendancetest.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role' => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    public function test_customer_can_list_their_attendance(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();

        Attendance::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/attendance');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_attendance_includes_multiple_dogs(): void
    {
        $dog1 = Dog::factory()->forCustomer($this->customer)->create();
        $dog2 = Dog::factory()->forCustomer($this->customer)->create();

        Attendance::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog1->id,
        ]);
        Attendance::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog2->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/attendance');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_attendance_does_not_include_other_customers_dogs(): void
    {
        $myDog = Dog::factory()->forCustomer($this->customer)->create();
        $otherDog = Dog::factory()->create(['tenant_id' => $this->tenant->id]);

        Attendance::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $myDog->id,
        ]);
        Attendance::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $otherDog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/attendance');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_attendance_is_paginated(): void
    {
        $dog = Dog::factory()->forCustomer($this->customer)->create();

        Attendance::factory()->count(25)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/attendance');

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data'));
        $this->assertArrayHasKey('total', $response->json('meta'));
        $this->assertSame(25, $response->json('meta.total'));
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/portal/v1/attendance')->assertStatus(401);
    }
}
