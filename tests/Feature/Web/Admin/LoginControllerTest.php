<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');
    }

    public function test_suspended_user_is_rejected_by_middleware_even_if_session_exists(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'staff',
            'status'    => 'suspended',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }
}
