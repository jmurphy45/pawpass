<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class NotificationInboxTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'inboxtest', 'status' => 'active']);
        URL::forceRootUrl('http://inboxtest.pawpass.com');

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'role' => 'customer',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    private function insertNotification(string $userId, ?string $readAt = null): string
    {
        $id = \Illuminate\Support\Str::uuid()->toString();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\Notifications\PawPassNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $userId,
            'data' => json_encode(['type' => 'credits.low', 'subject' => 'Low credits', 'body' => 'You are running low.']),
            'tenant_id' => $this->tenant->id,
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_index_returns_notifications_for_user(): void
    {
        $this->insertNotification($this->user->id);
        $this->insertNotification($this->user->id);

        // Another user's notification — should not appear
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->insertNotification($otherUser->id);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/notifications');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_is_paginated(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->insertNotification($this->user->id);
        }

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/notifications');

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data'));
    }

    public function test_count_returns_unread_count(): void
    {
        $this->insertNotification($this->user->id);
        $this->insertNotification($this->user->id);
        $this->insertNotification($this->user->id, now()->toDateTimeString());

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/notifications/count');

        $response->assertStatus(200)
            ->assertJsonPath('data.unread', 2);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $id = $this->insertNotification($this->user->id);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/notifications/{$id}/read");

        $response->assertStatus(200);

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_mark_read_on_unknown_returns_404(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->patchJson('/api/portal/v1/notifications/nonexistent-id/read');

        $response->assertStatus(404);
    }

    public function test_mark_read_cannot_access_other_users_notification(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $id = $this->insertNotification($otherUser->id);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/notifications/{$id}/read");

        $response->assertStatus(404);
    }

    public function test_read_all_marks_all_unread(): void
    {
        $this->insertNotification($this->user->id);
        $this->insertNotification($this->user->id);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/notifications/read-all');

        $response->assertStatus(200);

        $unread = DB::table('notifications')
            ->where('notifiable_id', $this->user->id)
            ->whereNull('read_at')
            ->count();

        $this->assertSame(0, $unread);
    }

    public function test_notification_resource_shape(): void
    {
        $this->insertNotification($this->user->id);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'subject', 'body', 'read_at', 'created_at'],
                ],
            ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/portal/v1/notifications')->assertStatus(401);
    }
}
