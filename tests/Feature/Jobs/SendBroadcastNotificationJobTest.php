<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendBroadcastNotificationJob;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendBroadcastNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    private function makeTenantWithCustomers(int $count = 2): array
    {
        $tenant = Tenant::factory()->create(['plan' => 'starter']);
        $users  = [];

        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role'      => 'customer',
                'phone'     => '+1555000000'.$i,
            ]);
            Customer::factory()->create([
                'tenant_id' => $tenant->id,
                'user_id'   => $user->id,
            ]);
            $users[] = $user;
        }

        return [$tenant, $users];
    }

    public function test_sends_in_app_notification_to_all_customers(): void
    {
        Notification::fake();

        [$tenant, $users] = $this->makeTenantWithCustomers(2);

        (new SendBroadcastNotificationJob(
            tenantId: $tenant->id,
            subject: 'Test Subject',
            body: 'Test Body',
            requestedChannels: ['in_app'],
        ))->handle();

        foreach ($users as $user) {
            Notification::assertSentTo($user, PawPassNotification::class, function ($n) {
                return $n->type === 'announcement';
            });
        }
    }

    public function test_does_not_send_to_staff_users(): void
    {
        Notification::fake();

        [$tenant] = $this->makeTenantWithCustomers(1);

        $staffUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'staff',
        ]);

        (new SendBroadcastNotificationJob(
            tenantId: $tenant->id,
            subject: 'Test',
            body: 'Test',
            requestedChannels: ['in_app'],
        ))->handle();

        Notification::assertNothingSentTo($staffUser);
    }

    public function test_sends_sms_channel_to_users_with_phone(): void
    {
        Notification::fake();

        [$tenant, $users] = $this->makeTenantWithCustomers(1);
        $user = $users[0];

        (new SendBroadcastNotificationJob(
            tenantId: $tenant->id,
            subject: 'Test Subject',
            body: 'Test Body',
            requestedChannels: ['in_app', 'sms'],
        ))->handle();

        Notification::assertSentTo($user, PawPassNotification::class, function ($n) use ($user) {
            return in_array('sms', $n->via($user));
        });
    }

    public function test_does_not_send_sms_to_users_without_phone(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user   = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'customer',
            'phone'     => null,
        ]);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        (new SendBroadcastNotificationJob(
            tenantId: $tenant->id,
            subject: 'Test',
            body: 'Test',
            requestedChannels: ['sms'],
        ))->handle();

        Notification::assertNothingSentTo($user);
    }

    public function test_sends_email_channel(): void
    {
        Notification::fake();

        [$tenant, $users] = $this->makeTenantWithCustomers(1);

        (new SendBroadcastNotificationJob(
            tenantId: $tenant->id,
            subject: 'Test Subject',
            body: 'Test Body',
            requestedChannels: ['email'],
        ))->handle();

        Notification::assertSentTo($users[0], PawPassNotification::class, function ($n) use ($users) {
            return in_array('mail', $n->via($users[0]));
        });
    }

    public function test_notification_has_correct_data(): void
    {
        Notification::fake();

        [$tenant, $users] = $this->makeTenantWithCustomers(1);

        (new SendBroadcastNotificationJob(
            tenantId: $tenant->id,
            subject: 'Hello Customers',
            body: 'Important update for all of you.',
            requestedChannels: ['in_app'],
        ))->handle();

        Notification::assertSentTo($users[0], PawPassNotification::class, function ($n) {
            return $n->data['subject'] === 'Hello Customers'
                && $n->data['body'] === 'Important update for all of you.';
        });
    }
}
