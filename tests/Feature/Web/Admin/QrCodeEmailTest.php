<?php

namespace Tests\Feature\Web\Admin;

use App\Mail\QrCodeMail;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class QrCodeEmailTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active']);
        URL::forceRootUrl('http://testco.pawpass.com');
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
            'status' => 'active',
        ]);

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_owner_can_email_qr_code_to_themselves(): void
    {
        Mail::fake();

        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
            'label' => 'Customer Portal',
        ]);

        $this->actingAs($this->owner)
            ->postJson("/admin/qr-codes/{$qr->id}/email")
            ->assertOk()
            ->assertJson(['message' => 'Email sent.']);

        Mail::assertSent(QrCodeMail::class, fn ($mail) => $mail->hasTo($this->owner->email));
    }

    public function test_staff_cannot_email_qr_code(): void
    {
        Mail::fake();

        $qr = QrCode::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'portal',
        ]);

        $this->actingAs($this->staff)
            ->postJson("/admin/qr-codes/{$qr->id}/email")
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_cross_tenant_qr_returns_404(): void
    {
        Mail::fake();

        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active']);
        $qr = QrCode::factory()->create([
            'tenant_id' => $otherTenant->id,
            'key' => 'portal',
        ]);

        $this->actingAs($this->owner)
            ->postJson("/admin/qr-codes/{$qr->id}/email")
            ->assertNotFound();

        Mail::assertNothingSent();
    }
}
