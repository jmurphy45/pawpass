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
        string $transferDestination,
        int $applicationFeeCents,
        array $metadata = []
    ): object {
        return $this->client->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $currency,
            'application_fee_amount' => $applicationFeeCents,
            'transfer_data' => ['destination' => $transferDestination],
            'metadata' => $metadata,
        ]);
    }

    public function createRefund(string $paymentIntentId): object
    {
        return $this->client->refunds->create([
            'payment_intent' => $paymentIntentId,
        ]);
    }

    public function createCustomer(string $email, string $name): object
    {
        return $this->client->customers->create([
            'email' => $email,
            'name' => $name,
        ]);
    }

    public function createSetupIntent(string $stripeCustomerId, array $metadata = []): object
    {
        return $this->client->setupIntents->create([
            'customer' => $stripeCustomerId,
            'metadata' => $metadata,
        ]);
    }

    public function createSubscription(
        string $stripeCustomerId,
        string $stripePriceId,
        string $paymentMethodId,
        string $transferDestination,
        float $applicationFeePercent,
        array $metadata = []
    ): object {
        return $this->client->subscriptions->create([
            'customer' => $stripeCustomerId,
            'items' => [['price' => $stripePriceId]],
            'default_payment_method' => $paymentMethodId,
            'application_fee_percent' => $applicationFeePercent,
            'transfer_data' => ['destination' => $transferDestination],
            'metadata' => $metadata,
        ]);
    }

    public function cancelSubscriptionAtPeriodEnd(string $stripeSubId): object
    {
        return $this->client->subscriptions->update($stripeSubId, [
            'cancel_at_period_end' => true,
        ]);
    }

    public function retrieveSetupIntent(string $setupIntentId): object
    {
        return $this->client->setupIntents->retrieve($setupIntentId);
    }

    public function createProduct(string $name): object
    {
        return $this->client->products->create([
            'name' => $name,
        ]);
    }

    public function createPrice(
        string $productId,
        int $unitAmountCents,
        string $currency,
        ?string $recurringInterval = null
    ): object {
        $payload = [
            'product' => $productId,
            'unit_amount' => $unitAmountCents,
            'currency' => $currency,
        ];

        if ($recurringInterval !== null) {
            $payload['recurring'] = ['interval' => $recurringInterval];
        }

        return $this->client->prices->create($payload);
    }

    public function archivePrice(string $priceId): object
    {
        return $this->client->prices->update($priceId, ['active' => false]);
    }

    public function archiveProduct(string $productId): object
    {
        return $this->client->products->update($productId, ['active' => false]);
    }

    public function createConnectAccount(string $email, string $businessName): object
    {
        return $this->client->accounts->create([
            'type' => 'express',
            'email' => $email,
            'business_profile' => ['name' => $businessName],
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers'     => ['requested' => true],
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

    public function constructWebhookEvent(string $payload, string $sigHeader, string $secret): object
    {
        return Webhook::constructEvent($payload, $sigHeader, $secret);
    }
}
