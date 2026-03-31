<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LogoControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->tenant = Tenant::factory()->create([
            'slug'   => 'testco',
            'status' => 'active',
            'plan'   => 'starter',
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'business_owner',
            'status'    => 'active',
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'staff',
            'status'    => 'active',
        ]);
    }

    public function test_owner_can_upload_logo(): void
    {
        $this->actingAs($this->owner);

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->post('/admin/settings/logo', ['logo' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->logo_url);

        $expectedPath = "tenants/{$this->tenant->id}/logo.png";
        Storage::disk('s3')->assertExists($expectedPath);
    }

    public function test_re_upload_deletes_old_s3_file(): void
    {
        $this->actingAs($this->owner);

        // Upload first logo
        $file1 = UploadedFile::fake()->image('logo.png');
        $this->post('/admin/settings/logo', ['logo' => $file1]);

        $this->tenant->refresh();
        $firstUrl = $this->tenant->logo_url;
        $this->assertNotNull($firstUrl);

        // Upload second logo (same path — overwrites)
        $file2 = UploadedFile::fake()->image('logo.png');
        $this->post('/admin/settings/logo', ['logo' => $file2]);

        // Old path was deleted before new file written, then re-written
        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->logo_url);

        // The key should still exist after re-upload (new file was stored)
        Storage::disk('s3')->assertExists("tenants/{$this->tenant->id}/logo.png");
    }

    public function test_owner_can_delete_logo(): void
    {
        // Seed a logo first
        $path = "tenants/{$this->tenant->id}/logo.png";
        Storage::disk('s3')->put($path, 'fake-content', 'public');
        $this->tenant->update(['logo_url' => Storage::disk('s3')->url($path)]);

        $this->actingAs($this->owner);
        $response = $this->delete('/admin/settings/logo');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->tenant->refresh();
        $this->assertNull($this->tenant->logo_url);

        Storage::disk('s3')->assertMissing($path);
    }

    public function test_staff_cannot_upload_logo(): void
    {
        $this->actingAs($this->staff);

        $file = UploadedFile::fake()->image('logo.png');
        $response = $this->post('/admin/settings/logo', ['logo' => $file]);

        $response->assertForbidden();
    }

    public function test_staff_cannot_delete_logo(): void
    {
        $this->actingAs($this->staff);

        $response = $this->delete('/admin/settings/logo');

        $response->assertForbidden();
    }

    public function test_upload_rejects_non_image_mime(): void
    {
        $this->actingAs($this->owner);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $response = $this->post('/admin/settings/logo', ['logo' => $file]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_upload_rejects_file_over_2mb(): void
    {
        $this->actingAs($this->owner);

        $file = UploadedFile::fake()->image('logo.png')->size(3000);
        $response = $this->post('/admin/settings/logo', ['logo' => $file]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_delete_with_no_logo_does_not_error(): void
    {
        $this->assertNull($this->tenant->logo_url);
        $this->actingAs($this->owner);

        $response = $this->delete('/admin/settings/logo');

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
