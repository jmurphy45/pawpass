<?php

namespace App\Services;

use App\Models\Tenant;
use Stripe\StripeClient;

class StripeBillingService
{
    public function __construct(private readonly StripeClient $client) {}

    public function createCustomer(Tenant $tenant): string
    {
        $customer = $this->client->customers->create([
            'name'     => $tenant->name,
            'metadata' => ['tenant_id' => $tenant->id, 'slug' => $tenant->slug],
        ]);

        return $customer->id;
    }

    public function createSetupIntent(string $customerId): object
    {
        return $this->client->setupIntents->create([
            'customer' => $customerId,
            'usage'    => 'off_session',
        ]);
    }

    public function attachPaymentMethod(string $customerId, string $paymentMethodId): void
    {
        $this->client->paymentMethods->attach($paymentMethodId, ['customer' => $customerId]);

        $this->client->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
        ]);
    }

    public function createSubscription(Tenant $tenant, string $priceId, string $cycle, ?string $paymentMethodId = null): object
    {
        $params = [
            'customer'        => $tenant->platform_stripe_customer_id,
            'items'           => [['price' => $priceId]],
            'metadata'        => ['tenant_id' => $tenant->id, 'cycle' => $cycle],
            'trial_from_plan' => false,
        ];

        if ($paymentMethodId !== null) {
            $params['default_payment_method'] = $paymentMethodId;
        }

        return $this->client->subscriptions->create($params);
    }

    public function changePlan(Tenant $tenant, string $newPriceId): object
    {
        $sub = $this->client->subscriptions->retrieve($tenant->platform_stripe_sub_id);

        return $this->client->subscriptions->update($tenant->platform_stripe_sub_id, [
            'items' => [[
                'id'    => $sub->items->data[0]->id,
                'price' => $newPriceId,
            ]],
            'proration_behavior' => 'create_prorations',
        ]);
    }

    public function cancelSubscription(Tenant $tenant): object
    {
        return $this->client->subscriptions->update($tenant->platform_stripe_sub_id, [
            'cancel_at_period_end' => true,
        ]);
    }

    public function listInvoices(Tenant $tenant): array
    {
        $invoices = $this->client->invoices->all([
            'customer' => $tenant->platform_stripe_customer_id,
            'limit'    => 24,
        ]);

        return $invoices->data;
    }

    public function createPortalSession(Tenant $tenant, string $returnUrl): string
    {
        $session = $this->client->billingPortal->sessions->create([
            'customer'   => $tenant->platform_stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    public function createTrialSubscription(
        Tenant $tenant,
        string $priceId,
        string $cycle,
        int $trialDays
    ): object {
        return $this->client->subscriptions->create([
            'customer'          => $tenant->platform_stripe_customer_id,
            'items'             => [['price' => $priceId]],
            'metadata'          => ['tenant_id' => $tenant->id, 'cycle' => $cycle],
            'trial_period_days' => $trialDays,
        ]);
    }

    public function createPlatformProduct(string $name): string
    {
        $product = $this->client->products->create(['name' => $name]);

        return $product->id;
    }

    public function createPlatformPrice(string $productId, int $unitAmountCents, string $interval): string
    {
        $price = $this->client->prices->create([
            'product'     => $productId,
            'unit_amount' => $unitAmountCents,
            'currency'    => 'usd',
            'recurring'   => ['interval' => $interval],
        ]);

        return $price->id;
    }

    public function updatePlatformProduct(string $productId, string $name): void
    {
        $this->client->products->update($productId, ['name' => $name]);
    }

    public function archivePlatformPrice(string $priceId): void
    {
        $this->client->prices->update($priceId, ['active' => false]);
    }

    public function archivePlatformProduct(string $productId): void
    {
        $this->client->products->update($productId, ['active' => false]);
    }

    public function getDefaultPaymentMethod(string $customerId): ?object
    {
        $customer = $this->client->customers->retrieve($customerId, [
            'expand' => ['invoice_settings.default_payment_method'],
        ]);

        return $customer->invoice_settings->default_payment_method ?: null;
    }

    public function constructWebhookEvent(string $payload, string $sigHeader, string $secret): object
    {
        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
    }

    public function createInvoiceItem(string $customerId, int $amountCents, string $description): object
    {
        return (object) $this->client->invoiceItems->create([
            'customer'    => $customerId,
            'amount'      => $amountCents,
            'currency'    => 'usd',
            'description' => $description,
        ]);
    }

    public function createAndFinalizeInvoice(string $customerId): object
    {
        $invoice = $this->client->invoices->create([
            'customer'     => $customerId,
            'auto_advance' => false,
        ]);

        return (object) $this->client->invoices->finalizeInvoice($invoice->id);
    }
}
