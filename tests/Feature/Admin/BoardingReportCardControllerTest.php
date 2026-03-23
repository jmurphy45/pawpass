<?php

namespace Tests\Feature\Admin;

use App\Models\BoardingReportCard;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class BoardingReportCardControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'rcard-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://rcard-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $dog->id,
            'customer_id' => $customer->id,
            'created_by'  => $this->staff->id,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_index_returns_cards_sorted_by_date(): void
    {
        BoardingReportCard::factory()->create([
            'tenant_id' => $this->tenant->id, 'reservation_id' => $this->reservation->id,
            'report_date' => '2026-05-03', 'created_by' => $this->staff->id,
        ]);
        BoardingReportCard::factory()->create([
            'tenant_id' => $this->tenant->id, 'reservation_id' => $this->reservation->id,
            'report_date' => '2026-05-01', 'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/reservations/{$this->reservation->id}/report-cards");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals('2026-05-01', $response->json('data.0.report_date'));
    }

    public function test_index_cross_tenant_isolation(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other-rcard', 'status' => 'active']);
        $otherRes = Reservation::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/reservations/{$otherRes->id}/report-cards");

        $response->assertStatus(404);
    }

    public function test_store_creates_new_card(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/report-cards",
            ['report_date' => '2026-05-01', 'notes' => 'Buddy had a great day!']
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.notes', 'Buddy had a great day!');
        $response->assertJsonPath('data.report_date', '2026-05-01');
    }

    public function test_store_upserts_card_for_same_date(): void
    {
        BoardingReportCard::factory()->create([
            'tenant_id' => $this->tenant->id, 'reservation_id' => $this->reservation->id,
            'report_date' => '2026-05-01', 'notes' => 'Original', 'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/report-cards",
            ['report_date' => '2026-05-01', 'notes' => 'Updated notes']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.notes', 'Updated notes');
        $this->assertDatabaseCount('boarding_report_cards', 1);
    }

    public function test_store_requires_report_date(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/report-cards",
            ['notes' => 'Some notes']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['report_date']);
    }

    public function test_store_requires_notes(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/report-cards",
            ['report_date' => '2026-05-01']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['notes']);
    }

    public function test_update_changes_notes(): void
    {
        $card = BoardingReportCard::factory()->create([
            'tenant_id' => $this->tenant->id, 'reservation_id' => $this->reservation->id,
            'report_date' => '2026-05-01', 'notes' => 'Old notes', 'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())->patchJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/report-cards/{$card->id}",
            ['notes' => 'New notes']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.notes', 'New notes');
    }

    public function test_update_rejects_wrong_reservation(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $otherRes = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id,
            'customer_id' => $customer->id, 'created_by' => $this->staff->id,
        ]);
        $card = BoardingReportCard::factory()->create([
            'tenant_id' => $this->tenant->id, 'reservation_id' => $otherRes->id,
            'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())->patchJson(
            "/api/admin/v1/reservations/{$this->reservation->id}/report-cards/{$card->id}",
            ['notes' => 'Hack attempt']
        );

        $response->assertStatus(404);
    }
}
