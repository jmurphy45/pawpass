<?php

namespace Tests\Feature\Web;

use App\Models\QrCode;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class QrRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active']);
        URL::forceRootUrl('http://pawpass.com');
    }

    public function test_active_qr_with_relative_target_redirects_to_tenant_subdomain(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'token' => '1234567890123456',
            'key' => 'portal',
            'target_url' => '/my',
            'is_active' => true,
        ]);

        $this->get('/go/1234567890123456')
            ->assertRedirect('https://testco.'.config('app.domain').'/my');
    }

    public function test_active_qr_with_absolute_target_redirects_directly(): void
    {
        QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'token' => '9999999999999999',
            'key' => 'external',
            'target_url' => 'https://example.com/promo',
            'is_active' => true,
        ]);

        $this->get('/go/9999999999999999')
            ->assertRedirect('https://example.com/promo');
    }

    public function test_scan_count_increments_on_redirect(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'token' => '1111111111111111',
            'key' => 'portal',
            'target_url' => '/my',
            'is_active' => true,
            'scan_count' => 5,
        ]);

        $this->get('/go/1111111111111111');

        $this->assertSame(6, $qr->fresh()->scan_count);
    }

    public function test_inactive_qr_returns_404(): void
    {
        QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'token' => '2222222222222222',
            'key' => 'portal',
            'target_url' => '/my',
            'is_active' => false,
        ]);

        $this->get('/go/2222222222222222')->assertNotFound();
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get('/go/0000000000000000')->assertNotFound();
    }

    public function test_slug_change_resolves_to_new_slug(): void
    {
        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'token' => '3333333333333333',
            'key' => 'portal',
            'target_url' => '/my',
            'is_active' => true,
        ]);

        $this->tenant->update(['slug' => 'newslug']);

        $this->get('/go/3333333333333333')
            ->assertRedirect('https://newslug.'.config('app.domain').'/my');
    }
}
