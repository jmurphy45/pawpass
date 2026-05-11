# Services — `app/Services/`

Loaded when editing service classes. See `app/CLAUDE.md` for credit ledger operations and Stripe architecture.

## Service Container Bindings (AppServiceProvider)

**Singletons** (one instance per request lifecycle):
- `JwtService` — RS256 JWT issue/decode/refresh
- `StripeService` — Stripe Connect Express (uses `STRIPE_SECRET`)
- `TwilioService` — SMS dispatch
- `NotificationService` — orchestrates channels, queues `SendNotificationJob`

**Bound (not singleton)**:
- `PlanGate` — re-resolved per request so it picks up the current tenant's plan fresh

## Two Stripe Contexts — Never Mix

| Service | Env var | Purpose |
|---|---|---|
| `StripeService` | `STRIPE_SECRET` | Connected account charges, customers, subscriptions |
| `StripeBillingService` | `STRIPE_BILLING_SECRET` | Platform billing (tenant subscriptions to PawPass) |

## Notification Patterns

- `customer.user_id` can be null — always null-guard before calling `NotificationService`
- `credits.low` / `credits.empty`: 60-second grouping window consolidates multi-dog alerts per customer
- `dogs.credits_alert_sent_at`: 24-hour dedup per dog
- Critical types (`payment.confirmed`, `credits.empty`, auth events) bypass tenant notification settings

## Scheduled Jobs

| Job | Schedule |
|---|---|
| `WarmTenantReportCaches` | 2 AM per-tenant timezone |
| `WarmPlatformReportCaches` | 3 AM UTC |
| `ExpireSubscriptionCredits` | 1 AM UTC |
| `PruneOldNotifications` | 4 AM UTC |
| `PruneRawWebhooks` | 4 AM UTC (7-day retention) |
| `PruneDispatchedPending` | 4 AM UTC |

Scheduler is registered via `withSchedule()` in `bootstrap/app.php` (before `withExceptions()`).

## Queue Channels

- `notifications` queue — `SendNotificationJob`
- Do NOT redeclare `public string $queue` in job classes; use `$this->onQueue('notifications')` in the constructor (`Queueable` trait already declares `$queue`).
