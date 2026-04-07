# Pre-Production Review — Issues to Fix

Audit completed 2026-04-07. Several agent findings were false positives:
- `KennelUnit`, `Customer`, `Dog` all use `BelongsToTenant` → `TenantScope` auto-filters every query. No explicit tenant checks needed.

---

## CRITICAL — Fix before any live traffic

### 1. Purchase confirmation ignores backend errors
**File:** `resources/js/Pages/Portal/Purchase.vue` lines 350–362

After `stripe.confirmCardPayment()` succeeds, a `fetch()` POST hits the backend to confirm and issue credits. The response is **never checked**. If the backend returns any error, `success.value = true` is still set and the user is redirected — but credits are never issued. Real money bug.

**Fix:** Check `resp.ok` before setting success:
```js
const resp = await fetch(route('portal.purchase.confirm'), { ... });
if (!resp.ok) {
  const body = await resp.json().catch(() => ({}));
  cardError.value = body.message ?? 'Payment confirmed but we could not credit your account. Please contact support.';
  return;
}
success.value = true;
```

- [ ] Add response check to confirmation fetch
  - Verification: Simulate a 500 from the confirm endpoint; user should see error, not success

---

### 2. Subscription marked 'active' before SetupIntent is confirmed
**File:** `app/Http/Controllers/Portal/V1/SubscriptionController.php` lines 67–80

Subscription is created with `status: 'active'` before the customer completes the Stripe SetupIntent flow. If they abandon the form, the DB shows an active subscription with no Stripe backing.

Additionally, there is **no `setup_intent.succeeded` webhook handler** (confirmed: nothing in `app/Http/Controllers/Webhooks/` handles it).

**Fix:**
1. Change `'status' => 'active'` → `'status' => 'pending'` at line 72
2. Add `setup_intent.succeeded` case in `StripeWebhookController` that reads `metadata.local_subscription_id`, finds the subscription, and transitions it to `active`

- [ ] Change initial subscription status to `pending`
  - Verification: Creating a subscription returns 201 but DB shows `pending`
- [ ] Add `setup_intent.succeeded` webhook case
  - Verification: Simulate webhook event; subscription transitions to `active`

---

### 3. BillingController — all Stripe calls crash on API errors
**File:** `app/Http/Controllers/Admin/V1/BillingController.php`

Every method (`subscribe`, `upgrade`, `cancel`, `invoices`, `portalUrl`) calls Stripe without try-catch. A Stripe API error throws unhandled → 500.

**Fix:** Wrap each call in:
```php
try {
    $result = $this->billing->someStripeCall(...);
} catch (\Stripe\Exception\ApiErrorException $e) {
    return response()->json(['message' => $e->getMessage()], 502);
}
```

- [ ] Add try-catch to `subscribe()`
- [ ] Add try-catch to `upgrade()`
- [ ] Add try-catch to `cancel()`
- [ ] Add try-catch to `invoices()`
- [ ] Add try-catch to `portalUrl()`
  - Verification: Mock Stripe to throw; each endpoint returns 502 with message

---

## HIGH — Fix before launch

### 4. XSS via `v-html` in boarding reservations pagination
**File:** `resources/js/Pages/Admin/Boarding/Reservations.vue` line 95

```vue
<a ... v-html="link.label" ... />
```

Laravel paginator labels are numbers and `&laquo;`/`&raquo;` — low actual risk since backend controls output — but `v-html` is never the right tool here.

**Fix:** Replace with text rendering:
```vue
{{ link.label.replace('&laquo;', '«').replace('&raquo;', '»') }}
```

- [ ] Replace `v-html` with text rendering on pagination labels
  - Verification: Pagination renders correctly, no raw HTML in DOM

---

### 5. Reservation + deposit payment not atomic
**File:** `app/Http/Controllers/Portal/V1/ReservationController.php` lines 90–145

`Reservation` is written first, then `createHoldPaymentIntent()` is called. If Stripe fails, an orphaned reservation exists in the DB with no payment order.

**Fix:** Wrap in `DB::transaction()` and throw on Stripe failure to roll back:
```php
$reservation = DB::transaction(function () use (...) {
    $res = Reservation::create([...]);
    try {
        $pi = $this->stripe->createHoldPaymentIntent(...);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        throw new \RuntimeException('Stripe error: ' . $e->getMessage());
    }
    Order::create([...]);
    return $res;
});
```

- [ ] Wrap reservation + payment creation in a DB transaction
  - Verification: Simulate Stripe failure; no orphaned reservation in DB

---

### 6. Boarding dates not validated (checkout must be after checkin)
**Files:**
- `resources/js/Pages/Portal/Boarding/Create.vue`
- Server-side store validation in `ReservationController`

