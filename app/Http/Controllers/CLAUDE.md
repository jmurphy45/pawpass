# Controllers — `app/Http/Controllers/`

Loaded when editing any controller. See `app/CLAUDE.md` for credit ledger and Stripe patterns.

## Response Envelope

All API responses: `{ "data": ..., "meta": ... }`. Use Laravel resources for `data`; add pagination to `meta` when returning collections.

## Namespaces

| Namespace | Route prefix | Rate limit | Auth |
|---|---|---|---|
| `Portal/V1/` | `/api/portal/v1/*` | 60/min | `auth.jwt` (role=customer) |
| `Admin/V1/` | `/api/admin/v1/*` | 60/min | `auth.jwt` (role=staff or owner) |
| `Platform/V1/` | `/api/platform/v1/*` | 120/min | `auth.jwt` (role=platform_admin) |
| `Public/V1/` | `/api/public/v1/*` | — | none |
| `Web/Admin/` | `/admin/*` | — | `staff.portal.web` |
| `Web/Portal/` | `/my/*` | — | `customer.portal.web` |
| `Webhooks/` | `/webhooks/*` | 600/min | HMAC signature |

## JWT Auth

RS256 tokens — 15-min access + 30-day opaque refresh. Payload: `sub`, `tenant_id`, `role`, `iat`, `exp`.

`AuthenticateJwt` middleware decodes the Bearer token and sets the authenticated user + tenant binding.

## Idempotency-Key Header

Required (UUID or ULID) on mutation endpoints:
- `POST /api/portal/v1/orders`
- `POST /api/admin/v1/dogs/{id}/credits/*`

`RequireIdempotencyKey` returns 400 if missing; caches 200 responses 24 h to replay on duplicate requests.

## SubstituteBindings Order

`bindings` alias is always added **last** in every route group — after `tenant` middleware. Never reorder. Without this, route model binding runs before `current.tenant.id` is set and TenantScope skips the WHERE clause, allowing cross-tenant record access.

## Web Controllers (Inertia)

Return `Inertia::render('Admin/{Feature}/Index', ['prop' => $value])`. The page component path must exactly match the string passed to `render()`. Use `Redirect::route()` after mutations — never return Inertia responses for POST/PATCH/DELETE.
