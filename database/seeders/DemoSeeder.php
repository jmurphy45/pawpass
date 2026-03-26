<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\CreditLedger;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private bool $hasRealStripeConnect = false;

    private bool $hasRealStripeBilling = false;

    public function run(): void
    {
        $this->hasRealStripeConnect = $this->isRealStripeKey(
            config('services.stripe.secret', env('STRIPE_SECRET', ''))
        );
        $this->hasRealStripeBilling = $this->isRealStripeKey(
            config('services.stripe_billing.billing_secret', env('STRIPE_BILLING_SECRET', ''))
        );

        $this->command->info('Seeding demo data...');
        $this->seedPlatformAdmin();
        $this->seedHappyPaws();
        $this->seedCoastalCanines();
        $this->seedBarkBox();
        $this->printCredentials();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isRealStripeKey(string $key): bool
    {
        return strlen($key) > 30
            && ! in_array($key, ['sk_test_placeholder', 'sk_live_placeholder', '']);
    }

    private function makePackage(
        string $tid,
        string $name,
        string $description,
        string $type,
        string $price,
        int $creditCount,
        int $dogLimit,
        ?int $durationDays,
        string $stripeAccountId,
        string $fakePriceId,
        bool $isFeatured = false
    ): Package {
        $stripePriceId = $fakePriceId;
        $stripeProductId = 'prod_demo_' . Str::slug($name);

        if ($this->hasRealStripeConnect && ! str_starts_with($stripeAccountId, 'acct_1Demo')) {
            try {
                $svc = app(\App\Services\StripeService::class);
                $product = $svc->createProduct($name, $stripeAccountId);
                $interval = $type === 'subscription' ? 'month' : null;
                $price_obj = $svc->createPrice($product->id, (int) ($price * 100), 'usd', $interval, $stripeAccountId);
                $stripePriceId = $price_obj->id;
                $stripeProductId = $product->id;
            } catch (\Exception $e) {
                // fall through to fake IDs
            }
        }

        return Package::create([
            'id'               => (string) Str::ulid(),
            'tenant_id'        => $tid,
            'name'             => $name,
            'description'      => $description,
            'type'             => $type,
            'price'            => $price,
            'credit_count'     => $creditCount,
            'dog_limit'        => $dogLimit,
            'duration_days'    => $durationDays,
            'is_active'        => true,
            'is_featured'      => $isFeatured,
            'stripe_price_id'  => $stripePriceId,
            'stripe_product_id' => $stripeProductId,
        ]);
    }

    /** @return array{0: User, 1: Customer} */
    private function makeCustomerUser(string $tid, string $name, string $email, ?string $phone = null): array
    {
        $user = User::create([
            'id'                => (string) Str::ulid(),
            'tenant_id'         => $tid,
            'customer_id'       => null,
            'name'              => $name,
            'email'             => $email,
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'customer',
            'status'            => 'active',
        ]);

        $customer = Customer::create([
            'id'        => (string) Str::ulid(),
            'tenant_id' => $tid,
            'user_id'   => $user->id,
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'notes'     => null,
        ]);

        $user->update(['customer_id' => $customer->id]);

        return [$user, $customer];
    }

    private function makeCustomerOnly(string $tid, string $name, ?string $email, ?string $phone = null): Customer
    {
        return Customer::create([
            'id'        => (string) Str::ulid(),
            'tenant_id' => $tid,
            'user_id'   => null,
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'notes'     => null,
        ]);
    }

    private function makeDog(
        string $tid,
        string $customerId,
        string $name,
        string $breed,
        string $dob,
        string $sex,
        int $creditBalance
    ): Dog {
        return Dog::create([
            'id'                     => (string) Str::ulid(),
            'tenant_id'              => $tid,
            'customer_id'            => $customerId,
            'name'                   => $name,
            'breed'                  => $breed,
            'dob'                    => $dob,
            'sex'                    => $sex,
            'photo_url'              => null,
            'credit_balance'         => $creditBalance,
            'credits_expire_at'      => null,
            'credits_alert_sent_at'  => null,
        ]);
    }

    private function makeOrder(
        string $tid,
        string $customerId,
        string $packageId,
        string $amount,
        mixed $paidAt,
        string $fakePiId,
        string $status = 'paid',
        mixed $refundedAt = null
    ): Order {
        $order = Order::create([
            'id'               => (string) Str::ulid(),
            'tenant_id'        => $tid,
            'customer_id'      => $customerId,
            'package_id'       => $packageId,
            'status'           => $status,
            'total_amount'     => $amount,
            'platform_fee_pct' => '5.00',
            'idempotency_key'  => (string) Str::uuid(),
        ]);

        OrderPayment::create([
            'id'                    => (string) Str::ulid(),
            'tenant_id'             => $tid,
            'order_id'              => $order->id,
            'stripe_pi_id'          => $fakePiId,
            'stripe_payment_method' => null,
            'amount_cents'          => (int) (floatval($amount) * 100),
            'type'                  => 'charge',
            'status'                => $status,
            'paid_at'               => $paidAt,
            'refunded_at'           => $refundedAt,
        ]);

        return $order;
    }

    private function makeLedger(
        string $tid,
        string $dogId,
        string $type,
        int $delta,
        int $balanceAfter,
        array $extra = []
    ): CreditLedger {
        return CreditLedger::create(array_merge([
            'tenant_id'        => $tid,
            'dog_id'           => $dogId,
            'type'             => $type,
            'delta'            => $delta,
            'balance_after'    => $balanceAfter,
            'expires_at'       => null,
            'order_id'         => null,
            'attendance_id'    => null,
            'subscription_id'  => null,
            'parent_ledger_id' => null,
            'created_by'       => null,
            'note'             => null,
        ], $extra));
    }

    private function makeAttendance(
        string $tid,
        string $dogId,
        string $checkedInBy,
        mixed $checkedInAt,
        mixed $checkedOutAt = null,
        bool $zeroOverride = false,
        ?string $overrideNote = null,
        ?string $checkedOutBy = null
    ): Attendance {
        return Attendance::create([
            'id'                 => (string) Str::ulid(),
            'tenant_id'          => $tid,
            'dog_id'             => $dogId,
            'checked_in_by'      => $checkedInBy,
            'checked_out_by'     => $checkedOutAt !== null ? ($checkedOutBy ?? $checkedInBy) : null,
            'checked_in_at'      => $checkedInAt,
            'checked_out_at'     => $checkedOutAt,
            'zero_credit_override' => $zeroOverride,
            'override_note'      => $overrideNote,
            'edited_by'          => null,
            'edited_at'          => null,
            'edit_note'          => null,
            'original_in'        => null,
            'original_out'       => null,
        ]);
    }

    private function seedNotification(
        string $userId,
        string $tenantId,
        string $event,
        string $title,
        string $body,
        array $extra = [],
        mixed $readAt = null,
        mixed $createdAt = null
    ): void {
        $ts = $createdAt ?? now();
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\Notifications\PawPassNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $userId,
            'data'            => json_encode(array_merge(['event' => $event, 'title' => $title, 'body' => $body], $extra)),
            'tenant_id'       => $tenantId,
            'read_at'         => $readAt,
            'created_at'      => $ts,
            'updated_at'      => $ts,
        ]);
    }

    // ── Platform Admin ────────────────────────────────────────────────────────

    private function seedPlatformAdmin(): void
    {
        User::create([
            'id'                => (string) Str::ulid(),
            'tenant_id'         => null,
            'customer_id'       => null,
            'name'              => 'Platform Admin',
            'email'             => 'admin@pawpass.test',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'platform_admin',
            'status'            => 'active',
        ]);
        $this->command->info('  [✓] Platform admin');
    }

    // ── Happy Paws Daycare — Pro plan, fully onboarded ────────────────────────

    private function seedHappyPaws(): void
    {
        $tid = (string) Str::ulid();

        $tenant = Tenant::create([
            'id'                         => $tid,
            'name'                       => 'Happy Paws Daycare',
            'slug'                       => 'happy-paws',
            'owner_user_id'              => null,
            'status'                     => 'active',
            'stripe_account_id'          => null,
            'stripe_onboarded_at'        => null,
            'platform_fee_pct'           => '5.00',
            'payout_schedule'            => 'daily',
            'low_credit_threshold'       => 2,
            'checkin_block_at_zero'      => true,
            'timezone'                   => 'America/New_York',
            'primary_color'              => '#7c3aed',
            'plan'                       => 'pro',
            'plan_billing_cycle'         => 'monthly',
            'plan_current_period_end'    => now()->addDays(14),
            'platform_stripe_customer_id' => 'cus_demo_happypaws',
            'platform_stripe_sub_id'     => 'sub_demo_happypaws',
        ]);

        // Stripe Connect
        if ($this->hasRealStripeConnect) {
            try {
                $svc = app(\App\Services\StripeService::class);
                $account = $svc->createConnectAccount('owner@happy-paws.test', 'Happy Paws Daycare');
                $tenant->update(['stripe_account_id' => $account->id, 'stripe_onboarded_at' => now()]);
            } catch (\Exception $e) {
                $this->command->warn("  Stripe Connect skipped for happy-paws: {$e->getMessage()}");
                $tenant->update(['stripe_account_id' => 'acct_1DemoHappyPaws']);
            }
        } else {
            $tenant->update(['stripe_account_id' => 'acct_1DemoHappyPaws']);
        }

        $stripeAcct = $tenant->fresh()->stripe_account_id;

        // Users
        $owner = User::create([
            'id' => (string) Str::ulid(), 'tenant_id' => $tid, 'customer_id' => null,
            'name' => 'Alex Morgan', 'email' => 'owner@happy-paws.test',
            'email_verified_at' => now(), 'password' => Hash::make('password'),
            'role' => 'business_owner', 'status' => 'active',
        ]);
        $tenant->update(['owner_user_id' => $owner->id]);

        $sarah = User::create([
            'id' => (string) Str::ulid(), 'tenant_id' => $tid, 'customer_id' => null,
            'name' => 'Sarah Thompson', 'email' => 'sarah@happy-paws.test',
            'email_verified_at' => now(), 'password' => Hash::make('password'),
            'role' => 'staff', 'status' => 'active',
        ]);

        $mike = User::create([
            'id' => (string) Str::ulid(), 'tenant_id' => $tid, 'customer_id' => null,
            'name' => 'Mike Johnson', 'email' => 'mike@happy-paws.test',
            'email_verified_at' => now(), 'password' => Hash::make('password'),
            'role' => 'staff', 'status' => 'active',
        ]);

        // Packages
        $pack5   = $this->makePackage($tid, '5-Day Pack',        'Great for occasional visits.',           'one_time',     '50.00',  5,  1, null, $stripeAcct, 'price_demo_hp_5day');
        $pack10  = $this->makePackage($tid, '10-Day Pack',       'Best value for regular visitors.',       'one_time',     '90.00',  10, 1, null, $stripeAcct, 'price_demo_hp_10day');
        $pack20  = $this->makePackage($tid, '20-Day Pack',       'Our most popular pack!',                 'one_time',     '170.00', 20, 1, null, $stripeAcct, 'price_demo_hp_20day', true);
        $subPack = $this->makePackage($tid, 'Monthly Unlimited', 'Unlimited days for the whole month.',    'subscription', '150.00', 30, 1, null, $stripeAcct, 'price_demo_hp_sub');
        $famPack = $this->makePackage($tid, 'Family Pack',       'Perfect for families with 2 dogs.',      'one_time',     '160.00', 10, 2, null, $stripeAcct, 'price_demo_hp_family');

        // Customers and dogs
        [$janeUser,   $jane]   = $this->makeCustomerUser($tid, 'Jane Smith',    'jane@happy-paws.test',   '+15551234567');
        [$robertUser, $robert] = $this->makeCustomerUser($tid, 'Robert Chen',   'robert@happy-paws.test', '+15559876543');
        [$emilyUser,  $emily]  = $this->makeCustomerUser($tid, 'Emily Davis',   'emily@happy-paws.test',  '+15555551234');
        [$tomUser,    $tom]    = $this->makeCustomerUser($tid, 'Tom Wilson',     'tom@happy-paws.test',    '+15558765432');
        [$lisaUser,   $lisa]   = $this->makeCustomerUser($tid, 'Lisa Park',      'lisa@happy-paws.test',   '+15553213456');
        [$carlosUser, $carlos] = $this->makeCustomerUser($tid, 'Carlos Mendez',  'carlos@happy-paws.test', '+15556789012');

        $karen = $this->makeCustomerOnly($tid, 'Karen Williams', 'karen@happy-paws.test', '+15554567890');
        $david = $this->makeCustomerOnly($tid, 'David Kim',      null,                    '+15551112222');

        $buddy   = $this->makeDog($tid, $jane->id,   'Buddy',   'Labrador Retriever', '2019-06-15', 'male',   8);
        $max     = $this->makeDog($tid, $jane->id,   'Max',     'Beagle',             '2021-03-10', 'male',   2);
        $coco    = $this->makeDog($tid, $robert->id, 'Coco',    'Golden Retriever',   '2020-05-20', 'female', 0);
        $rocky   = $this->makeDog($tid, $robert->id, 'Rocky',   'German Shepherd',    '2018-09-14', 'male',   6);
        $daisy   = $this->makeDog($tid, $emily->id,  'Daisy',   'Poodle',             '2020-04-12', 'female', 15);
        $scout   = $this->makeDog($tid, $emily->id,  'Scout',   'Border Collie',      '2019-11-03', 'male',   0);
        $bella   = $this->makeDog($tid, $tom->id,    'Bella',   'Dachshund',          '2021-07-22', 'female', 3);
        $charlie = $this->makeDog($tid, $tom->id,    'Charlie', 'Corgi',              '2020-02-18', 'male',   5);
        $pepper  = $this->makeDog($tid, $tom->id,    'Pepper',  'Shih Tzu',           '2022-01-05', 'female', 10);
        $bruno   = $this->makeDog($tid, $lisa->id,   'Bruno',   'Bulldog',            '2019-08-30', 'male',   4);
        $bear    = $this->makeDog($tid, $lisa->id,   'Bear',    'Husky',              '2020-12-15', 'male',   1);
        $luna    = $this->makeDog($tid, $carlos->id, 'Luna',    'Chihuahua',          '2021-10-08', 'female', 23);
        $rex     = $this->makeDog($tid, $karen->id,  'Rex',     'Rottweiler',         '2018-03-25', 'male',   7);
        $zoe     = $this->makeDog($tid, $david->id,  'Zoe',     'Maltese',            '2022-06-14', 'female', 0);

        // ── Orders & Credit Ledger ─────────────────────────────────────────────

        // Buddy: 10-Day Pack (30d ago) → purchase +10, deductions ×2 = 8
        $orderBuddy = $this->makeOrder($tid, $jane->id, $pack10->id, '90.00', now()->subDays(30), 'pi_3demoBuddyPurchase000001');
        $this->makeLedger($tid, $buddy->id, 'purchase', 10, 10, ['order_id' => $orderBuddy->id, 'created_at' => now()->subDays(30)]);
        $attBuddy1 = $this->makeAttendance($tid, $buddy->id, $sarah->id, now()->subDays(25)->setHour(8), now()->subDays(25)->setHour(17), false, null, $sarah->id);
        $this->makeLedger($tid, $buddy->id, 'deduction', -1, 9, ['attendance_id' => $attBuddy1->id, 'created_at' => now()->subDays(25)]);
        $attBuddy2 = $this->makeAttendance($tid, $buddy->id, $mike->id, now()->subDays(18)->setHour(8), now()->subDays(18)->setHour(17), false, null, $mike->id);
        $this->makeLedger($tid, $buddy->id, 'deduction', -1, 8, ['attendance_id' => $attBuddy2->id, 'created_at' => now()->subDays(18)]);

        // Max: 5-Day Pack (20d ago) → purchase +5, deductions ×3 = 2 (low)
        $orderMax = $this->makeOrder($tid, $jane->id, $pack5->id, '50.00', now()->subDays(20), 'pi_3demoMaxPurchase0000001');
        $this->makeLedger($tid, $max->id, 'purchase', 5, 5, ['order_id' => $orderMax->id, 'created_at' => now()->subDays(20)]);
        foreach ([15 => 4, 10 => 3, 5 => 2] as $daysAgo => $balAfter) {
            $staffUser = $daysAgo === 10 ? $sarah : $mike;
            $attMax = $this->makeAttendance($tid, $max->id, $staffUser->id, now()->subDays($daysAgo)->setHour(8), now()->subDays($daysAgo)->setHour(16), false, null, $staffUser->id);
            $this->makeLedger($tid, $max->id, 'deduction', -1, $balAfter, ['attendance_id' => $attMax->id, 'created_at' => now()->subDays($daysAgo)]);
        }

        // Coco: 10-Day Pack (25d ago) REFUNDED (20d ago) → refund removes all
        $orderCoco = $this->makeOrder($tid, $robert->id, $pack10->id, '90.00', now()->subDays(25), 'pi_3demoCocoPurchase000001', 'refunded', now()->subDays(20));
        $lCocoPurchase = $this->makeLedger($tid, $coco->id, 'purchase', 10, 10, ['order_id' => $orderCoco->id, 'created_at' => now()->subDays(25)]);
        $attCoco1 = $this->makeAttendance($tid, $coco->id, $sarah->id, now()->subDays(24)->setHour(8), now()->subDays(24)->setHour(17), false, null, $sarah->id);
        $this->makeLedger($tid, $coco->id, 'deduction', -1, 9, ['attendance_id' => $attCoco1->id, 'created_at' => now()->subDays(24)]);
        $attCoco2 = $this->makeAttendance($tid, $coco->id, $sarah->id, now()->subDays(23)->setHour(8), now()->subDays(23)->setHour(16), false, null, $sarah->id);
        $this->makeLedger($tid, $coco->id, 'deduction', -1, 8, ['attendance_id' => $attCoco2->id, 'created_at' => now()->subDays(23)]);
        $this->makeLedger($tid, $coco->id, 'refund', -8, 0, [
            'order_id'         => $orderCoco->id,
            'parent_ledger_id' => $lCocoPurchase->id,
            'created_at'       => now()->subDays(20),
        ]);

        // Rocky: 5-Day Pack (10d ago) + goodwill +2 + correction_remove -1 = 6
        $orderRocky = $this->makeOrder($tid, $robert->id, $pack5->id, '50.00', now()->subDays(10), 'pi_3demoRockyPurchase00001');
        $this->makeLedger($tid, $rocky->id, 'purchase', 5, 5, ['order_id' => $orderRocky->id, 'created_at' => now()->subDays(10)]);
        $this->makeLedger($tid, $rocky->id, 'goodwill', 2, 7, [
            'note'       => 'Compensation for scheduling error',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(8),
        ]);
        $this->makeLedger($tid, $rocky->id, 'correction_remove', -1, 6, [
            'note'       => 'Entry correction — one extra credit removed',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(7),
        ]);

        // Daisy: Active subscription — 30 credits issued 15d ago, 15 deductions = 15
        $subDaisy = Subscription::create([
            'id'                   => (string) Str::ulid(),
            'tenant_id'            => $tid,
            'customer_id'          => $emily->id,
            'package_id'           => $subPack->id,
            'dog_id'               => $daisy->id,
            'status'               => 'active',
            'stripe_sub_id'        => 'sub_demo_daisy_monthly',
            'stripe_customer_id'   => 'cus_demo_emily_connect',
            'current_period_start' => now()->subDays(15),
            'current_period_end'   => now()->addDays(15),
            'cancelled_at'         => null,
        ]);
        $this->makeLedger($tid, $daisy->id, 'subscription', 30, 30, [
            'subscription_id' => $subDaisy->id,
            'expires_at'      => now()->addDays(15),
            'created_at'      => now()->subDays(15),
        ]);
        for ($i = 1; $i <= 15; $i++) {
            $staffUser = $i % 2 === 0 ? $sarah : $mike;
            $dayAgo = 15 - $i + 1;
            $attD = $this->makeAttendance($tid, $daisy->id, $staffUser->id, now()->subDays($dayAgo)->setHour(7), now()->subDays($dayAgo)->setHour(17), false, null, $staffUser->id);
            $this->makeLedger($tid, $daisy->id, 'deduction', -1, 30 - $i, ['attendance_id' => $attD->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Scout: Cancelled subscription — 30 credits, all consumed
        $subScout = Subscription::create([
            'id'                   => (string) Str::ulid(),
            'tenant_id'            => $tid,
            'customer_id'          => $emily->id,
            'package_id'           => $subPack->id,
            'dog_id'               => $scout->id,
            'status'               => 'cancelled',
            'stripe_sub_id'        => 'sub_demo_scout_cancelled',
            'stripe_customer_id'   => 'cus_demo_emily_connect',
            'current_period_start' => now()->subDays(45),
            'current_period_end'   => now()->subDays(15),
            'cancelled_at'         => now()->subDays(15),
        ]);
        $this->makeLedger($tid, $scout->id, 'subscription', 30, 30, [
            'subscription_id' => $subScout->id,
            'expires_at'      => now()->subDays(15),
            'created_at'      => now()->subDays(45),
        ]);
        for ($i = 1; $i <= 30; $i++) {
            $staffUser = $i % 2 === 0 ? $sarah : $mike;
            $dayAgo = 45 - $i + 1;
            $attS = $this->makeAttendance($tid, $scout->id, $staffUser->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(17), false, null, $staffUser->id);
            $this->makeLedger($tid, $scout->id, 'deduction', -1, 30 - $i, ['attendance_id' => $attS->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Bella: 20-Day Pack (60d ago, expired 30d ago) → expiry_removal + correction_add +3 = 3
        $orderBella = $this->makeOrder($tid, $tom->id, $pack20->id, '170.00', now()->subDays(60), 'pi_3demoBellaPurchase00001');
        $lBellaPurchase = $this->makeLedger($tid, $bella->id, 'purchase', 20, 20, [
            'order_id'   => $orderBella->id,
            'expires_at' => now()->subDays(30),
            'created_at' => now()->subDays(60),
        ]);
        $this->makeLedger($tid, $bella->id, 'expiry_removal', -20, 0, [
            'parent_ledger_id' => $lBellaPurchase->id,
            'created_at'       => now()->subDays(30),
        ]);
        $this->makeLedger($tid, $bella->id, 'correction_add', 3, 3, [
            'note'       => 'Courtesy credits after pack expiry',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(28),
        ]);

        // Charlie: 10-Day Pack (15d ago) → transfer_out -5 to Pepper = 5
        $orderCharlie = $this->makeOrder($tid, $tom->id, $pack10->id, '90.00', now()->subDays(15), 'pi_3demoCharliePurchase0001');
        $this->makeLedger($tid, $charlie->id, 'purchase', 10, 10, ['order_id' => $orderCharlie->id, 'created_at' => now()->subDays(15)]);
        $lTransferOut = $this->makeLedger($tid, $charlie->id, 'transfer_out', -5, 5, [
            'note'       => 'Transferred to Pepper (same owner account)',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(5),
        ]);

        // Pepper: 5-Day Pack (15d ago) → transfer_in +5 from Charlie = 10
        $orderPepper = $this->makeOrder($tid, $tom->id, $pack5->id, '50.00', now()->subDays(15), 'pi_3demoPepperPurchase00001');
        $this->makeLedger($tid, $pepper->id, 'purchase', 5, 5, ['order_id' => $orderPepper->id, 'created_at' => now()->subDays(15)]);
        $this->makeLedger($tid, $pepper->id, 'transfer_in', 5, 10, [
            'note'             => 'Transferred from Charlie (same owner account)',
            'parent_ledger_id' => $lTransferOut->id,
            'created_by'       => $owner->id,
            'created_at'       => now()->subDays(5),
        ]);

        // Bruno: 5-Day Pack (7d ago) → 1 deduction = 4
        $orderBruno = $this->makeOrder($tid, $lisa->id, $pack5->id, '50.00', now()->subDays(7), 'pi_3demoBrunoPurchase000001');
        $this->makeLedger($tid, $bruno->id, 'purchase', 5, 5, ['order_id' => $orderBruno->id, 'created_at' => now()->subDays(7)]);
        $attBruno1 = $this->makeAttendance($tid, $bruno->id, $mike->id, now()->subDays(5)->setHour(8), now()->subDays(5)->setHour(17), false, null, $mike->id);
        $this->makeLedger($tid, $bruno->id, 'deduction', -1, 4, ['attendance_id' => $attBruno1->id, 'created_at' => now()->subDays(5)]);

        // Bear: 5-Day Pack (14d ago) → 4 deductions = 1 (very low)
        $orderBear = $this->makeOrder($tid, $lisa->id, $pack5->id, '50.00', now()->subDays(14), 'pi_3demoBearPurchase0000001');
        $this->makeLedger($tid, $bear->id, 'purchase', 5, 5, ['order_id' => $orderBear->id, 'created_at' => now()->subDays(14)]);
        foreach ([11 => 4, 9 => 3, 7 => 2, 3 => 1] as $dayAgo => $balAfter) {
            $staffUser = ($dayAgo % 4 === 3) ? $sarah : $mike;
            $attBear = $this->makeAttendance($tid, $bear->id, $staffUser->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(17), false, null, $staffUser->id);
            $this->makeLedger($tid, $bear->id, 'deduction', -1, $balAfter, ['attendance_id' => $attBear->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Luna: Active subscription — 30 credits issued 7d ago, 7 deductions = 23
        $subLuna = Subscription::create([
            'id'                   => (string) Str::ulid(),
            'tenant_id'            => $tid,
            'customer_id'          => $carlos->id,
            'package_id'           => $subPack->id,
            'dog_id'               => $luna->id,
            'status'               => 'active',
            'stripe_sub_id'        => 'sub_demo_luna_monthly',
            'stripe_customer_id'   => 'cus_demo_carlos_connect',
            'current_period_start' => now()->subDays(7),
            'current_period_end'   => now()->addDays(23),
            'cancelled_at'         => null,
        ]);
        $this->makeLedger($tid, $luna->id, 'subscription', 30, 30, [
            'subscription_id' => $subLuna->id,
            'expires_at'      => now()->addDays(23),
            'created_at'      => now()->subDays(7),
        ]);
        for ($i = 1; $i <= 7; $i++) {
            $dayAgo = 7 - $i + 1;
            $attL = $this->makeAttendance($tid, $luna->id, $sarah->id, now()->subDays($dayAgo)->setHour(9), now()->subDays($dayAgo)->setHour(16), false, null, $sarah->id);
            $this->makeLedger($tid, $luna->id, 'deduction', -1, 30 - $i, ['attendance_id' => $attL->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Rex: 10-Day Pack (14d ago) → 3 deductions = 7
        $orderRex = $this->makeOrder($tid, $karen->id, $pack10->id, '90.00', now()->subDays(14), 'pi_3demoRexPurchase0000001');
        $this->makeLedger($tid, $rex->id, 'purchase', 10, 10, ['order_id' => $orderRex->id, 'created_at' => now()->subDays(14)]);
        foreach ([12 => 9, 8 => 8, 4 => 7] as $dayAgo => $balAfter) {
            $staffUser = $dayAgo === 8 ? $sarah : $mike;
            $attR = $this->makeAttendance($tid, $rex->id, $staffUser->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(17), false, null, $staffUser->id);
            $this->makeLedger($tid, $rex->id, 'deduction', -1, $balAfter, ['attendance_id' => $attR->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Zoe: 5-Day Pack (10d ago) → 5 deductions = 0, currently checked in with override
        $orderZoe = $this->makeOrder($tid, $david->id, $pack5->id, '50.00', now()->subDays(10), 'pi_3demoZoePurchase00000001');
        $this->makeLedger($tid, $zoe->id, 'purchase', 5, 5, ['order_id' => $orderZoe->id, 'created_at' => now()->subDays(10)]);
        for ($i = 1; $i <= 5; $i++) {
            $dayAgo = 10 - $i;
            $attZ = $this->makeAttendance($tid, $zoe->id, $mike->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(17), false, null, $mike->id);
            $this->makeLedger($tid, $zoe->id, 'deduction', -1, 5 - $i, ['attendance_id' => $attZ->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Currently checked in: Buddy (normal) + Zoe (zero-credit override)
        $this->makeAttendance($tid, $buddy->id, $sarah->id, now()->subHours(3), null);
        $this->makeAttendance($tid, $zoe->id, $sarah->id, now()->subHours(2), null, true, 'Customer paid cash at front desk');

        // ── Notifications ──────────────────────────────────────────────────────

        $this->seedNotification($janeUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $90.00 for 10-Day Pack was confirmed.',
            [], now()->subDays(25), now()->subDays(30));
        $this->seedNotification($janeUser->id, $tid, 'credits.low', 'Low Credits Alert',
            'Max is running low on credits (2 remaining).',
            ['dog_name' => 'Max'], null, now()->subDays(5));

        $this->seedNotification($robertUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $90.00 for 10-Day Pack was confirmed.',
            [], now()->subDays(22), now()->subDays(25));
        $this->seedNotification($robertUser->id, $tid, 'payment.refunded', 'Refund Processed',
            'Your refund of $90.00 for 10-Day Pack has been processed.',
            [], null, now()->subDays(20));

        $this->seedNotification($emilyUser->id, $tid, 'subscription.renewed', 'Subscription Renewed',
            "Daisy's Monthly Unlimited subscription has been renewed.",
            ['dog_name' => 'Daisy'], now()->subDays(10), now()->subDays(15));
        $this->seedNotification($emilyUser->id, $tid, 'subscription.cancelled', 'Subscription Cancelled',
            "Scout's Monthly Unlimited subscription has been cancelled.",
            ['dog_name' => 'Scout'], now()->subDays(12), now()->subDays(15));
        $this->seedNotification($emilyUser->id, $tid, 'credits.empty', 'No Credits Remaining',
            "Scout has no credits remaining. Purchase a pack to book visits.",
            ['dog_name' => 'Scout'], null, now()->subDays(14));

        $this->seedNotification($tomUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $170.00 for 20-Day Pack was confirmed.',
            [], now()->subDays(55), now()->subDays(60));
        $this->seedNotification($tomUser->id, $tid, 'credits.empty', 'No Credits Remaining',
            "Bella's credits have expired. Purchase a new pack to continue visits.",
            ['dog_name' => 'Bella'], null, now()->subDays(30));

        $this->seedNotification($lisaUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $50.00 for 5-Day Pack was confirmed.',
            [], now()->subDays(10), now()->subDays(14));
        $this->seedNotification($lisaUser->id, $tid, 'credits.low', 'Low Credits Alert',
            'Bear is running low on credits (1 remaining).',
            ['dog_name' => 'Bear'], null, now()->subDays(1));

        $this->seedNotification($carlosUser->id, $tid, 'subscription.renewed', 'Subscription Renewed',
            "Luna's Monthly Unlimited subscription has been renewed.",
            ['dog_name' => 'Luna'], now()->subDays(5), now()->subDays(7));

        $this->command->info('  [✓] Happy Paws Daycare (happy-paws) — pro, 14 dogs, all 10 ledger types');
    }

    // ── Coastal Canines — Starter plan, not yet onboarded ────────────────────

    private function seedCoastalCanines(): void
    {
        $tid = (string) Str::ulid();

        $tenant = Tenant::create([
            'id'                         => $tid,
            'name'                       => 'Coastal Canines',
            'slug'                       => 'coastal-canines',
            'owner_user_id'              => null,
            'status'                     => 'active',
            'stripe_account_id'          => null,
            'stripe_onboarded_at'        => null,
            'platform_fee_pct'           => '5.00',
            'payout_schedule'            => 'weekly',
            'low_credit_threshold'       => 2,
            'checkin_block_at_zero'      => false,
            'timezone'                   => 'America/Los_Angeles',
            'primary_color'              => '#0ea5e9',
            'plan'                       => 'starter',
            'plan_billing_cycle'         => 'monthly',
            'plan_current_period_end'    => now()->addDays(21),
            'platform_stripe_customer_id' => 'cus_demo_coastal',
            'platform_stripe_sub_id'     => 'sub_demo_coastal',
        ]);

        // No Stripe Connect onboarding yet — set placeholder
        $tenant->update(['stripe_account_id' => 'acct_1DemoCoastalCanines']);

        // Users
        $owner = User::create([
            'id' => (string) Str::ulid(), 'tenant_id' => $tid, 'customer_id' => null,
            'name' => 'Jamie Rivera', 'email' => 'owner@coastal-canines.test',
            'email_verified_at' => now(), 'password' => Hash::make('password'),
            'role' => 'business_owner', 'status' => 'active',
        ]);
        $tenant->update(['owner_user_id' => $owner->id]);

        $staffUser = User::create([
            'id' => (string) Str::ulid(), 'tenant_id' => $tid, 'customer_id' => null,
            'name' => 'Taylor Brooks', 'email' => 'staff@coastal-canines.test',
            'email_verified_at' => now(), 'password' => Hash::make('password'),
            'role' => 'staff', 'status' => 'active',
        ]);

        // Packages
        $stripeAcct = 'acct_1DemoCoastalCanines';
        $pack5   = $this->makePackage($tid, '5-Day Pack',          'Great starter pack.',                 'one_time',     '45.00',  5,  1, null, $stripeAcct, 'price_demo_cc_5day');
        $pack10  = $this->makePackage($tid, '10-Day Pack',         'Best value pack.',                    'one_time',     '85.00',  10, 1, null, $stripeAcct, 'price_demo_cc_10day');
        $subPack = $this->makePackage($tid, 'Monthly Subscription', 'Monthly unlimited access.',          'subscription', '120.00', 30, 1, null, $stripeAcct, 'price_demo_cc_sub');

        // Customers and dogs
        [$amyUser,    $amy]    = $this->makeCustomerUser($tid, 'Amy Garcia',    'amy@coastal-canines.test',    '+14151234567');
        [$brianUser,  $brian]  = $this->makeCustomerUser($tid, 'Brian Lee',     'brian@coastal-canines.test',  '+14159876543');
        [$rachelUser, $rachel] = $this->makeCustomerUser($tid, 'Rachel Kim',    'rachel@coastal-canines.test', '+14155551234');
        $marcus = $this->makeCustomerOnly($tid, 'Marcus Johnson', null, '+14153214321');

        $milo   = $this->makeDog($tid, $amy->id,    'Milo',   'Labrador Mix',      '2021-03-15', 'male',   1);
        $rosie  = $this->makeDog($tid, $amy->id,    'Rosie',  'Cocker Spaniel',    '2020-08-22', 'female', 3);
        $duke   = $this->makeDog($tid, $brian->id,  'Duke',   'Doberman',          '2019-05-10', 'male',   20);
        $bailey = $this->makeDog($tid, $brian->id,  'Bailey', 'Weimaraner',        '2022-02-14', 'female', 0);
        $mocha  = $this->makeDog($tid, $rachel->id, 'Mocha',  'Chocolate Labrador','2020-11-30', 'female', 8);
        $teddy  = $this->makeDog($tid, $rachel->id, 'Teddy',  'Golden Retriever',  '2021-06-18', 'male',   12);
        $ginger = $this->makeDog($tid, $marcus->id, 'Ginger', 'Irish Setter',      '2020-04-05', 'female', 5);

        // Milo: 5-Day Pack + history
        $orderMilo = $this->makeOrder($tid, $amy->id, $pack5->id, '45.00', now()->subDays(12), 'pi_3demoMiloPurchase000001');
        $this->makeLedger($tid, $milo->id, 'purchase', 5, 5, ['order_id' => $orderMilo->id, 'created_at' => now()->subDays(12)]);
        foreach ([10 => 4, 7 => 3, 5 => 2, 2 => 1] as $dayAgo => $bal) {
            $attM = $this->makeAttendance($tid, $milo->id, $staffUser->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(17));
            $this->makeLedger($tid, $milo->id, 'deduction', -1, $bal, ['attendance_id' => $attM->id, 'created_at' => now()->subDays($dayAgo)]);
        }
        // Rosie: 5-Day Pack
        $orderRosie = $this->makeOrder($tid, $amy->id, $pack5->id, '45.00', now()->subDays(10), 'pi_3demoRosiePurchase00001');
        $this->makeLedger($tid, $rosie->id, 'purchase', 5, 5, ['order_id' => $orderRosie->id, 'created_at' => now()->subDays(10)]);
        foreach ([8 => 4, 6 => 3] as $dayAgo => $bal) {
            $attR = $this->makeAttendance($tid, $rosie->id, $staffUser->id, now()->subDays($dayAgo)->setHour(9), now()->subDays($dayAgo)->setHour(17));
            $this->makeLedger($tid, $rosie->id, 'deduction', -1, $bal, ['attendance_id' => $attR->id, 'created_at' => now()->subDays($dayAgo)]);
        }
        // Rosie balance: 5-2=3 ✓

        // Duke: Active subscription — 30 credits, 10 deductions = 20
        $subDuke = Subscription::create([
            'id'                   => (string) Str::ulid(),
            'tenant_id'            => $tid,
            'customer_id'          => $brian->id,
            'package_id'           => $subPack->id,
            'dog_id'               => $duke->id,
            'status'               => 'active',
            'stripe_sub_id'        => 'sub_demo_duke_monthly',
            'stripe_customer_id'   => 'cus_demo_brian_cc',
            'current_period_start' => now()->subDays(10),
            'current_period_end'   => now()->addDays(20),
            'cancelled_at'         => null,
        ]);
        $this->makeLedger($tid, $duke->id, 'subscription', 30, 30, [
            'subscription_id' => $subDuke->id,
            'expires_at'      => now()->addDays(20),
            'created_at'      => now()->subDays(10),
        ]);
        for ($i = 1; $i <= 10; $i++) {
            $dayAgo = 10 - $i + 1;
            $attDuke = $this->makeAttendance($tid, $duke->id, $staffUser->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(17));
            $this->makeLedger($tid, $duke->id, 'deduction', -1, 30 - $i, ['attendance_id' => $attDuke->id, 'created_at' => now()->subDays($dayAgo)]);
        }
        // Duke balance: 30-10=20 ✓

        // Bailey: 0 credits — never purchased
        // (created with 0 credits, no ledger entries needed)

        // Mocha: 10-Day Pack → 2 deductions = 8
        $orderMocha = $this->makeOrder($tid, $rachel->id, $pack10->id, '85.00', now()->subDays(14), 'pi_3demoMochaPurchase00001');
        $this->makeLedger($tid, $mocha->id, 'purchase', 10, 10, ['order_id' => $orderMocha->id, 'created_at' => now()->subDays(14)]);
        foreach ([11 => 9, 9 => 8] as $dayAgo => $bal) {
            $attMc = $this->makeAttendance($tid, $mocha->id, $owner->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(16));
            $this->makeLedger($tid, $mocha->id, 'deduction', -1, $bal, ['attendance_id' => $attMc->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Teddy: 10-Day Pack → 0 deductions (just purchased) = 12... wait, 10-Day pack gives 10.
        // Let me give Rachel a 10-Day pack for Teddy, then add 2 goodwill = 12.
        $orderTeddy = $this->makeOrder($tid, $rachel->id, $pack10->id, '85.00', now()->subDays(5), 'pi_3demoTeddyPurchase000001');
        $this->makeLedger($tid, $teddy->id, 'purchase', 10, 10, ['order_id' => $orderTeddy->id, 'created_at' => now()->subDays(5)]);
        $this->makeLedger($tid, $teddy->id, 'goodwill', 2, 12, [
            'note'       => 'Welcome bonus for new member',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(5),
        ]);
        // Teddy balance: 10+2=12 ✓

        // Ginger: 5-Day Pack
        $orderGinger = $this->makeOrder($tid, $marcus->id, $pack5->id, '45.00', now()->subDays(8), 'pi_3demoGingerPurchase0001');
        $this->makeLedger($tid, $ginger->id, 'purchase', 5, 5, ['order_id' => $orderGinger->id, 'created_at' => now()->subDays(8)]);
        // Ginger balance: 5 ✓

        // Notifications
        $this->seedNotification($amyUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $45.00 for 5-Day Pack was confirmed.', [], now()->subDays(10), now()->subDays(12));
        $this->seedNotification($brianUser->id, $tid, 'subscription.renewed', 'Subscription Renewed',
            "Duke's Monthly Subscription has been renewed.", ['dog_name' => 'Duke'], now()->subDays(8), now()->subDays(10));
        $this->seedNotification($rachelUser->id, $tid, 'credits.low', 'Low Credits Alert',
            'Rosie is running low on credits (3 remaining).', ['dog_name' => 'Rosie'], null, now()->subDays(3));

        $this->command->info('  [✓] Coastal Canines (coastal-canines) — starter, 7 dogs');
    }

    // ── Bark Box Boarding — Free tier, trialing ───────────────────────────────

    private function seedBarkBox(): void
    {
        $tid = (string) Str::ulid();

        $tenant = Tenant::create([
            'id'                         => $tid,
            'name'                       => 'Bark Box Boarding',
            'slug'                       => 'bark-box',
            'owner_user_id'              => null,
            'status'                     => 'trialing',
            'stripe_account_id'          => null,
            'stripe_onboarded_at'        => null,
            'platform_fee_pct'           => '5.00',
            'payout_schedule'            => 'monthly',
            'low_credit_threshold'       => 1,
            'checkin_block_at_zero'      => false,
            'timezone'                   => 'America/Chicago',
            'primary_color'              => '#f97316',
            'plan'                       => 'free',
            'plan_billing_cycle'         => null,
            'plan_current_period_end'    => now()->addDays(21),
            'trial_started_at'           => now()->subDays(7),
            'trial_ends_at'              => now()->addDays(21),
            'platform_stripe_customer_id' => 'cus_demo_barkbox',
            'platform_stripe_sub_id'     => null,
        ]);

        // Users
        $owner = User::create([
            'id' => (string) Str::ulid(), 'tenant_id' => $tid, 'customer_id' => null,
            'name' => 'Casey Bell', 'email' => 'owner@bark-box.test',
            'email_verified_at' => now(), 'password' => Hash::make('password'),
            'role' => 'business_owner', 'status' => 'active',
        ]);
        $tenant->update(['owner_user_id' => $owner->id]);

        // Packages
        $stripeAcct = 'acct_1DemoBarkBox';
        $pack5 = $this->makePackage($tid, '5-Day Pack', 'Perfect for trying us out!', 'one_time', '40.00', 5, 1, null, $stripeAcct, 'price_demo_bb_5day');

        // Customers and dogs
        [$chrisUser, $chris] = $this->makeCustomerUser($tid, 'Chris Taylor', 'chris@bark-box.test', '+13121234567');
        $pat = $this->makeCustomerOnly($tid, 'Pat Williams', null, '+13129876543');

        $peanut = $this->makeDog($tid, $chris->id, 'Peanut', 'Miniature Poodle', '2022-04-10', 'male',   5);
        $noodle = $this->makeDog($tid, $chris->id, 'Noodle', 'Dachshund Mix',    '2021-09-22', 'female', 0);
        $ace    = $this->makeDog($tid, $pat->id,   'Ace',    'Boxer',            '2020-07-15', 'male',   3);

        // Peanut: 5-Day Pack (3d ago) → 0 deductions = 5
        $orderPeanut = $this->makeOrder($tid, $chris->id, $pack5->id, '40.00', now()->subDays(3), 'pi_3demoPeanutPurchase0001');
        $this->makeLedger($tid, $peanut->id, 'purchase', 5, 5, ['order_id' => $orderPeanut->id, 'created_at' => now()->subDays(3)]);

        // Noodle: 0 credits — not yet purchased
        // Ace: 5-Day Pack (5d ago) → 2 deductions = 3
        $orderAce = $this->makeOrder($tid, $pat->id, $pack5->id, '40.00', now()->subDays(5), 'pi_3demoAcePurchase000001');
        $this->makeLedger($tid, $ace->id, 'purchase', 5, 5, ['order_id' => $orderAce->id, 'created_at' => now()->subDays(5)]);
        foreach ([4 => 4, 2 => 3] as $dayAgo => $bal) {
            $attAce = $this->makeAttendance($tid, $ace->id, $owner->id, now()->subDays($dayAgo)->setHour(8), now()->subDays($dayAgo)->setHour(17));
            $this->makeLedger($tid, $ace->id, 'deduction', -1, $bal, ['attendance_id' => $attAce->id, 'created_at' => now()->subDays($dayAgo)]);
        }

        // Notifications
        $this->seedNotification($chrisUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $40.00 for 5-Day Pack was confirmed.', [], null, now()->subDays(3));
        $this->seedNotification($chrisUser->id, $tid, 'credits.empty', 'No Credits Remaining',
            'Noodle has no credits. Purchase a pack to start booking visits.', ['dog_name' => 'Noodle'], null, now()->subDays(1));

        $this->command->info('  [✓] Bark Box Boarding (bark-box) — free (trialing), 3 dogs');
    }

    // ── Credentials summary ───────────────────────────────────────────────────

    private function printCredentials(): void
    {
        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Demo Login Credentials (all passwords: password)');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Platform Admin:           admin@pawpass.test');
        $this->command->newLine();
        $this->command->info('  Happy Paws (pro):');
        $this->command->info('    Owner:   owner@happy-paws.test');
        $this->command->info('    Staff:   sarah@happy-paws.test');
        $this->command->info('    Staff:   mike@happy-paws.test');
        $this->command->info('    Customer (Buddy 8cr, Max 2cr):  jane@happy-paws.test');
        $this->command->info('    Customer (Coco 0cr, Rocky 6cr): robert@happy-paws.test');
        $this->command->info('    Customer (Daisy sub, Scout 0cr): emily@happy-paws.test');
        $this->command->info('    Customer (Bella 3cr, Charlie 5cr, Pepper 10cr): tom@happy-paws.test');
        $this->command->info('    Customer (Bruno 4cr, Bear 1cr): lisa@happy-paws.test');
        $this->command->info('    Customer (Luna sub 23cr): carlos@happy-paws.test');
        $this->command->newLine();
        $this->command->info('  Coastal Canines (starter):');
        $this->command->info('    Owner:   owner@coastal-canines.test');
        $this->command->info('    Customer (Milo 1cr, Rosie 3cr): amy@coastal-canines.test');
        $this->command->info('    Customer (Duke sub 20cr):       brian@coastal-canines.test');
        $this->command->info('    Customer (Mocha 8cr, Teddy 12cr): rachel@coastal-canines.test');
        $this->command->newLine();
        $this->command->info('  Bark Box (free / trialing):');
        $this->command->info('    Owner:   owner@bark-box.test');
        $this->command->info('    Customer (Peanut 5cr, Noodle 0cr): chris@bark-box.test');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->newLine();
        $this->command->info('  Verify ledger types: php artisan tinker');
        $this->command->info("    CreditLedger::distinct()->pluck('type')");
        $this->command->newLine();
    }
}
