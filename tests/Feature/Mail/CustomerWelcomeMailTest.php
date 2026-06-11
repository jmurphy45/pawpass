<?php

namespace Tests\Feature\Mail;

use App\Mail\CustomerWelcomeMail;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_code_embedded_when_portal_qr_exists(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'testco', 'plan' => 'starter']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        QrCode::factory()->create([
            'tenant_id' => $tenant->id,
            'key' => 'portal',
            'target_url' => '/my',
        ]);

        $mailable = new CustomerWelcomeMail($user, $tenant, 'raw-token-123');
        $rendered = $mailable->render();

        $this->assertStringContainsString('data:image/png;base64,', $rendered);
        $this->assertStringContainsString('scan to open the portal', $rendered);
    }

    public function test_qr_code_section_absent_when_no_portal_qr(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'testco', 'plan' => 'starter']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $mailable = new CustomerWelcomeMail($user, $tenant, 'raw-token-123');
        $rendered = $mailable->render();

        $this->assertStringNotContainsString('Scan to open the portal', $rendered);
    }
}