No check that `ends_at > starts_at` on client or server.

**Fix (server):** Add to store validation rules:
```php
'ends_at' => ['required', 'date', 'after:starts_at'],
```

**Fix (client):** In `onDatesChange()`:
```js
if (form.starts_at && form.ends_at && form.ends_at <= form.starts_at) {
  form.setError('ends_at', 'Check-out must be after check-in');
}
```

- [ ] Add `after:starts_at` to server-side validation
  - Verification: POST with `ends_at = starts_at` returns 422
- [ ] Add client-side date order check
  - Verification: Selecting same-day dates shows error inline

---

## MEDIUM — Fix soon after launch

### 7. Occupancy.vue: null-safety in occupancyMap computed
**File:** `resources/js/Pages/Admin/Boarding/Occupancy.vue` lines 481–482

```ts
let cur = parseISO(res.starts_at.slice(0, 10));  // throws if null
const end = parseISO(res.ends_at.slice(0, 10));   // same
```

DB has NOT NULL so this won't fire in normal operation, but TypeScript can't know that.

**Fix:** Add a guard:
```ts
if (!res.starts_at || !res.ends_at) continue;
```

- [ ] Add null guard on reservation dates in occupancyMap computed
  - Verification: No TypeScript error; component renders with empty reservations

---

### 8. Job retry logic missing for critical jobs
**Files:**
- `app/Jobs/ProcessAutoReplenishJob.php` — `$tries = 1`
- `app/Jobs/SendBroadcastNotificationJob.php` — `$tries = 1`

Payment and notification jobs that fail once are permanently lost with no retry.

**Fix:** Increase retries and add back-off:
```php
public int $tries = 5;

public function backoff(): array
{
    return [60, 300, 900, 3600];
}

public function failed(\Throwable $e): void
{
    Log::error('Job permanently failed', ['job' => static::class, 'error' => $e->getMessage()]);
}
```

- [ ] Update `ProcessAutoReplenishJob` retry config
- [ ] Update `SendBroadcastNotificationJob` retry config
  - Verification: Job can be dispatched and retried in tests

---

### 9. N+1: Tenant loaded 3× in BoardingController.processCheckout()
**File:** `app/Http/Controllers/Web/Admin/BoardingController.php` lines ~126, ~217, ~306

`Tenant::find($reservation->tenant_id)` called three times in a single request.

**Fix:** Load once at the top of the method and reuse the variable.

- [ ] Deduplicate Tenant queries in processCheckout
  - Verification: Same behavior, fewer DB queries (add a query count assertion or just verify manually)

---

## Deployment Checklist (not code changes)

- [ ] Set `APP_DEBUG=false` and `APP_ENV=production` in production environment
- [ ] Replace test Stripe keys with production keys via secrets manager
- [ ] Set real `whsec_` webhook secrets in Stripe dashboard → production env
- [ ] Update `.env.example` to include all required vars:
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_BILLING_WEBHOOK_SECRET`
  - `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM`, `TWILIO_FAKE`
  - `SCOUT_DRIVER`, `MEILISEARCH_HOST`, `MEILISEARCH_NO_ANALYTICS`

---

## Review

### Files to Modify
| Priority | File |
|---|---|
| CRITICAL | `resources/js/Pages/Portal/Purchase.vue` |
| CRITICAL | `app/Http/Controllers/Portal/V1/SubscriptionController.php` |
| CRITICAL | `app/Http/Controllers/Webhooks/StripeWebhookController.php` |
| CRITICAL | `app/Http/Controllers/Admin/V1/BillingController.php` |
| HIGH | `resources/js/Pages/Admin/Boarding/Reservations.vue` |
| HIGH | `app/Http/Controllers/Portal/V1/ReservationController.php` |
| HIGH | `resources/js/Pages/Portal/Boarding/Create.vue` |
| MEDIUM | `resources/js/Pages/Admin/Boarding/Occupancy.vue` |
| MEDIUM | `app/Jobs/ProcessAutoReplenishJob.php` |
| MEDIUM | `app/Jobs/SendBroadcastNotificationJob.php` |
| MEDIUM | `app/Http/Controllers/Web/Admin/BoardingController.php` |

### Verification (final)
1. `./vendor/bin/sail artisan test` — all tests pass
2. `npm run build` — no TypeScript/build errors
3. End-to-end: complete a purchase (one-time + subscription) and verify credits appear
4. Simulate `setup_intent.succeeded` webhook → subscription moves to `active`
5. Trigger Stripe error in billing controller → 502 returned, not 500
6. Submit boarding reservation with checkout ≤ checkin → 422 returned
