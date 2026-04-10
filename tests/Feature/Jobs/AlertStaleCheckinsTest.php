<?php

namespace Tests\Feature\Jobs;

use App\Jobs\AlertStaleCheckins;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttendancePaymentService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class AlertStaleCheckinsTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();
        app()->forgetInstance('current.tenant.id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function makeTenantWithOwnerAndDog(array $tenantOverrides = []): array
    {
        $tenant   = Tenant::factory()->withOwner()->create($tenantOverrides);
        $owner    = User::find($tenant->owner_user_id);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog      = Dog::factory()->forCustomer($customer)->create();

        return compact('tenant', 'owner', 'customer', 'dog');
    }

    private function makeStaleAttendance(Dog $dog, Tenant $tenant, string $checkedInAt = '-1 day'): Attendance
    {
        return Attendance::factory()->create([
            'tenant_id'      => $tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_at'  => now($tenant->timezone ?? 'UTC')->modify($checkedInAt)->startOfDay()->addHours(9)->utc(),
            'checked_out_at' => null,
        ]);
    }

    private function mockNotifications(): \Mockery\MockInterface
    {
        return $this->mock(NotificationService::class);
    }

    private function mockPayments(): \Mockery\MockInterface
    {
        return $this->mock(AttendancePaymentService::class);
    }

    // ─── Auto-checkout path ───────────────────────────────────────────────────────

    public function test_auto_checkout_sets_checked_out_at_and_audit_fields(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => true,
            'timezone'            => 'America/Chicago',
        ]);

        $attendance = $this->makeStaleAttendance($dog, $tenant, '-1 day');

        $this->mockNotifications()->shouldReceive('dispatch')->never();
        $this->mockPayments()->shouldReceive('captureAuthorized')->once();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );

        $attendance->refresh();

        $this->assertNotNull($attendance->checked_out_at);
        $this->assertNull($attendance->checked_out_by);
        $this->assertNull($attendance->edited_by);
        $this->assertEquals('Auto-checked out by system (stale check-in alert)', $attendance->edit_note);
    }

    public function test_auto_checkout_respects_tenant_timezone(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => true,
            'timezone'            => 'America/New_York',
        ]);

        $attendance = $this->makeStaleAttendance($dog, $tenant, '-1 day');

        $this->mockPayments()->shouldReceive('captureAuthorized')->once();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );

        $attendance->refresh();

        // checked_out_at should be end-of-day in America/New_York, stored as UTC
        $expectedEndOfDay = $attendance->checked_in_at
            ->setTimezone('America/New_York')
            ->endOfDay()
            ->setTimezone('UTC');

        $this->assertEquals(
            $expectedEndOfDay->toDateTimeString(),
            $attendance->checked_out_at->toDateTimeString(),
        );
    }

    public function test_late_night_checkin_crossing_utc_midnight_is_caught(): void
    {
        // Freeze time to 9 AM UTC. A dog checked in at 11 PM CST (UTC-6) yesterday
        // has checked_in_at = 5 AM UTC today. The UTC-date comparison sees "today"
        // and misses it; the per-tenant timezone-aware comparison sees "yesterday CST"
        // and correctly flags it as stale.
        $this->travelTo(now()->startOfDay()->addHours(9));

        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => true,
            'timezone'            => 'America/Chicago', // UTC-6
        ]);

        // Yesterday 11 PM CST = today 5 AM UTC (UTC date = today → old query misses it)
        $checkedInAt = Carbon::yesterday('America/Chicago')
            ->setHour(23)->setMinute(0)->setSecond(0)
            ->utc();

        $attendance = Attendance::factory()->create([
            'tenant_id'      => $tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_at'  => $checkedInAt,
            'checked_out_at' => null,
        ]);

        $this->mockPayments()->shouldReceive('captureAuthorized')->once();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );

        $this->assertNotNull($attendance->fresh()->checked_out_at);
    }

    public function test_auto_checkout_ignores_todays_open_checkins(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => true,
        ]);

        $todayAttendance = Attendance::factory()->create([
            'tenant_id'      => $tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_at'  => now()->startOfDay()->addHours(8),
            'checked_out_at' => null,
        ]);

        $this->mockPayments()->shouldReceive('captureAuthorized')->never();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );

        $this->assertNull($todayAttendance->fresh()->checked_out_at);
    }

    public function test_auto_checkout_skips_stripe_failure_silently(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => true,
        ]);

        $attendance = $this->makeStaleAttendance($dog, $tenant);

        $payments = $this->mockPayments();
        $payments->shouldReceive('captureAuthorized')->andThrow(new \RuntimeException('Stripe error'));

        // Should not throw — failure is caught
        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );

        // Attendance still updated despite Stripe failure
        $this->assertNotNull($attendance->fresh()->checked_out_at);
    }

    // ─── Notification path ────────────────────────────────────────────────────────

    public function test_notification_dispatched_to_owner_when_auto_checkout_disabled(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => false,
        ]);

        $this->makeStaleAttendance($dog, $tenant);

        $notifications = $this->mockNotifications();
        $notifications->shouldReceive('dispatch')
            ->with('attendance.stale_checkins', $tenant->id, $owner->id, \Mockery::on(function ($payload) use ($dog) {
                return $payload['dog_count'] === 1
                    && in_array($dog->name, $payload['dog_names'])
                    && isset($payload['checkout_url']);
            }))
            ->once();
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->mockPayments()->shouldReceive('captureAuthorized')->never();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );
    }

    public function test_notification_dispatched_to_active_staff(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => false,
        ]);

        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'staff',
            'status'    => 'active',
        ]);

        $this->makeStaleAttendance($dog, $tenant);

        $notifications = $this->mockNotifications();
        $notifications->shouldReceive('dispatch')
            ->with('attendance.stale_checkins', $tenant->id, $staff->id, \Mockery::any())
            ->once();
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );
    }

    public function test_suspended_staff_not_notified(): void
    {
        ['tenant' => $tenant, 'dog' => $dog] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => false,
        ]);

        $suspended = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'staff',
            'status'    => 'suspended',
        ]);

        $this->makeStaleAttendance($dog, $tenant);

        $notifications = $this->mockNotifications();
        $notifications->shouldReceive('dispatch')
            ->with('attendance.stale_checkins', $tenant->id, $suspended->id, \Mockery::any())
            ->never();
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );
    }

    public function test_no_notification_when_no_stale_records(): void
    {
        $this->makeTenantWithOwnerAndDog(['auto_checkout_stale' => false]);

        $notifications = $this->mockNotifications();
        $notifications->shouldReceive('dispatch')->never();

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );
    }

    public function test_failure_in_one_tenant_does_not_abort_others(): void
    {
        ['tenant' => $tenant1, 'dog' => $dog1] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => true,
        ]);
        ['tenant' => $tenant2, 'dog' => $dog2] = $this->makeTenantWithOwnerAndDog([
            'auto_checkout_stale' => true,
        ]);

        $stale1 = $this->makeStaleAttendance($dog1, $tenant1);
        $stale2 = $this->makeStaleAttendance($dog2, $tenant2);

        $callCount = 0;
        $payments = $this->mockPayments();
        $payments->shouldReceive('captureAuthorized')->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new \RuntimeException('First tenant Stripe failure');
            }
        });

        (new AlertStaleCheckins)->handle(
            app(NotificationService::class),
            app(AttendancePaymentService::class),
        );

        // Both attendances should be checked out despite first tenant throwing
        $this->assertNotNull($stale1->fresh()->checked_out_at);
        $this->assertNotNull($stale2->fresh()->checked_out_at);
    }

    // ─── Signed checkout route ────────────────────────────────────────────────────

    public function test_signed_url_checks_out_stale_records(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'stale-checkout-a', 'status' => 'active']);
        URL::forceRootUrl('http://stale-checkout-a.pawpass.com');

        $owner    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'business_owner']);
        $tenant->update(['owner_user_id' => $owner->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog      = Dog::factory()->forCustomer($customer)->create();

        $attendance = $this->makeStaleAttendance($dog, $tenant);

        $url = URL::temporarySignedRoute(
            'admin.attendance.checkout-stale',
            now()->addDays(3),
            ['tenant' => $tenant->id],
        );

        $this->get($url)
             ->assertOk()
             ->assertInertia(fn ($page) => $page
                 ->component('Admin/Roster/StaleCheckoutConfirmation')
                 ->where('checked_out_count', 1)
             );

        $attendance->refresh();
        $this->assertNotNull($attendance->checked_out_at);
        $this->assertEquals('Checked out via stale check-in email link', $attendance->edit_note);
        $this->assertNull($attendance->checked_out_by);
    }

    public function test_signed_url_sets_checked_out_by_when_authenticated(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'stale-checkout-b', 'status' => 'active']);
        URL::forceRootUrl('http://stale-checkout-b.pawpass.com');

        $staff    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff', 'status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog      = Dog::factory()->forCustomer($customer)->create();

        $attendance = $this->makeStaleAttendance($dog, $tenant);

        $url = URL::temporarySignedRoute(
            'admin.attendance.checkout-stale',
            now()->addDays(3),
            ['tenant' => $tenant->id],
        );

        $this->actingAs($staff)
             ->get($url)
             ->assertOk();

        $attendance->refresh();
        $this->assertEquals($staff->id, $attendance->checked_out_by);
        $this->assertEquals($staff->id, $attendance->edited_by);
    }

    public function test_unsigned_checkout_url_returns_403(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'stale-checkout-c', 'status' => 'active']);
        URL::forceRootUrl('http://stale-checkout-c.pawpass.com');

        $this->get('/admin/attendance/checkout-stale')
             ->assertForbidden();
    }

    public function test_only_stale_records_for_correct_tenant_are_checked_out(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'stale-checkout-d', 'status' => 'active']);
        $tenantB = Tenant::factory()->create(['slug' => 'stale-checkout-e', 'status' => 'active']);

        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
        $dogA      = Dog::factory()->forCustomer($customerA)->create();
        $dogB      = Dog::factory()->forCustomer($customerB)->create();

        $staleA = $this->makeStaleAttendance($dogA, $tenantA);
        $staleB = $this->makeStaleAttendance($dogB, $tenantB);

        URL::forceRootUrl('http://stale-checkout-d.pawpass.com');

        $url = URL::temporarySignedRoute(
            'admin.attendance.checkout-stale',
            now()->addDays(3),
            ['tenant' => $tenantA->id],
        );

        $this->get($url)->assertOk();

        $this->assertNotNull($staleA->fresh()->checked_out_at);
        $this->assertNull($staleB->fresh()->checked_out_at);
    }
}
