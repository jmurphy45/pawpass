<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchGroupedAlertJob;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\NotificationPending;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DispatchGroupedAlertJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    public function test_dispatches_notification_for_all_channels(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(1)->create();

        $pending = NotificationPending::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
            'dog_ids' => [$dog->id],
            'dispatched_at' => null,
        ]);

        (new DispatchGroupedAlertJob($pending->id))->handle();

        Notification::assertSentTo(
            $user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => $n->via($user) === ['database', 'mail', 'sms'],
        );
    }

    public function test_second_invocation_with_same_id_does_nothing(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(1)->create();

        $pending = NotificationPending::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
            'dog_ids' => [$dog->id],
            'dispatched_at' => null,
        ]);

        (new DispatchGroupedAlertJob($pending->id))->handle();
        Notification::assertSentTo($user, PawPassNotification::class);

        // Reset and run again — second invocation does nothing
        Notification::fake();
        (new DispatchGroupedAlertJob($pending->id))->handle();
        Notification::assertNothingSent();
    }

    public function test_type_upgraded_to_credits_empty_when_any_dog_has_zero_balance(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dogLow = Dog::factory()->forCustomer($customer)->withCredits(1)->create();
        $dogEmpty = Dog::factory()->forCustomer($customer)->noCredits()->create();

        $pending = NotificationPending::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
            'dog_ids' => [$dogLow->id, $dogEmpty->id],
            'dispatched_at' => null,
        ]);

        (new DispatchGroupedAlertJob($pending->id))->handle();

        Notification::assertSentTo(
            $user,
            PawPassNotification::class,
            fn (PawPassNotification $n) => $n->type === 'credits.empty',
        );
    }

    public function test_credits_alert_sent_at_set_on_all_dogs(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog1 = Dog::factory()->forCustomer($customer)->withCredits(1)->create();
        $dog2 = Dog::factory()->forCustomer($customer)->withCredits(2)->create();

        $pending = NotificationPending::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
            'dog_ids' => [$dog1->id, $dog2->id],
            'dispatched_at' => null,
        ]);

        (new DispatchGroupedAlertJob($pending->id))->handle();

        $this->assertNotNull($dog1->fresh()->credits_alert_sent_at);
        $this->assertNotNull($dog2->fresh()->credits_alert_sent_at);
    }

    public function test_dispatched_at_marked_on_pending(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(1)->create();

        $pending = NotificationPending::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
            'dog_ids' => [$dog->id],
            'dispatched_at' => null,
        ]);

        (new DispatchGroupedAlertJob($pending->id))->handle();

        $this->assertNotNull($pending->fresh()->dispatched_at);
    }
}
