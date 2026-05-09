# Tests — `tests/`

Loaded when editing test files. See root `CLAUDE.md` for commands.

## Test Environment

- **PostgreSQL** (`pawpass_testing` DB) — NOT SQLite. `RefreshDatabase` runs real migrations each test.
- Queue: sync | Mail: array | Cache: array | Session: array
- `phpunit.xml` sets `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, Twilio test creds as placeholders

## JWT in Tests

Use `Tests\Traits\InteractsWithJwt`:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->setUpJwt(); // generates fresh RSA key pair, overrides JwtService singleton
}

$token = $this->jwtFor($user);
$this->withToken($token)->getJson('/api/admin/v1/...');
```

## Tenant Setup Pattern

```php
$this->tenant = Tenant::factory()->create();
app()->instance('current.tenant.id', $this->tenant->id);
URL::forceRootUrl("http://{$this->tenant->slug}.pawpass.test");
```

Use `URL::forceRootUrl()` — **not** `withServerVariables(['HTTP_HOST'])` (Symfony overwrites HTTP_HOST from the URL component, so it doesn't work).

Clear in `tearDown`: `app()->forgetInstance('current.tenant.id')`.

## Factory Cross-Tenant Isolation

`Dog::factory()->create()` auto-assigns to `current.tenant.id` if set. For cross-tenant records always be explicit:

```php
Dog::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $otherCustomer->id]);
// or
Dog::factory()->forCustomer($customerOnOtherTenant)->create();
```

Platform admin user: `User::factory()->platformAdmin()->create()` → `role=platform_admin`, `tenant_id=null`.

## Mocking NotificationService

In setUp for any test that exercises `DogCreditService`:

```php
$this->mock(NotificationService::class)->shouldIgnoreMissing();
```

Prevents `dispatchCreditAlert` from interfering.

## Stripe in Tests

Always mock `StripeService` — never hit real Stripe:

```php
$this->mock(StripeService::class)
    ->shouldReceive('createPaymentIntent')
    ->andReturn((object)['id' => 'pi_test', 'client_secret' => 'secret']);
```

Webhook controller tests: POST raw payload + `Stripe-Signature` header with HMAC of test secret.

## Test Naming & Location

- Method names: `test_snake_case_description`
- `tests/Feature/{Namespace}/` mirrors controller namespace (e.g., `Admin/`, `Portal/`, `Platform/`, `Web/Admin/`)
- `tests/Unit/{Concern}/` for services, models, notifications
- `tests/Traits/` for shared test helpers

## Notification Assertions

Use `Notification::fake()` + `Notification::assertSentTo()` — **not** `Bus::fake()`. `Mail::assertSent(Mailable)` does NOT catch notification emails; use `Notification::fake()` instead.
