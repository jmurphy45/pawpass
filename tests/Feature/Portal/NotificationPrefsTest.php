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

class NotificationPrefsTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'prefstest', 'status' => 'active']);
        URL::forceRootUrl('http://prefstest.pawpass.com');

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

    public function test_get_notification_prefs_returns_empty_by_default(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/account/notification-prefs');

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_get_returns_existing_prefs(): void
    {
        DB::table('user_notification_preferences')->insert([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'credits.low',
            'channel' => 'email',
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/account/notification-prefs');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('credits.low', $response->json('data.0.type'));
        $this->assertSame('email', $response->json('data.0.channel'));
        $this->assertFalse((bool) $response->json('data.0.is_enabled'));
    }

    public function test_put_upserts_prefs(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/portal/v1/account/notification-prefs', [
                'prefs' => [
                    ['type' => 'credits.low', 'channel' => 'email', 'is_enabled' => false],
                    ['type' => 'credits.low', 'channel' => 'sms', 'is_enabled' => true],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $this->user->id,
            'type' => 'credits.low',
            'channel' => 'email',
            'is_enabled' => false,
        ]);
    }

    public function test_put_updates_existing_pref(): void
    {
        DB::table('user_notification_preferences')->insert([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'credits.low',
            'channel' => 'email',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/portal/v1/account/notification-prefs', [
                'prefs' => [
                    ['type' => 'credits.low', 'channel' => 'email', 'is_enabled' => false],
                ],
            ]);

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $this->user->id,
            'type' => 'credits.low',
            'channel' => 'email',
            'is_enabled' => false,
        ]);
    }

    public function test_put_rejects_in_app_channel(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/portal/v1/account/notification-prefs', [
                'prefs' => [
                    ['type' => 'credits.low', 'channel' => 'in_app', 'is_enabled' => false],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prefs.0.channel']);
    }

    public function test_prefs_array_is_required(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/portal/v1/account/notification-prefs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prefs']);
    }
}
