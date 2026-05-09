# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all services (Laravel server, queue worker, log viewer, Vite)
composer dev

# Run tests
./vendor/bin/sail artisan test --no-coverage 2>&1

# Run a single test file
./vendor/bin/sail artisan test tests/Feature/ExampleTest.php 2>&1

# Run tests matching a filter
./vendor/bin/sail artisan test --filter=SomeTestName 2>&1

# Code style
./vendor/bin/sail pint --dirty

# Migrations
./vendor/bin/sail artisan migrate

# Tinker
./vendor/bin/sail artisan tinker

# Vite build
./vendor/bin/sail npm run build 2>&1

# Initial setup (first time only)
composer setup
```

## Project Overview

PawPass is a **multi-tenant SaaS platform for doggy daycare businesses** — Laravel 12 + Vue 3 + Inertia.js + PostgreSQL. Each tenant (daycare) gets a branded subdomain and processes payments through Stripe Connect Express.

**Stack:** Laravel 12, PostgreSQL, Vue 3, Vite, Tailwind CSS v4, Redis, Stripe Connect Express, Twilio, Resend, S3/R2, Meilisearch.

## Subdomain Routing

```
{slug}.pawpass.com        → TenantMiddleware resolves slug → admin/portal
{slug}.pawpass.com/my/*   → Customer portal (CustomerPortalMiddleware)
{slug}.pawpass.com/api/*  → Admin/portal API (JWT auth)
platform.pawpass.com      → Platform admin + webhooks
```

## Roles

`platform_admin` > `business_owner` > `staff` > `customer`

## Core Domain Concepts

| Concept | Description |
|---|---|
| **Tenant** | One daycare business, identified by a slug |
| **Package** | Product — one-time day packs, subscriptions, or unlimited passes |
| **Credits** | Currency of attendance; one credit deducted per dog per check-in |
| **Credit Ledger** | Append-only PostgreSQL table — source of truth for all credit transactions |
| **Attendance** | Check-in/check-out record per dog per visit |

## Sub-files (loaded automatically by directory)

- **Backend PHP** → `app/CLAUDE.md` (multi-tenancy, credit ledger, Stripe, API, notifications)
- **Frontend Vue/TS** → `resources/js/CLAUDE.md` (components, Inertia, types, composables)
- **Tests** → `tests/CLAUDE.md` (JWT trait, tenant setup, factory patterns, mocking)
- **Migrations/Factories** → `database/CLAUDE.md` (PG conventions, ULID vs bigserial, soft deletes)

## Slash Commands

- `/feature` — plan a new feature (TDD task block, edge case analysis, approval gate)
- `/tdd-cycle` — execute one red-green-refactor iteration
- `/test` — run tests and diagnose failures
- `/migrate` — generate a migration following PawPass conventions
- `/review` — pre-commit checklist (tenancy, ledger, Stripe, tests, migrations)
- `/stripe-debug` — diagnose Stripe Connect / webhook issues

## Standard Workflow

1. **Understand** — read the problem, locate related files, do not modify yet
2. **Plan** — write a checklist in `tasks/todo.md` (each step atomic, with a Verification note; first step is always a failing test)
3. **Pause** — share the plan, wait for approval
4. **TDD** — for each checklist item: write failing test → confirm failure → implement minimal code → confirm pass → refactor if needed → mark done
5. **Minimal** — modify as few files as possible; no unrelated cleanups; no speculative features
6. **Summarize** — after each task: one paragraph on what changed, why, and how verified
7. **Final verification** — run full test suite + `npm run build`; confirm no regressions
8. **Review section** — add a `## Review` block to `tasks/todo.md` summarizing changes, tests, and build status
