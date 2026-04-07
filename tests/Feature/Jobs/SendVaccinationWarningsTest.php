<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendVaccinationExpiringSoonWarnings;
use App\Jobs\SendVaccinationExpiringUrgentWarnings;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendVaccinationWarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────────

    private function makeTenantWithDog(): array
    {
        $tenant   = Tenant::factory()->withOwner()->create();
        $owner    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'business_owner']);
        $tenant->update(['owner_user_id' => $owner->id]);
        $user     = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'customer']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog      = Dog::factory()->forCustomer($customer)->create();

        return compact('tenant', 'owner', 'user', 'customer', 'dog');
    }

    // ─── SendVaccinationExpiringSoonWarnings ─────────────────────────────────────

    public function test_sends_expiring_soon_to_tenant_owner(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant->id, $owner->id, \Mockery::any())
            ->once();
        $notificationService->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_sends_expiring_soon_to_customer_user(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant->id, $user->id, \Mockery::any())
            ->once();
        $notificationService->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_skips_expiring_soon_if_warning_already_sent(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->warningSent()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')->never();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_sets_warning_sent_at_on_processed_vaccinations(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        $vax = DogVaccination::factory()->expiringSoon()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        (new SendVaccinationExpiringSoonWarnings)->handle(app(NotificationService::class));

        $this->assertNotNull($vax->fresh()->warning_sent_at);
    }

    public function test_ignores_vaccinations_outside_30_day_window(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->create([
            'tenant_id'  => $tenant->id,
            'dog_id'     => $dog->id,
            'expires_at' => now()->addDays(45)->toDateString(),
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')->never();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_ignores_already_expired_vaccinations(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expired()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')->never();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_ignores_vaccinations_with_no_expiry(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->noExpiry()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')->never();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_aggregates_multiple_dogs_into_one_owner_notification(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'dog' => $dog1] = $this->makeTenantWithDog();

        $user2     = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'customer']);
        $customer2 = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user2->id]);
        $dog2      = Dog::factory()->forCustomer($customer2)->create();

        DogVaccination::factory()->expiringSoon()->create(['tenant_id' => $tenant->id, 'dog_id' => $dog1->id]);
        DogVaccination::factory()->expiringSoon()->create(['tenant_id' => $tenant->id, 'dog_id' => $dog2->id]);

        $capturedData = null;
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant->id, $owner->id, \Mockery::capture($capturedData))
            ->once();
        $notificationService->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);

        $this->assertSame(2, $capturedData['dog_count']);
        $this->assertSame(2, $capturedData['vaccination_count']);
    }

    public function test_owner_notification_payload_includes_vaccine_detail(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->create([
            'tenant_id'    => $tenant->id,
            'dog_id'       => $dog->id,
            'vaccine_name' => 'Rabies',
        ]);
        DogVaccination::factory()->expiringSoon()->create([
            'tenant_id'    => $tenant->id,
            'dog_id'       => $dog->id,
            'vaccine_name' => 'Bordetella',
        ]);

        $capturedData = null;
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant->id, $owner->id, \Mockery::capture($capturedData))
            ->once();
        $notificationService->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);

        $this->assertCount(2, $capturedData['dogs'][0]['vaccinations']);
        $this->assertSame(2, $capturedData['vaccination_count']);
    }

    public function test_two_tenants_get_separate_owner_notifications(): void
    {
        ['tenant' => $tenant1, 'owner' => $owner1, 'dog' => $dog1] = $this->makeTenantWithDog();
        ['tenant' => $tenant2, 'owner' => $owner2, 'dog' => $dog2] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->create(['tenant_id' => $tenant1->id, 'dog_id' => $dog1->id]);
        DogVaccination::factory()->expiringSoon()->create(['tenant_id' => $tenant2->id, 'dog_id' => $dog2->id]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant1->id, $owner1->id, \Mockery::any())
            ->once();
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant2->id, $owner2->id, \Mockery::any())
            ->once();
        $notificationService->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_dog_with_customer_with_no_user_does_not_send_customer_notification(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeTenantWithDog();

        // Customer with no linked user_id (staff-created customer without portal access)
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => null]);
        $dog      = Dog::factory()->forCustomer($customer)->create();
        DogVaccination::factory()->expiringSoon()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant->id, $owner->id, \Mockery::any())
            ->once();
        // No customer notification because customer has no user_id
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_soon', $tenant->id, \Mockery::not($owner->id), \Mockery::any())
            ->never();

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
    }

    public function test_exception_for_one_tenant_does_not_abort_others(): void
    {
        ['tenant' => $tenant1, 'dog' => $dog1] = $this->makeTenantWithDog();
        ['tenant' => $tenant2, 'owner' => $owner2, 'dog' => $dog2] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->create(['tenant_id' => $tenant1->id, 'dog_id' => $dog1->id]);
        DogVaccination::factory()->expiringSoon()->create(['tenant_id' => $tenant2->id, 'dog_id' => $dog2->id]);

        $callCount = 0;
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \RuntimeException('Simulated failure');
                }
            });

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);

        // At least one successful dispatch happened (second tenant processed)
        $this->assertGreaterThanOrEqual(2, $callCount);
    }

    // ─── SendVaccinationExpiringUrgentWarnings ───────────────────────────────────

    public function test_sends_expiring_urgent_to_tenant_owner(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringUrgent()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_urgent', $tenant->id, $owner->id, \Mockery::any())
            ->once();
        $notificationService->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new SendVaccinationExpiringUrgentWarnings)->handle($notificationService);
    }

    public function test_sends_expiring_urgent_to_customer_user(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringUrgent()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->with('vaccinations.expiring_urgent', $tenant->id, $user->id, \Mockery::any())
            ->once();
        $notificationService->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new SendVaccinationExpiringUrgentWarnings)->handle($notificationService);
    }

    public function test_skips_expiring_urgent_if_urgent_already_sent(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringUrgent()->urgentSent()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')->never();

        (new SendVaccinationExpiringUrgentWarnings)->handle($notificationService);
    }

    public function test_sets_urgent_sent_at_on_processed_vaccinations(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        $vax = DogVaccination::factory()->expiringUrgent()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        (new SendVaccinationExpiringUrgentWarnings)->handle(app(NotificationService::class));

        $this->assertNotNull($vax->fresh()->urgent_sent_at);
        $this->assertNull($vax->fresh()->warning_sent_at); // urgent job only sets urgent_sent_at
    }

    public function test_vaccination_expiring_in_20_days_not_picked_up_by_urgent_job(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->create([
            'tenant_id'  => $tenant->id,
            'dog_id'     => $dog->id,
            'expires_at' => now()->addDays(20)->toDateString(),
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')->never();

        (new SendVaccinationExpiringUrgentWarnings)->handle($notificationService);
    }

    // ─── DogVaccination Observer ─────────────────────────────────────────────────

    public function test_updating_expires_at_resets_both_dedup_columns(): void
    {
        $tenant = Tenant::factory()->create();
        $dog    = Dog::factory()->create(['tenant_id' => $tenant->id]);

        $vax = DogVaccination::factory()->warningSent()->urgentSent()->create([
            'tenant_id'  => $tenant->id,
            'dog_id'     => $dog->id,
            'expires_at' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertNotNull($vax->warning_sent_at);
        $this->assertNotNull($vax->urgent_sent_at);

        app()->instance('current.tenant.id', $tenant->id);
        $vax->update(['expires_at' => now()->addYear()->toDateString()]);
        app()->forgetInstance('current.tenant.id');

        $fresh = $vax->fresh();
        $this->assertNull($fresh->warning_sent_at);
        $this->assertNull($fresh->urgent_sent_at);
    }

    public function test_updating_non_expires_at_field_does_not_reset_dedup_columns(): void
    {
        $tenant = Tenant::factory()->create();
        $dog    = Dog::factory()->create(['tenant_id' => $tenant->id]);

        $vax = DogVaccination::factory()->warningSent()->urgentSent()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        app()->instance('current.tenant.id', $tenant->id);
        $vax->update(['notes' => 'updated notes']);
        app()->forgetInstance('current.tenant.id');

        $fresh = $vax->fresh();
        $this->assertNotNull($fresh->warning_sent_at);
        $this->assertNotNull($fresh->urgent_sent_at);
    }

    // ─── PawPassNotification messages ────────────────────────────────────────────

    public function test_expiring_soon_notification_has_correct_subject(): void
    {
        $tenant = Tenant::factory()->create();
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);

        $notification = new \App\Notifications\PawPassNotification(
            'vaccinations.expiring_soon',
            $tenant->id,
            ['dog_count' => 2, 'vaccination_count' => 3],
            ['database'],
        );

        $payload = $notification->toArray($user);

        $this->assertSame('Vaccinations Expiring Soon', $payload['subject']);
        $this->assertStringContainsString('30 days', $payload['body']);
    }

    public function test_expiring_urgent_notification_has_urgent_subject(): void
    {
        $tenant = Tenant::factory()->create();
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);

        $notification = new \App\Notifications\PawPassNotification(
            'vaccinations.expiring_urgent',
            $tenant->id,
            ['dog_count' => 1, 'vaccination_count' => 1],
            ['database'],
        );

        $payload = $notification->toArray($user);

        $this->assertStringContainsString('Urgent', $payload['subject']);
        $this->assertStringContainsString('7 days', $payload['body']);
    }

    // ─── Dedup integration ───────────────────────────────────────────────────────

    public function test_running_soon_job_twice_does_not_double_notify(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        DogVaccination::factory()->expiringSoon()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $dispatchCount = 0;
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->andReturnUsing(function () use (&$dispatchCount) {
                $dispatchCount++;
            });

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);
        $firstRunCount = $dispatchCount;

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService);

        $this->assertSame($firstRunCount, $dispatchCount, 'Second run should not dispatch any new notifications');
    }

    public function test_renewing_vaccine_allows_re_notification(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithDog();

        $vax = DogVaccination::factory()->expiringSoon()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();
        (new SendVaccinationExpiringSoonWarnings)->handle(app(NotificationService::class));

        $vax = $vax->fresh(); // reload so local instance reflects the mass-updated warning_sent_at
        $this->assertNotNull($vax->warning_sent_at); // dedup set

        // Renew and push back into warning window
        app()->instance('current.tenant.id', $tenant->id);
        $vax->update(['expires_at' => now()->addYear()->toDateString()]);
        $vax->update(['expires_at' => now()->addDays(15)->toDateString()]);
        app()->forgetInstance('current.tenant.id');

        $this->assertNull($vax->fresh()->warning_sent_at); // reset by observer

        $secondRunCount = 0;
        $notificationService2 = $this->mock(NotificationService::class);
        $notificationService2->shouldReceive('dispatch')
            ->andReturnUsing(function () use (&$secondRunCount) {
                $secondRunCount++;
            });

        (new SendVaccinationExpiringSoonWarnings)->handle($notificationService2);

        $this->assertGreaterThan(0, $secondRunCount);
    }
}
