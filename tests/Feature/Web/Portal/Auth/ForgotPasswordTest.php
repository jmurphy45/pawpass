<?php

namespace Tests\Feature\Web\Portal\Auth;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'role' => 'customer',
            'status' => 'active',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_forgot_password_page_renders(): void
    {
        $response = $this->get('/my/forgot-password');

        $response->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_forgot_password_store_dispatches_notification_with_reset_url_for_known_email(): void
    {
        $captured = null;
        $this->mock(NotificationService::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->withArgs(function ($type, $tenantId, $userId, $data) use (&$captured) {
                    $captured = [$type, $data];

                    return true;
                });
        });

        $response = $this->post('/my/forgot-password', ['email' => 'jane@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        [$type, $data] = $captured;
        $this->assertSame('auth.password_reset', $type);
        $this->assertStringContainsString('/my/reset-password?token=', $data['reset_url']);
        $this->assertStringContainsString('email=jane%40example.com', $data['reset_url']);
    }

    public function test_forgot_password_store_is_silent_for_unknown_email(): void
    {
        $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('dispatch');
        });

        $response = $this->post('/my/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->post('/my/forgot-password', []);

        $response->assertSessionHasErrors('email');
    }
}
