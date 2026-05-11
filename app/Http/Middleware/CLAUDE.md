# Middleware — `app/Http/Middleware/`

Loaded when editing middleware. See `app/Http/Controllers/CLAUDE.md` for route group ordering.

## Middleware Reference

| Alias | Class | Purpose |
|---|---|---|
| `tenant` | `TenantMiddleware` | Subdomain → Tenant lookup → sets `current.tenant.id`; allows statuses: `active`, `trialing`, `free_tier`, `past_due` |
| `auth.jwt` | `AuthenticateJwt` | Bearer token → RS256 decode → sets `Auth::user()` + tenant |
| `role` | `RequireRole` | Checks `$user->role` against allowed roles; returns 403 |
| `plan` | `RequirePlanFeature` | Resolves `PlanGate`; returns 403 `{"error":"PLAN_FEATURE_NOT_AVAILABLE"}` |
| `idempotency` | `RequireIdempotencyKey` | Returns 400 if `Idempotency-Key` header missing; replays cached 200s |
| `customer.portal` | `CustomerPortalMiddleware` | JWT API guard for role=customer; returns 403 JSON |
| `customer.portal.web` | `CustomerPortalWebMiddleware` | Session guard for role=customer; redirects to `portal.login` |
| `staff.portal.web` | `StaffPortalWebMiddleware` | Session guard for role=staff or business_owner; redirects to `admin.login` |
| — | `HandleInertiaRequests` | Shares `auth.user`, `tenant`, `tenantPlan`, `unreadCount`, flash props |

## Guest Redirect Logic

`redirectGuestsTo()` is path-aware:
```php
$request->is('admin*') → route('admin.login')
default               → route('portal.login')
```

## SubstituteBindings

Removed from both `web` and `api` middleware groups in `bootstrap/app.php`. Each route group re-adds the `bindings` alias explicitly as the **last** middleware so tenant scope is active before route model binding fires.
