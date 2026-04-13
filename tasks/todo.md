# Task: Production Hardening — Webhook Dedup, Job Retries, Idempotency

## Phase 1 — Webhook Deduplication

- [x] Add failing test: sending the same Stripe event_id twice returns 200 both times but only processes once (credits issued once)
- [x] Migration: add `UNIQUE(provider, event_id)` to `raw_webhooks`
- [x] Update `StripeWebhookController::handle()`: use `insertOrIgnore`; if 0 rows inserted (duplicate), return 200 early
- [x] Update `StripeBillingWebhookController::handle()` with same pattern

## Phase 2 — Job Retry Configuration

- [x] Add `$tries = 3` and `$backoff = [60, 300, 900]` to all 14 jobs missing retry config
- [x] Update `ProcessAutoReplenishJob`: bump from `$tries = 1` to `$tries = 3`, add backoff

## Phase 3 — ExpireSubscriptionCredits Idempotency

- [x] Add failing test: job implements `ShouldBeUnique`
- [x] Add failing test: running twice sequentially dispatches `ProcessAutoReplenishJob` only once
- [x] Fix: implement `ShouldBeUnique` on `ExpireSubscriptionCredits`

## Verification

- [x] `./vendor/bin/sail artisan test` — 1221 passed, no regressions

---

## Review

### Summary of Changes

- `database/migrations/2026_04_11_000001_add_unique_event_id_to_raw_webhooks.php` — adds `UNIQUE(provider, event_id)` to `raw_webhooks`
- `StripeWebhookController` / `StripeBillingWebhookController` — replace `Model::create()` with `DB::table()->insertOrIgnore()`; return 200 immediately on duplicate event_id
- 14 jobs — added `$tries = 3` and `$backoff = [60, 300, 900]`: `AlertStaleCheckins`, `CancelStalePendingOrders`, `ExpireSubscriptionCredits`, `ExpireTrials`, `ProcessDunning`, `PruneDispatchedPending`, `PruneOldNotifications`, `PruneRawWebhooks`, `SendTrialExpirationWarnings`, `SendUpgradeNudges`, `SendVaccinationExpiringSoonWarnings`, `SendVaccinationExpiringUrgentWarnings`, `WarmPlatformReportCaches`, `WarmTenantReportCaches`
- `ProcessAutoReplenishJob` — bumped from `$tries = 1` to `$tries = 3`, added `$backoff`
- `ExpireSubscriptionCredits` — added `ShouldBeUnique` to prevent concurrent double-runs

### Tests Added or Updated

- `tests/Feature/Webhooks/StripeWebhookTest.php` — `test_replayed_event_id_returns_200_and_does_not_process_twice`
- `tests/Unit/Jobs/ExpireSubscriptionCreditsTest.php` — `test_job_implements_should_be_unique_to_prevent_concurrent_double_runs`, `test_running_twice_sequentially_dispatches_auto_replenish_only_once`

### Build Status

- Tests: 1221 passed (no regressions)
- Build: Not run (no frontend changes)
