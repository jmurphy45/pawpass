<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WebNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'webnotif', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://webnotif.pawpass.com');

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
        ]);
    }

    private function insertNotification(string $type = 'credits.low', string $subject = 'Low credits', string $body = 'You are running low.'): string
    {
        $id = \Illuminate\Support\Str::uuid()->toString();
        DB::table('notifications')->insert([
            'id'              => $id,
            'type'            => 'App\\Notifications\\PawPassNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $this->user->id,
            'data'            => json_encode(['type' => $type, 'subject' => $subject, 'body' => $body, 'tenant_id' => $this->tenant->id, 'data' => []]),
            'tenant_id'       => $this->tenant->id,
            'read_at'         => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $id;
    }

    public function test_index_returns_flat_dto_with_event_type_not_php_class_name(): void
    {
        $this->insertNotification('credits.low', 'Low credits', 'You are running low.');

        $response = $this->actingAs($this->user)
            ->get('/my/notifications');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Notifications')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'credits.low')
            ->where('notifications.data.0.subject', 'Low credits')
            ->where('notifications.data.0.body', 'You are running low.')
        );
    }

    public function test_index_type_is_not_the_php_class_name(): void
    {
        $this->insertNotification('payment.confirmed', 'Payment Confirmed', 'Your payment was received.');

        $response = $this->actingAs($this->user)
            ->get('/my/notifications');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('notifications.data.0.type', 'payment.confirmed')
        );

        // Verify the raw PHP class name is NOT in the response
        $response->assertDontSee('App\\Notifications\\PawPassNotification');
    }

    public function test_dashboard_recent_notifications_returns_flat_dto(): void
    {
        $this->insertNotification('credits.empty', 'Credits Empty', 'Your dog has no credits.');

        $customer = $this->user->customer;
        $customer->update(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get('/my');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Dashboard')
            ->has('recentNotifications', 1)
            ->where('recentNotifications.0.type', 'credits.empty')
            ->where('recentNotifications.0.subject', 'Credits Empty')
            ->where('recentNotifications.0.body', 'Your dog has no credits.')
        );
    }
}
