# Frontend — `resources/js/`

Loaded when editing Vue/TypeScript files. See root `CLAUDE.md` for project overview.

## Stack

Vue 3 + Inertia.js (`@inertiajs/vue3`) + TypeScript + Tailwind CSS v4.

- Tailwind config is **in CSS** (`@import "tailwindcss"`) — there is no `tailwind.config.js`
- `@` alias resolves to `resources/js`
- Entry point: `resources/js/app.ts`

## Layouts

- `AdminLayout.vue` — staff/owner dashboard (`/admin/*`)
- `PortalLayout.vue` — customer portal (`/my/*`)

## Globally Registered Components (no import needed)

`AppAlert`, `AppBadge`, `AppButton`, `AppCard`, `AppDropdown`, `AppEmptyState`, `AppInput`, `AppModal`, `AppPageHeader`, `AppSelect`, `AppStatCard`

## Inertia Shared Props

Always available via `usePage().props`:

```ts
auth.user    // AuthUser | null
tenant       // Tenant
tenantPlan   // string (plan slug)
vapidPublicKey // string
```

## TypeScript

Core interfaces in `resources/js/types/index.d.ts`: `Tenant`, `AuthUser`, `Dog`, `Package`, `Order`, `Attendance`.

`route()` is a **global function** (Ziggy) — typed in `resources/js/types/vue.d.ts`. Pass named params as `{ dog: id }` not bare strings.

`PageProps` must include `[key: string]: unknown` to satisfy `@inertiajs/vue3`'s generic constraint.

## Composables

- `useFeatures.ts` — plan feature gate checks
- `usePushNotifications.ts` — push subscription register/unsubscribe

## Page Conventions

See `resources/js/Pages/CLAUDE.md` for naming conventions, `PageProps` interface pattern, and routing.
