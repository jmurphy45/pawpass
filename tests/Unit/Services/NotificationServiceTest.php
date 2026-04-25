<?php

namespace Tests\Unit\Services;

use App\Jobs\DispatchGroupedAlertJob;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\NotificationPending;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
        $this->service = app(NotificationService::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_critical_type_dispatches_even_when_tenant_setting_disabled(): void
    {
        Notification::fake();

        DB::table('tenant_notification_settings')->insert([
            'tenant_id' => $this->tenant->id,
            'type' => 'payment.confirmed',
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->dispatch('payment.confirmed', $this->tenant->id, $this->user->id);

        Notification::assertSentTo($this->user, PawPassNotification::class);
    }

    public function test_non_critical_disabled_type_does_not_dispatch(): void
    {
        Notification::fake();

        DB::table('tenant_notification_settings')->insert([
            'tenant_id' => $this->tenant->id,
            'type' => 'dog.birthday',
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->dispatch('dog.birthday', $this->tenant->id, $this->user->id);

        Notification::assertNothingSent();
    }

    public function test_non_critical_enabled_type_dispatches_all_channels(): void
    {
        Notification::fake();

        $this->service->dispatch('dog.birthday', $this->tenant->id, $this->user->id);

        Notification::assertSentTo(
            $this->user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => $n->via($this->user) === ['database', 'mail', 'sms'],
        );
    }

    public function test_user_preference_disabled_channel_skips_that_channel(): void
    {
        Notification::fake();

        DB::table('user_notification_preferences')->insert([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'dog.birthday',
            'channel' => 'email',
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->dispatch('dog.birthday', $this->tenant->id, $this->user->id);

        // database (in_app always) + sms = 2 channels (email is disabled)
        Notification::assertSentTo(
            $this->user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => $n->via($this->user) === ['database', 'sms'],
        );
    }

    public function test_in_app_always_dispatched_even_if_email_and_sms_disabled(): void
    {
        Notification::fake();

        foreach (['email', 'sms'] as $channel) {
            DB::table('user_notification_preferences')->insert([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'dog.birthday',
                'channel' => $channel,
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->service->dispatch('dog.birthday', $this->tenant->id, $this->user->id);

        Notification::assertSentTo(
            $this->user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => $n->via($this->user) === ['database'],
        );
    }

    public function test_webpush_channel_added_when_user_has_push_subscription(): void
    {
        Notification::fake();

        \App\Models\PushSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'key1',
            'auth_token' => 'auth1',
        ]);

        $this->service->dispatch('dog.birthday', $this->tenant->id, $this->user->id);

        Notification::assertSentTo(
            $this->user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => in_array('webpush', $n->via($this->user), true),
        );
    }

    public function test_webpush_not_added_without_push_subscriptions(): void
    {
        Notification::fake();

        $this->service->dispatch('dog.birthday', $this->tenant->id, $this->user->id);

        Notification::assertSentTo(
            $this->user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => ! in_array('webpush', $n->via($this->user), true),
        );
    }

    public function test_webpush_channel_skipped_when_user_preference_disabled(): void
    {
        Notification::fake();

        \App\Models\PushSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'key1',
            'auth_token' => 'auth1',
        ]);

        DB::table('user_notification_preferences')->insert([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'dog.birthday',
            'channel' => 'webpush',
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->dispatch('dog.birthday', $this->tenant->id, $this->user->id);

        Notification::assertSentTo(
            $this->user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => ! in_array('webpush', $n->via($this->user), true),
        );
    }

    public function test_enqueue_grouped_creates_pending_and_dispatches_grouped_job(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->service->enqueueGrouped('credits.low', $tenant->id, $user->id, $dog->id);

        $this->assertDatabaseHas('notification_pending', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
        ]);

        Bus::assertDispatched(DispatchGroupedAlertJob::class);
    }

    public function test_enqueue_grouped_appends_dog_id_when_pending_exists(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog1 = Dog::factory()->forCustomer($customer)->create();
        $dog2 = Dog::factory()->forCustomer($customer)->create();

        $this->service->enqueueGrouped('credits.low', $tenant->id, $user->id, $dog1->id);
        $this->service->enqueueGrouped('credits.low', $tenant->id, $user->id, $dog2->id);

        $pending = NotificationPending::allTenants()
            ->where('user_id', $user->id)
            ->where('type', 'credits.low')
            ->whereNull('dispatched_at')
            ->first();

        $this->assertContains($dog1->id, $pending->dog_ids);
        $this->assertContains($dog2->id, $pending->dog_ids);
        // DispatchGroupedAlertJob dispatched only once for first creation
        Bus::assertDispatchedTimes(DispatchGroupedAlertJob::class, 1);
    }
}
