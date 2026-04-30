<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\AttendanceComment;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class AttendanceCommentControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => []]);

        $this->tenant = Tenant::factory()->create(['slug' => 'comments-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://comments-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->attendance = Attendance::create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
        ]);
    }

    private function authHeaders(?User $user = null): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($user ?? $this->staff)];
    }

    public function test_staff_can_list_comments_for_attendance(): void
    {
        AttendanceComment::create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $this->attendance->id,
            'created_by' => $this->staff->id,
            'body' => 'Max was playful today.',
            'is_public' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/attendances/{$this->attendance->id}/comments");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.body', 'Max was playful today.');
        $response->assertJsonPath('data.0.is_public', false);
        $this->assertNotNull($response->json('data.0.created_by'));
    }

    public function test_staff_can_add_a_comment(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/attendances/{$this->attendance->id}/comments", [
                'body' => 'Scratch on left paw, owner notified.',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.body', 'Scratch on left paw, owner notified.');
        $response->assertJsonPath('data.is_public', false);

        $this->assertDatabaseHas('attendance_comments', [
            'attendance_id' => $this->attendance->id,
            'created_by' => $this->staff->id,
            'body' => 'Scratch on left paw, owner notified.',
        ]);
    }

    public function test_store_validates_body_is_required(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/attendances/{$this->attendance->id}/comments", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_store_validates_body_max_length(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/attendances/{$this->attendance->id}/comments", [
                'body' => str_repeat('a', 2001),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_author_can_delete_their_own_comment(): void
    {
        $comment = AttendanceComment::create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $this->attendance->id,
            'created_by' => $this->staff->id,
            'body' => 'My note.',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/attendances/{$this->attendance->id}/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('attendance_comments', ['id' => $comment->id]);
    }

    public function test_owner_can_delete_any_comment(): void
    {
        $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'business_owner']);

        $comment = AttendanceComment::create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $this->attendance->id,
            'created_by' => $this->staff->id,
            'body' => 'Staff note.',
        ]);

        $response = $this->withHeaders($this->authHeaders($owner))
            ->deleteJson("/api/admin/v1/attendances/{$this->attendance->id}/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('attendance_comments', ['id' => $comment->id]);
    }

    public function test_staff_cannot_delete_another_staff_members_comment(): void
    {
        $otherStaff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);

        $comment = AttendanceComment::create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $this->attendance->id,
            'created_by' => $otherStaff->id,
            'body' => 'Other staff note.',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/v1/attendances/{$this->attendance->id}/comments/{$comment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('attendance_comments', ['id' => $comment->id]);
    }

    public function test_cross_tenant_attendance_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();
        $otherAttendance = Attendance::create([
            'tenant_id' => $otherTenant->id,
            'dog_id' => $otherDog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/attendances/{$otherAttendance->id}/comments");

        $response->assertStatus(404);
    }

    public function test_roster_index_includes_comment_count(): void
    {
        AttendanceComment::create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $this->attendance->id,
            'created_by' => $this->staff->id,
            'body' => 'A note.',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/roster');

        $response->assertStatus(200);
        $dogEntry = collect($response->json('data'))->first(fn ($d) => $d['attendance_id'] === $this->attendance->id);
        $this->assertNotNull($dogEntry);
        $this->assertEquals(1, $dogEntry['comment_count']);
    }
}
