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
use App\Services\StripeService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ShowcaseSeeder — Paw Showcase Daycare
 *
 * Creates one fully-featured demo tenant that exercises every PawPass feature
 * and connects every record to real Stripe objects when valid test keys are
 * present. Falls back to descriptive fake IDs automatically, so this seeder
 * always runs regardless of environment.
 *
 * Usage:
 *   php artisan db:seed --class=ShowcaseSeeder
 *
 * For real Stripe integration set in .env:
 *   STRIPE_SECRET=sk_test_...          (Connect-capable test key)
 *   STRIPE_BILLING_SECRET=sk_test_...  (Platform billing test key — optional)
 */
class ShowcaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private bool $hasRealStripe = false;

    private StripeService $stripe;

    // Collected Stripe IDs for the credential summary
    private array $stripeIds = [];

    public function run(): void
    {
        $this->hasRealStripe = $this->isRealStripeKey(
            config('services.stripe.secret', env('STRIPE_SECRET', ''))
        );

        $this->stripe = app(StripeService::class);

        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  PawPass Showcase Seeder — Paw Showcase Daycare');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($this->hasRealStripe) {
            $this->command->info('  Stripe mode : LIVE (test keys detected — real objects will be created)');
        } else {
            $this->command->warn('  Stripe mode : FAKE (no valid test key — placeholder IDs will be used)');
            $this->command->warn('  Set STRIPE_SECRET=sk_test_... in .env for live Stripe integration.');
        }

        $this->command->newLine();

        $this->seedShowcaseTenant();
        $this->printSummary();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function isRealStripeKey(string $key): bool
    {
        return strlen($key) > 30
            && ! in_array($key, ['sk_test_placeholder', 'sk_live_placeholder', '']);
    }

    /** Create a Stripe customer on the connected account; fall back to a fake ID. */
    private function stripeCustomer(string $acctId, string $email, string $name): string
    {
        if (! $this->hasRealStripe) {
            return 'cus_showcase_' . Str::slug($name, '_');
        }
        try {
            $cus = $this->stripe->createCustomer($email, $name, $acctId);
            return $cus->id;
        } catch (\Exception $e) {
            $this->command->warn("    Stripe customer creation failed ({$name}): {$e->getMessage()}");
            return 'cus_showcase_' . Str::slug($name, '_');
        }
    }

    /**
     * Create a real payment method from a Stripe test token on the connected account.
     * Returns the payment method ID (real or fake).
     */
    private function stripePaymentMethod(string $acctId, string $token, string $label): string
    {
        if (! $this->hasRealStripe) {
            return 'pm_showcase_' . $label;
        }
        try {
            $pm = $this->stripe->createPaymentMethodFromToken($token, $acctId);
            return $pm->id;
        } catch (\Exception $e) {
            $this->command->warn("    PM creation failed ({$label}): {$e->getMessage()}");
            return 'pm_showcase_' . $label;
        }
    }

    /**
     * Attach a payment method to a Stripe customer on the connected account.
     */
    private function attachPm(string $pmId, string $cusId, string $acctId): void
    {
        if (! $this->hasRealStripe || str_starts_with($pmId, 'pm_showcase_')) {
            return;
        }
        try {
            $this->stripe->attachPaymentMethod($pmId, $cusId, $acctId);
        } catch (\Exception $e) {
            $this->command->warn("    PM attach failed: {$e->getMessage()}");
        }
    }

    /**
     * Confirm a PaymentIntent on the connected account and return its ID.
     * For refunded orders the PI is created then immediately refunded.
     */
    private function stripePaymentIntent(
        string $acctId,
        int $amountCents,
        string $cusId,
        string $pmId,
        string $fakeId,
        array $metadata = [],
        bool $refund = false
    ): array {
        if (! $this->hasRealStripe || str_starts_with($cusId, 'cus_showcase_')) {
            return ['pi_id' => $fakeId, 'refund_id' => $refund ? 're_showcase_' . Str::random(8) : null];
        }
        try {
            $applicationFee = (int) round($amountCents * 0.05);
            $pi = $this->stripe->createPaymentIntent(
                amountCents: $amountCents,
                currency: 'usd',
                stripeAccountId: $acctId,
                applicationFeeCents: $applicationFee,
                metadata: $metadata,
                stripeCustomerId: $cusId,
                confirm: true,
                paymentMethodId: $pmId,
                paymentMethodTypes: ['card'],
            );
            $refundId = null;
            if ($refund) {
                $r = $this->stripe->createRefund($pi->id, $acctId);
                $refundId = $r->id;
            }
            return ['pi_id' => $pi->id, 'refund_id' => $refundId];
        } catch (\Exception $e) {
            $this->command->warn("    PaymentIntent failed: {$e->getMessage()}");
            return ['pi_id' => $fakeId, 'refund_id' => $refund ? 're_showcase_err' : null];
        }
    }

    /** Create a real Stripe subscription on the connected account. */
    private function stripeSubscription(
        string $acctId,
        string $cusId,
        string $priceId,
        string $pmId,
        string $fakeSubId,
        array $metadata = []
    ): string {
        if (! $this->hasRealStripe || str_starts_with($cusId, 'cus_showcase_') || str_starts_with($priceId, 'price_showcase_')) {
            return $fakeSubId;
        }
        try {
            $sub = $this->stripe->createSubscription($cusId, $priceId, $pmId, $acctId, 5.0, $metadata);
            return $sub->id;
        } catch (\Exception $e) {
            $this->command->warn("    Subscription creation failed: {$e->getMessage()}");
            return $fakeSubId;
        }
    }

    /** Create a Stripe product + price, returning [productId, priceId]. */
    private function stripeProductAndPrice(
        string $acctId,
        string $name,
        int $amountCents,
        ?string $interval,
        string $fakeProductId,
        string $fakePriceId
    ): array {
        if (! $this->hasRealStripe) {
            return [$fakeProductId, $fakePriceId];
        }
        try {
            $product = $this->stripe->createProduct($name, $acctId);
            $price   = $this->stripe->createPrice($product->id, $amountCents, 'usd', $interval, $acctId);
            return [$product->id, $price->id];
        } catch (\Exception $e) {
            $this->command->warn("    Stripe product/price failed ({$name}): {$e->getMessage()}");
            return [$fakeProductId, $fakePriceId];
        }
    }

    // ─── Record builders ──────────────────────────────────────────────────────

    private function makeUser(array $attrs): User
    {
        return User::create(array_merge([
            'id'                => (string) Str::ulid(),
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'status'            => 'active',
            'customer_id'       => null,
        ], $attrs));
    }

    /** @return array{0: User, 1: Customer} */
    private function makeCustomerUser(
        string $tid,
        string $name,
        string $email,
        ?string $phone,
        string $stripeCusId
    ): array {
        $user = $this->makeUser([
            'tenant_id' => $tid,
            'name'      => $name,
            'email'     => $email,
            'role'      => 'customer',
        ]);

        $customer = Customer::create([
            'id'                => (string) Str::ulid(),
            'tenant_id'         => $tid,
            'user_id'           => $user->id,
            'name'              => $name,
            'email'             => $email,
            'phone'             => $phone,
            'stripe_customer_id' => $stripeCusId,
        ]);

        $user->update(['customer_id' => $customer->id]);

        return [$user, $customer];
    }

    private function makeDog(
        string $tid,
        string $customerId,
        string $name,
        string $breed,
        string $dob,
        string $sex,
        int $creditBalance,
        array $extra = []
    ): Dog {
        return Dog::create(array_merge([
            'id'             => (string) Str::ulid(),
            'tenant_id'      => $tid,
            'customer_id'    => $customerId,
            'name'           => $name,
            'breed'          => $breed,
            'dob'            => $dob,
            'sex'            => $sex,
            'credit_balance' => $creditBalance,
        ], $extra));
    }

    private function makeOrder(
        string $tid,
        string $customerId,
        string $packageId,
        string $amount,
        mixed $paidAt,
        string $piId,
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
            'stripe_pi_id'          => $piId,
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
            'id'                   => (string) Str::ulid(),
            'tenant_id'            => $tid,
            'dog_id'               => $dogId,
            'checked_in_by'        => $checkedInBy,
            'checked_out_by'       => $checkedOutAt !== null ? ($checkedOutBy ?? $checkedInBy) : null,
            'checked_in_at'        => $checkedInAt,
            'checked_out_at'       => $checkedOutAt,
            'zero_credit_override' => $zeroOverride,
            'override_note'        => $overrideNote,
            'edited_by'            => null,
            'edited_at'            => null,
            'edit_note'            => null,
            'original_in'          => null,
            'original_out'         => null,
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
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
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

    // ─── Tenant seed ──────────────────────────────────────────────────────────

    private function seedShowcaseTenant(): void
    {
        $tid = (string) Str::ulid();

        // ── Tenant ────────────────────────────────────────────────────────────
        $tenant = Tenant::create([
            'id'                          => $tid,
            'name'                        => 'Paw Showcase Daycare',
            'slug'                        => 'paw-showcase',
            'owner_user_id'               => null,
            'status'                      => 'active',
            'stripe_account_id'           => null,
            'stripe_onboarded_at'         => null,
            'platform_fee_pct'            => '5.00',
            'payout_schedule'             => 'daily',
            'low_credit_threshold'        => 2,
            'checkin_block_at_zero'       => true,
            'timezone'                    => 'America/New_York',
            'primary_color'               => '#16a34a',
            'plan'                        => 'pro',
            'plan_billing_cycle'          => 'monthly',
            'plan_current_period_end'     => now()->addDays(14),
            'platform_stripe_customer_id' => 'cus_showcase_platform',
            'platform_stripe_sub_id'      => 'sub_showcase_platform',
        ]);

        // Stripe Connect account
        $stripeAcct = 'acct_1ShowcasePawPass';
        if ($this->hasRealStripe) {
            try {
                $acct = $this->stripe->createConnectAccount('owner@paw-showcase.test', 'Paw Showcase Daycare');
                $stripeAcct = $acct->id;
                $tenant->update(['stripe_account_id' => $stripeAcct, 'stripe_onboarded_at' => now()]);
            } catch (\Exception $e) {
                $this->command->warn("  Stripe Connect account skipped: {$e->getMessage()}");
                $tenant->update(['stripe_account_id' => $stripeAcct]);
            }
        } else {
            $tenant->update(['stripe_account_id' => $stripeAcct]);
        }

        $this->stripeIds['connect_account'] = $stripeAcct;
        $this->command->info("  [+] Tenant:  Paw Showcase Daycare  (slug: paw-showcase)");
        $this->command->info("      Stripe Connect: {$stripeAcct}");

        // ── Staff ─────────────────────────────────────────────────────────────
        $owner = $this->makeUser([
            'tenant_id' => $tid,
            'name'      => 'Jordan Ellis',
            'email'     => 'owner@paw-showcase.test',
            'role'      => 'business_owner',
        ]);
        $tenant->update(['owner_user_id' => $owner->id]);

        $staff1 = $this->makeUser([
            'tenant_id' => $tid,
            'name'      => 'Morgan Hayes',
            'email'     => 'morgan@paw-showcase.test',
            'role'      => 'staff',
        ]);

        $staff2 = $this->makeUser([
            'tenant_id' => $tid,
            'name'      => 'Riley Park',
            'email'     => 'riley@paw-showcase.test',
            'role'      => 'staff',
        ]);

        // ── Packages ──────────────────────────────────────────────────────────
        [$prod5, $price5]     = $this->stripeProductAndPrice($stripeAcct, 'Starter 5-Day Pack',    5500,   null,    'prod_showcase_5day',  'price_showcase_5day');
        [$prod10, $price10]   = $this->stripeProductAndPrice($stripeAcct, 'Popular 10-Day Pack',   9900,   null,    'prod_showcase_10day', 'price_showcase_10day');
        [$prodUnlim, $priceUnlim] = $this->stripeProductAndPrice($stripeAcct, 'Unlimited Month Pass', 19500, null,  'prod_showcase_unlim', 'price_showcase_unlim');
        [$prodSub, $priceSub] = $this->stripeProductAndPrice($stripeAcct, 'Monthly Subscription',  14500,  'month', 'prod_showcase_sub',   'price_showcase_sub');

        $pack5 = Package::create([
            'id'               => (string) Str::ulid(),
            'tenant_id'        => $tid,
            'name'             => 'Starter 5-Day Pack',
            'description'      => 'Perfect for occasional visits.',
            'type'             => 'one_time',
            'price'            => '55.00',
            'credit_count'     => 5,
            'dog_limit'        => 1,
            'duration_days'    => null,
            'is_active'        => true,
            'is_featured'      => false,
            'stripe_product_id' => $prod5,
            'stripe_price_id'  => $price5,
        ]);
        $pack10 = Package::create([
            'id'               => (string) Str::ulid(),
            'tenant_id'        => $tid,
            'name'             => 'Popular 10-Day Pack',
            'description'      => 'Best value for regular visitors.',
            'type'             => 'one_time',
            'price'            => '99.00',
            'credit_count'     => 10,
            'dog_limit'        => 1,
            'duration_days'    => null,
            'is_active'        => true,
            'is_featured'      => true,
            'stripe_product_id' => $prod10,
            'stripe_price_id'  => $price10,
        ]);
        $packUnlim = Package::create([
            'id'               => (string) Str::ulid(),
            'tenant_id'        => $tid,
            'name'             => 'Unlimited Month Pass',
            'description'      => 'Unlimited daycare days for 30 days.',
            'type'             => 'unlimited',
            'price'            => '195.00',
            'credit_count'     => 30,
            'dog_limit'        => 1,
            'duration_days'    => 30,
            'is_active'        => true,
            'is_featured'      => false,
            'stripe_product_id' => $prodUnlim,
            'stripe_price_id'  => $priceUnlim,
        ]);
        $packSub = Package::create([
            'id'               => (string) Str::ulid(),
            'tenant_id'        => $tid,
            'name'             => 'Monthly Subscription',
            'description'      => '30 credits per month, auto-renewing.',
            'type'             => 'subscription',
            'price'            => '145.00',
            'credit_count'     => 30,
            'dog_limit'        => 1,
            'duration_days'    => null,
            'is_active'        => true,
            'is_featured'      => false,
            'stripe_product_id' => $prodSub,
            'stripe_price_id'  => $priceSub,
        ]);

        $this->stripeIds['packages'] = [
            '5-Day Pack'          => ['product' => $prod5,      'price' => $price5],
            '10-Day Pack'         => ['product' => $prod10,     'price' => $price10],
            'Unlimited Month Pass'=> ['product' => $prodUnlim,  'price' => $priceUnlim],
            'Monthly Subscription'=> ['product' => $prodSub,    'price' => $priceSub],
        ];

        $this->command->info("  [+] Packages: 5-Day ($price5) | 10-Day ($price10) | Unlimited ($priceUnlim) | Sub ($priceSub)");

        // ── Customer 1: Sarah Chen — Visa ─────────────────────────────────────
        $sarahCusId = $this->stripeCustomer($stripeAcct, 'sarah@paw-showcase.test', 'Sarah Chen');
        $sarahPmId  = $this->stripePaymentMethod($stripeAcct, 'tok_visa', 'visa_sarah');
        $this->attachPm($sarahPmId, $sarahCusId, $stripeAcct);

        [$sarahUser, $sarah] = $this->makeCustomerUser(
            $tid, 'Sarah Chen', 'sarah@paw-showcase.test', '+12125551001', $sarahCusId
        );

        $this->stripeIds['customers']['sarah'] = ['customer' => $sarahCusId, 'pm' => $sarahPmId, 'brand' => 'Visa'];
        $this->command->info("  [+] Customer: Sarah Chen  cus={$sarahCusId}  pm={$sarahPmId}");

        // Biscuit — 10-Day Pack → 3 deductions → goodwill +2 = 9 credits
        $biscuit = $this->makeDog($tid, $sarah->id, 'Biscuit', 'Labrador Retriever', '2020-03-12', 'male', 9);
        $piA = $this->stripePaymentIntent($stripeAcct, 9900, $sarahCusId, $sarahPmId, 'pi_showcase_biscuit_10day', ['dog' => 'Biscuit', 'pack' => '10-Day']);
        $orderBiscuit = $this->makeOrder($tid, $sarah->id, $pack10->id, '99.00', now()->subDays(20), $piA['pi_id']);
        $lBiscuitPurchase = $this->makeLedger($tid, $biscuit->id, 'purchase', 10, 10, ['order_id' => $orderBiscuit->id, 'created_at' => now()->subDays(20)]);
        $daysAgoCheckins = [17 => 9, 14 => 8, 11 => 7];
        foreach ($daysAgoCheckins as $dAgo => $bal) {
            $staff = $dAgo === 14 ? $staff2 : $staff1;
            $att = $this->makeAttendance($tid, $biscuit->id, $staff->id, now()->subDays($dAgo)->setHour(8), now()->subDays($dAgo)->setHour(17), false, null, $staff->id);
            $this->makeLedger($tid, $biscuit->id, 'deduction', -1, $bal, ['attendance_id' => $att->id, 'created_at' => now()->subDays($dAgo)]);
        }
        $this->makeLedger($tid, $biscuit->id, 'goodwill', 2, 9, [
            'note'       => 'Compensation for a scheduling mix-up last week',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(8),
        ]);

        // Cookie — 5-Day Pack → purchased + REFUNDED = 0 credits
        $cookie = $this->makeDog($tid, $sarah->id, 'Cookie', 'Beagle', '2022-01-18', 'female', 0);
        $piB = $this->stripePaymentIntent($stripeAcct, 5500, $sarahCusId, $sarahPmId, 'pi_showcase_cookie_5day_ref', ['dog' => 'Cookie', 'pack' => '5-Day'], false);
        $orderCookie = $this->makeOrder($tid, $sarah->id, $pack5->id, '55.00', now()->subDays(15), $piB['pi_id']);
        $lCookiePurchase = $this->makeLedger($tid, $cookie->id, 'purchase', 5, 5, ['order_id' => $orderCookie->id, 'created_at' => now()->subDays(15)]);
        // Refund it
        $piCookieRefund = $this->stripePaymentIntent($stripeAcct, 5500, $sarahCusId, $sarahPmId, 'pi_showcase_cookie_5day_ref', [], true);
        $orderCookie->update(['status' => 'refunded']);
        $orderCookie->payments()->update(['status' => 'refunded', 'refunded_at' => now()->subDays(12)]);
        $this->makeLedger($tid, $cookie->id, 'refund', -5, 0, [
            'order_id'         => $orderCookie->id,
            'parent_ledger_id' => $lCookiePurchase->id,
            'created_at'       => now()->subDays(12),
        ]);

        $this->stripeIds['orders']['biscuit_10day'] = $piA['pi_id'];
        $this->stripeIds['orders']['cookie_5day']   = $piB['pi_id'];

        // Notifications
        $this->seedNotification($sarahUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $99.00 for Popular 10-Day Pack was confirmed.', [], now()->subDays(18), now()->subDays(20));
        $this->seedNotification($sarahUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $55.00 for Starter 5-Day Pack was confirmed.', [], now()->subDays(13), now()->subDays(15));
        $this->seedNotification($sarahUser->id, $tid, 'payment.refunded', 'Refund Processed',
            "Your refund of \$55.00 for Starter 5-Day Pack has been processed.", [], null, now()->subDays(12));

        $this->command->info("  [+]   Dog: Biscuit (Labrador) — 9 credits  [purchase, deduction ×3, goodwill]");
        $this->command->info("  [+]   Dog: Cookie (Beagle)    — 0 credits  [purchase, refund]");

        // ── Customer 2: Marcus Webb — Mastercard ──────────────────────────────
        $marcusCusId = $this->stripeCustomer($stripeAcct, 'marcus@paw-showcase.test', 'Marcus Webb');
        $marcusPmId  = $this->stripePaymentMethod($stripeAcct, 'tok_mastercard', 'mc_marcus');
        $this->attachPm($marcusPmId, $marcusCusId, $stripeAcct);

        [$marcusUser, $marcus] = $this->makeCustomerUser(
            $tid, 'Marcus Webb', 'marcus@paw-showcase.test', '+14155551002', $marcusCusId
        );

        $this->stripeIds['customers']['marcus'] = ['customer' => $marcusCusId, 'pm' => $marcusPmId, 'brand' => 'Mastercard'];
        $this->command->info("  [+] Customer: Marcus Webb  cus={$marcusCusId}  pm={$marcusPmId}");

        // Atlas — Active subscription (real Stripe sub) → 12 deductions = 18 credits
        $atlas = $this->makeDog($tid, $marcus->id, 'Atlas', 'German Shepherd', '2019-07-04', 'male', 18);

        // Cancelled subscription last month (demonstrates cancelled state)
        $subAtlasPrev = Subscription::create([
            'id'                   => (string) Str::ulid(),
            'tenant_id'            => $tid,
            'customer_id'          => $marcus->id,
            'package_id'           => $packSub->id,
            'dog_id'               => $atlas->id,
            'status'               => 'cancelled',
            'stripe_sub_id'        => 'sub_showcase_atlas_prev',
            'stripe_customer_id'   => $marcusCusId,
            'current_period_start' => now()->subDays(60),
            'current_period_end'   => now()->subDays(30),
            'cancelled_at'         => now()->subDays(30),
        ]);
        $this->makeLedger($tid, $atlas->id, 'subscription', 30, 30, [
            'subscription_id' => $subAtlasPrev->id,
            'expires_at'      => now()->subDays(30),
            'created_at'      => now()->subDays(60),
        ]);
        // All 30 credits consumed during previous subscription period
        for ($i = 1; $i <= 30; $i++) {
            $dAgo    = 60 - $i + 1;
            $staffU  = $i % 2 === 0 ? $staff2 : $staff1;
            $attPrev = $this->makeAttendance($tid, $atlas->id, $staffU->id, now()->subDays($dAgo)->setHour(8), now()->subDays($dAgo)->setHour(17), false, null, $staffU->id);
            $this->makeLedger($tid, $atlas->id, 'deduction', -1, 30 - $i, ['attendance_id' => $attPrev->id, 'created_at' => now()->subDays($dAgo)]);
        }

        // Active subscription this month
        $subAtlasId = $this->stripeSubscription($stripeAcct, $marcusCusId, $priceSub, $marcusPmId, 'sub_showcase_atlas_active', ['dog' => 'Atlas']);
        $subAtlas = Subscription::create([
            'id'                   => (string) Str::ulid(),
            'tenant_id'            => $tid,
            'customer_id'          => $marcus->id,
            'package_id'           => $packSub->id,
            'dog_id'               => $atlas->id,
            'status'               => 'active',
            'stripe_sub_id'        => $subAtlasId,
            'stripe_customer_id'   => $marcusCusId,
            'current_period_start' => now()->subDays(12),
            'current_period_end'   => now()->addDays(18),
            'cancelled_at'         => null,
        ]);
        $this->makeLedger($tid, $atlas->id, 'subscription', 30, 30, [
            'subscription_id' => $subAtlas->id,
            'expires_at'      => now()->addDays(18),
            'created_at'      => now()->subDays(12),
        ]);
        for ($i = 1; $i <= 12; $i++) {
            $dAgo   = 12 - $i + 1;
            $staffU = $i % 2 === 0 ? $staff2 : $staff1;
            $attCur = $this->makeAttendance($tid, $atlas->id, $staffU->id, now()->subDays($dAgo)->setHour(8), now()->subDays($dAgo)->setHour(17), false, null, $staffU->id);
            $this->makeLedger($tid, $atlas->id, 'deduction', -1, 30 - $i, ['attendance_id' => $attCur->id, 'created_at' => now()->subDays($dAgo)]);
        }

        // Nova — Expired 20-Day Pack → expiry_removal → correction_add +3 = 3 credits
        $nova = $this->makeDog($tid, $marcus->id, 'Nova', 'Border Collie', '2021-05-22', 'female', 3);
        $piC = $this->stripePaymentIntent($stripeAcct, 9900, $marcusCusId, $marcusPmId, 'pi_showcase_nova_10day', ['dog' => 'Nova', 'pack' => '10-Day']);
        $orderNova = $this->makeOrder($tid, $marcus->id, $pack10->id, '99.00', now()->subDays(50), $piC['pi_id']);
        $lNovaPurchase = $this->makeLedger($tid, $nova->id, 'purchase', 10, 10, [
            'order_id'   => $orderNova->id,
            'expires_at' => now()->subDays(20),
            'created_at' => now()->subDays(50),
        ]);
        // Used 7 credits before expiry
        for ($i = 1; $i <= 7; $i++) {
            $dAgo = 50 - ($i * 3);
            $attN = $this->makeAttendance($tid, $nova->id, $staff1->id, now()->subDays($dAgo)->setHour(9), now()->subDays($dAgo)->setHour(16), false, null, $staff1->id);
            $this->makeLedger($tid, $nova->id, 'deduction', -1, 10 - $i, ['attendance_id' => $attN->id, 'created_at' => now()->subDays($dAgo)]);
        }
        $this->makeLedger($tid, $nova->id, 'expiry_removal', -3, 0, [
            'parent_ledger_id' => $lNovaPurchase->id,
            'created_at'       => now()->subDays(20),
        ]);
        $this->makeLedger($tid, $nova->id, 'correction_add', 3, 3, [
            'note'       => 'Courtesy credits — expired pack was unused due to illness',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(18),
        ]);

        $this->stripeIds['subscriptions']['atlas_active'] = $subAtlasId;
        $this->stripeIds['orders']['nova_10day']           = $piC['pi_id'];

        // Notifications
        $this->seedNotification($marcusUser->id, $tid, 'subscription.renewed', 'Subscription Renewed',
            "Atlas's Monthly Subscription has been renewed.", ['dog_name' => 'Atlas'], now()->subDays(9), now()->subDays(12));
        $this->seedNotification($marcusUser->id, $tid, 'credits.low', 'Low Credits Alert',
            'Nova has only 3 credits remaining.', ['dog_name' => 'Nova'], null, now()->subDays(18));

        $this->command->info("  [+]   Dog: Atlas (German Shepherd) — 18 credits  [subscription ×2, deduction ×42, cancelled sub]");
        $this->command->info("      Sub ID (active): {$subAtlasId}");
        $this->command->info("  [+]   Dog: Nova (Border Collie)    — 3 credits   [purchase, deduction ×7, expiry_removal, correction_add]");

        // ── Customer 3: Priya Nair — Amex ─────────────────────────────────────
        $priyaCusId = $this->stripeCustomer($stripeAcct, 'priya@paw-showcase.test', 'Priya Nair');
        $priyaPmId  = $this->stripePaymentMethod($stripeAcct, 'tok_amex', 'amex_priya');
        $this->attachPm($priyaPmId, $priyaCusId, $stripeAcct);

        [$priyaUser, $priya] = $this->makeCustomerUser(
            $tid, 'Priya Nair', 'priya@paw-showcase.test', '+13105551003', $priyaCusId
        );

        $this->stripeIds['customers']['priya'] = ['customer' => $priyaCusId, 'pm' => $priyaPmId, 'brand' => 'Amex'];
        $this->command->info("  [+] Customer: Priya Nair  cus={$priyaCusId}  pm={$priyaPmId}");

        // Mango — Unlimited Month Pass (real PI), currently checked in
        $mango = $this->makeDog($tid, $priya->id, 'Mango', 'Golden Retriever', '2021-09-15', 'male', 30, [
            'unlimited_pass_expires_at' => now()->addDays(22),
        ]);
        $piD = $this->stripePaymentIntent($stripeAcct, 19500, $priyaCusId, $priyaPmId, 'pi_showcase_mango_unlim', ['dog' => 'Mango', 'pack' => 'Unlimited']);
        $orderMango = $this->makeOrder($tid, $priya->id, $packUnlim->id, '195.00', now()->subDays(8), $piD['pi_id']);
        $this->makeLedger($tid, $mango->id, 'purchase', 30, 30, [
            'order_id'   => $orderMango->id,
            'expires_at' => now()->addDays(22),
            'created_at' => now()->subDays(8),
        ]);
        // 5 attendances using unlimited pass (no credit deductions — unlimited pass)
        foreach ([7 => null, 6 => null, 5 => null, 3 => null, 2 => null] as $dAgo => $_) {
            $this->makeAttendance($tid, $mango->id, $staff2->id, now()->subDays($dAgo)->setHour(8), now()->subDays($dAgo)->setHour(17), false, null, $staff2->id);
        }
        // Currently checked in
        $this->makeAttendance($tid, $mango->id, $staff1->id, now()->subHours(2), null);

        // Chai — 10-Day Pack → transfer_out -4 to Spice = 6 credits
        $chai = $this->makeDog($tid, $priya->id, 'Chai', 'Poodle', '2020-11-08', 'female', 6);
        $piE = $this->stripePaymentIntent($stripeAcct, 9900, $priyaCusId, $priyaPmId, 'pi_showcase_chai_10day', ['dog' => 'Chai', 'pack' => '10-Day']);
        $orderChai = $this->makeOrder($tid, $priya->id, $pack10->id, '99.00', now()->subDays(14), $piE['pi_id']);
        $this->makeLedger($tid, $chai->id, 'purchase', 10, 10, ['order_id' => $orderChai->id, 'created_at' => now()->subDays(14)]);

        // Spice — 5-Day Pack → correction_remove -1 → transfer_in +4 from Chai = 8 credits
        $spice = $this->makeDog($tid, $priya->id, 'Spice', 'Shih Tzu', '2022-04-03', 'female', 8);
        $piF = $this->stripePaymentIntent($stripeAcct, 5500, $priyaCusId, $priyaPmId, 'pi_showcase_spice_5day', ['dog' => 'Spice', 'pack' => '5-Day']);
        $orderSpice = $this->makeOrder($tid, $priya->id, $pack5->id, '55.00', now()->subDays(14), $piF['pi_id']);
        $this->makeLedger($tid, $spice->id, 'purchase', 5, 5, ['order_id' => $orderSpice->id, 'created_at' => now()->subDays(14)]);
        $this->makeLedger($tid, $spice->id, 'correction_remove', -1, 4, [
            'note'       => 'Entry correction — one credit over-issued at registration',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(13),
        ]);

        // Transfer: Chai → Spice
        $lTransferOut = $this->makeLedger($tid, $chai->id, 'transfer_out', -4, 6, [
            'note'       => 'Transferred to Spice (same owner account)',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(6),
        ]);
        $this->makeLedger($tid, $spice->id, 'transfer_in', 4, 8, [
            'note'             => 'Transferred from Chai (same owner account)',
            'parent_ledger_id' => $lTransferOut->id,
            'created_by'       => $owner->id,
            'created_at'       => now()->subDays(6),
        ]);

        $this->stripeIds['orders']['mango_unlim']  = $piD['pi_id'];
        $this->stripeIds['orders']['chai_10day']   = $piE['pi_id'];
        $this->stripeIds['orders']['spice_5day']   = $piF['pi_id'];

        // Notifications
        $this->seedNotification($priyaUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $195.00 for Unlimited Month Pass was confirmed.', [], now()->subDays(6), now()->subDays(8));
        $this->seedNotification($priyaUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $99.00 for Popular 10-Day Pack was confirmed.', [], now()->subDays(12), now()->subDays(14));
        $this->seedNotification($priyaUser->id, $tid, 'payment.confirmed', 'Payment Confirmed',
            'Your payment of $55.00 for Starter 5-Day Pack was confirmed.', [], now()->subDays(12), now()->subDays(14));

        $this->command->info("  [+]   Dog: Mango (Golden Retriever) — 30 credits  [unlimited pass, currently checked in]");
        $this->command->info("  [+]   Dog: Chai  (Poodle)           — 6 credits   [purchase, transfer_out]");
        $this->command->info("  [+]   Dog: Spice (Shih Tzu)         — 8 credits   [purchase, correction_remove, transfer_in]");

        // ── Staff-only customer (no login) ────────────────────────────────────
        $staffOnly = Customer::create([
            'id'        => (string) Str::ulid(),
            'tenant_id' => $tid,
            'user_id'   => null,
            'name'      => 'Lee Thompson',
            'email'     => null,
            'phone'     => '+18085551099',
        ]);
        $rex = $this->makeDog($tid, $staffOnly->id, 'Rex', 'Rottweiler', '2018-11-30', 'male', 4);
        $piG = $this->stripePaymentIntent($stripeAcct, 5500, 'cus_showcase_staff_only', 'pm_showcase_cash', 'pi_showcase_rex_5day', ['dog' => 'Rex', 'pack' => '5-Day']);
        $orderRex = $this->makeOrder($tid, $staffOnly->id, $pack5->id, '55.00', now()->subDays(7), $piG['pi_id']);
        $this->makeLedger($tid, $rex->id, 'purchase', 5, 5, ['order_id' => $orderRex->id, 'created_at' => now()->subDays(7)]);
        foreach ([5 => 4, 3 => 3] as $dAgo => $bal) {
            $attRex = $this->makeAttendance($tid, $rex->id, $staff1->id, now()->subDays($dAgo)->setHour(8), now()->subDays($dAgo)->setHour(17), false, null, $staff1->id);
            $this->makeLedger($tid, $rex->id, 'deduction', -1, $bal, ['attendance_id' => $attRex->id, 'created_at' => now()->subDays($dAgo)]);
        }

        $this->command->info("  [+] Customer: Lee Thompson (staff-only, no login)");
        $this->command->info("  [+]   Dog: Rex (Rottweiler) — 4 credits");
        $this->command->newLine();
    }

    // ─── Credential + Stripe ID summary ───────────────────────────────────────

    private function printSummary(): void
    {
        $mode = $this->hasRealStripe ? 'LIVE STRIPE (test mode)' : 'FAKE IDs (no Stripe key)';

        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║         PAW SHOWCASE DAYCARE — Credential Summary                ║');
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info("║  Stripe mode: {$mode}");
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  LOGINS  (all passwords: password)                               ║');
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Admin portal:     http://paw-showcase.pawpass.test/admin        ║');
        $this->command->info('║  Customer portal:  http://paw-showcase.pawpass.test/my           ║');
        $this->command->newLine();
        $this->command->info('  Owner:   owner@paw-showcase.test     (business_owner)');
        $this->command->info('  Staff:   morgan@paw-showcase.test    (staff)');
        $this->command->info('  Staff:   riley@paw-showcase.test     (staff)');
        $this->command->newLine();
        $this->command->info('  Customer (Biscuit 9cr, Cookie 0cr refunded):');
        $this->command->info('           sarah@paw-showcase.test');
        $this->command->info('  Customer (Atlas 18cr subscription, Nova 3cr expired):');
        $this->command->info('           marcus@paw-showcase.test');
        $this->command->info('  Customer (Mango unlimited+checked-in, Chai 6cr, Spice 8cr):');
        $this->command->info('           priya@paw-showcase.test');
        $this->command->info('  Staff-only: Lee Thompson (no portal login)');

        $this->command->newLine();
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  STRIPE IDs                                                      ║');
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info("  Connect Account:  " . ($this->stripeIds['connect_account'] ?? 'n/a'));

        $this->command->newLine();
        $this->command->info('  Customers:');
        foreach ($this->stripeIds['customers'] ?? [] as $name => $ids) {
            $this->command->info("    {$name}:  cus={$ids['customer']}  pm={$ids['pm']}  ({$ids['brand']})");
        }

        $this->command->newLine();
        $this->command->info('  Packages (Stripe prices):');
        foreach ($this->stripeIds['packages'] ?? [] as $name => $ids) {
            $this->command->info("    {$name}: product={$ids['product']}  price={$ids['price']}");
        }

        $this->command->newLine();
        $this->command->info('  Subscriptions:');
        foreach ($this->stripeIds['subscriptions'] ?? [] as $label => $subId) {
            $this->command->info("    {$label}: {$subId}");
        }

        $this->command->newLine();
        $this->command->info('  Payment Intents (orders):');
        foreach ($this->stripeIds['orders'] ?? [] as $label => $piId) {
            $this->command->info("    {$label}: {$piId}");
        }

        $this->command->newLine();
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  CREDIT LEDGER TYPES COVERED                                     ║');
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info('  purchase          Biscuit, Cookie, Nova, Mango, Chai, Spice, Rex');
        $this->command->info('  deduction         Biscuit ×3, Atlas ×42, Nova ×7, Rex ×2');
        $this->command->info('  subscription      Atlas (active + cancelled)');
        $this->command->info('  refund            Cookie (full refund)');
        $this->command->info('  goodwill          Biscuit (+2, scheduling compensation)');
        $this->command->info('  correction_add    Nova (+3, expired pack courtesy)');
        $this->command->info('  correction_remove Spice (-1, over-issued at registration)');
        $this->command->info('  expiry_removal    Nova (3 remaining credits expired)');
        $this->command->info('  transfer_out      Chai (-4 to Spice)');
        $this->command->info('  transfer_in       Spice (+4 from Chai)');

        $this->command->newLine();
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  TINKER VERIFICATION                                             ║');
        $this->command->info('╠══════════════════════════════════════════════════════════════════╣');
        $this->command->info('  php artisan tinker');
        $this->command->info('  // All 10 ledger types:');
        $this->command->info("  CreditLedger::distinct()->pluck('type')->sort()->values()");
        $this->command->info('  // Orders with payment records:');
        $this->command->info("  Order::with('payments')->whereHas('customer', fn(\$q)=>\$q->where('name','Sarah Chen'))->get()->map(fn(\$o)=>[\$o->status, \$o->payments->first()?->stripe_pi_id])");
        $this->command->info('  // Dogs for paw-showcase:');
        $this->command->info("  Tenant::where('slug','paw-showcase')->first()->dogs->pluck('credit_balance','name')");
        $this->command->info('╚══════════════════════════════════════════════════════════════════╝');
        $this->command->newLine();
    }
}
