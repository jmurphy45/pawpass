<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    public function __construct(private readonly StripeClient $client) {}

    public function createPaymentIntent(
        int $amountCents,
        string $currency,
        string $stripeAccountId,
        int $applicationFeeCents,
        array $metadata = [],
        ?string $stripeCustomerId = null,
        bool $confirm = false,
        bool $offSession = false,
        ?string $paymentMethodId = null,
        array $paymentMethodTypes = [],
        ?string $setupFutureUsage = null,
        bool $automaticTax = false,
    ): object {
        $payload = [
            'amount' => $amountCents,
            'currency' => $currency,
            'application_fee_amount' => $applicationFeeCents,
            'metadata' => $metadata,
        ];
        if ($stripeCustomerId) {
            $payload['customer'] = $stripeCustomerId;
        }
        if ($paymentMethodTypes) {
            $payload['payment_method_types'] = $paymentMethodTypes;
        }
        if ($confirm) {
            $payload['confirm'] = true;
            if ($paymentMethodTypes) {
                $payload['error_on_requires_action'] = true;
            }
        }
        if ($offSession) {
            $payload['off_session'] = true;
        }
        if ($paymentMethodId) {
            $payload['payment_method'] = $paymentMethodId;
        }
        if ($setupFutureUsage) {
            $payload['setup_future_usage'] = $setupFutureUsage;
        }
        if ($automaticTax) {
            $payload['automatic_tax'] = ['enabled' => true];
        }
        return $this->client->paymentIntents->create($payload, ['stripe_account' => $stripeAccountId]);
    }

    public function createHoldPaymentIntent(
        int $amountCents,
        string $currency,
        string $stripeAccountId,
        int $applicationFeeCents,
        array $metadata = [],
    ): object {
        return $this->client->paymentIntents->create([
            'amount'                 => $amountCents,
            'currency'               => $currency,
            'application_fee_amount' => $applicationFeeCents,
            'capture_method'         => 'manual',
            'metadata'               => $metadata,
        ], ['stripe_account' => $stripeAccountId]);
    }

    public function capturePaymentIntent(string $piId, string $stripeAccountId): object
    {
        return $this->client->paymentIntents->capture($piId, [], ['stripe_account' => $stripeAccountId]);
    }

    public function cancelPaymentIntent(string $piId, string $stripeAccountId): object
    {
        return $this->client->paymentIntents->cancel($piId, [], ['stripe_account' => $stripeAccountId]);
    }

    public function attachPaymentMethod(string $pmId, string $stripeCustomerId, string $stripeAccountId): object
    {
        return $this->client->paymentMethods->attach(
            $pmId,
            ['customer' => $stripeCustomerId],
            ['stripe_account' => $stripeAccountId],
        );
    }

    public function createRefund(string $paymentIntentId, ?string $stripeAccountId = null): object
    {
        $opts = $stripeAccountId ? ['stripe_account' => $stripeAccountId] : [];
        return $this->client->refunds->create(['payment_intent' => $paymentIntentId], $opts);
    }

    public function createCustomer(string $email, string $name, ?string $stripeAccountId = null): object
    {
        $params = ['email' => $email, 'name' => $name];
        if ($stripeAccountId) {
            return $this->client->customers->create($params, ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->customers->create($params);
    }

    public function createSetupIntent(string $stripeCustomerId, array $metadata = [], ?string $stripeAccountId = null): object
    {
        $payload = ['customer' => $stripeCustomerId, 'metadata' => $metadata];
        if ($stripeAccountId) {
            return $this->client->setupIntents->create($payload, ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->setupIntents->create($payload);
    }

    public function createSubscription(
        string $stripeCustomerId,
        string $stripePriceId,
        string $paymentMethodId,
        string $stripeAccountId,
        float $applicationFeePercent,
        array $metadata = []
    ): object {
        return $this->client->subscriptions->create([
            'customer' => $stripeCustomerId,
            'items' => [['price' => $stripePriceId]],
            'default_payment_method' => $paymentMethodId,
            'application_fee_percent' => $applicationFeePercent,
            'metadata' => $metadata,
        ], ['stripe_account' => $stripeAccountId]);
    }

    public function cancelSubscriptionAtPeriodEnd(string $stripeSubId, string $stripeAccountId): object
    {
        return $this->client->subscriptions->update($stripeSubId, [
            'cancel_at_period_end' => true,
        ], ['stripe_account' => $stripeAccountId]);
    }

    public function retrievePaymentIntent(string $id, ?string $stripeAccountId = null): object
    {
        $opts = $stripeAccountId ? ['stripe_account' => $stripeAccountId] : [];
        return $this->client->paymentIntents->retrieve($id, [], $opts);
    }

    public function retrieveChargeDetails(string $paymentIntentId, string $stripeAccountId): ?array
    {
        $pi = $this->client->paymentIntents->retrieve(
            $paymentIntentId,
            ['expand' => ['latest_charge']],
            ['stripe_account' => $stripeAccountId]
        );

        $charge = $pi->latest_charge ?? null;
        if (!$charge) {
            return null;
        }

        $card = $charge->payment_method_details?->card ?? null;

        return [
            'charge_id'      => $charge->id,
            'receipt_number' => $charge->receipt_number ?? null,
            'card_brand'     => $card?->brand ?? null,
            'card_last4'     => $card?->last4 ?? null,
        ];
    }

    public function retrievePaymentMethod(string $pmId, ?string $stripeAccountId = null): object
    {
        $opts = $stripeAccountId ? ['stripe_account' => $stripeAccountId] : [];
        return $this->client->paymentMethods->retrieve($pmId, [], $opts);
    }

    public function retrieveSetupIntent(string $setupIntentId, ?string $stripeAccountId = null): object
    {
        $opts = $stripeAccountId ? ['stripe_account' => $stripeAccountId] : [];
        return $this->client->setupIntents->retrieve($setupIntentId, [], $opts);
    }

    public function createProduct(string $name, ?string $stripeAccountId = null): object
    {
        if ($stripeAccountId) {
            return $this->client->products->create(['name' => $name], ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->products->create(['name' => $name]);
    }

    public function createPrice(
        string $productId,
        int $unitAmountCents,
        string $currency,
        ?string $recurringInterval = null,
        ?string $stripeAccountId = null,
        int $intervalCount = 1,
    ): object {
        $payload = [
            'product' => $productId,
            'unit_amount' => $unitAmountCents,
            'currency' => $currency,
        ];

        if ($recurringInterval !== null) {
            $payload['recurring'] = [
                'interval'       => $recurringInterval,
                'interval_count' => $intervalCount,
            ];
        }

        if ($stripeAccountId) {
            return $this->client->prices->create($payload, ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->prices->create($payload);
    }

    public function archivePrice(string $priceId, ?string $stripeAccountId = null): object
    {
        if ($stripeAccountId) {
            return $this->client->prices->update($priceId, ['active' => false], ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->prices->update($priceId, ['active' => false]);
    }

    public function archiveProduct(string $productId, ?string $stripeAccountId = null): object
    {
        if ($stripeAccountId) {
            return $this->client->products->update($productId, ['active' => false], ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->products->update($productId, ['active' => false]);
    }

    public function createPaymentMethodFromToken(string $token, string $stripeAccountId): object
    {
        return $this->client->paymentMethods->create(
            ['type' => 'card', 'card' => ['token' => $token]],
            ['stripe_account' => $stripeAccountId],
        );
    }

    public function createConnectAccount(string $email, string $businessName): object
    {
        return $this->client->accounts->create([
            'type' => 'express',
            'email' => $email,
            'business_profile' => ['name' => $businessName],
            'capabilities' => [
                'card_payments'                => ['requested' => true],
                'transfers'                    => ['requested' => true],
                'us_bank_account_ach_payments' => ['requested' => true],
            ],
        ]);
    }

    public function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): object
    {
        return $this->client->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }

    public function createAccountSession(string $accountId, array $components): object
    {
        return $this->client->accountSessions->create([
            'account' => $accountId,
            'components' => $components,
        ]);
    }

    public function calculateTax(
        int $subtotalCents,
        string $currency,
        string $stripeAccountId,
        array $customerAddress,
        string $reference = 'order',
    ): object {
        return $this->client->tax->calculations->create([
            'currency'         => $currency,
            'line_items'       => [[
                'amount'    => $subtotalCents,
                'reference' => $reference,
            ]],
            'customer_details' => [
                'address'        => $customerAddress,
                'address_source' => 'billing',
            ],
        ], ['stripe_account' => $stripeAccountId]);
    }

    public function createTaxTransaction(
        string $taxCalculationId,
        string $reference,
        string $stripeAccountId,
    ): object {
        return $this->client->tax->transactions->createFromCalculation([
            'calculation' => $taxCalculationId,
            'reference'   => $reference,
        ], ['stripe_account' => $stripeAccountId]);
    }

    public function constructWebhookEvent(string $payload, string $sigHeader, string $secret): object
    {
        return Webhook::constructEvent($payload, $sigHeader, $secret);
    }
}
