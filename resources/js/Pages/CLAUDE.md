# Pages — `resources/js/Pages/`

Loaded when editing Inertia page components. See `resources/js/CLAUDE.md` for shared props and global components.

## File → Route Mapping

```
Pages/Admin/{Feature}/Index.vue     → /admin/{feature}
Pages/Admin/{Feature}/Show.vue      → /admin/{feature}/{id}
Pages/Portal/{Feature}.vue          → /my/{feature}
Pages/Registration/Create.vue       → /register
Pages/Home.vue, Leaderboard.vue     → public pages
```

The filename must **exactly match** the string passed to `Inertia::render()` in the controller — Inertia auto-resolves it with no aliasing.

## Props Interface Pattern

Every page component must define a local `PageProps` interface with an index signature:

```ts
interface PageProps {
  dogs: Dog[]
  // ... page-specific props
  [key: string]: unknown  // required by @inertiajs/vue3 generic constraint
}

const props = usePage<PageProps>().props
```

Never use bare `usePage()` without the generic — TypeScript won't narrow the props correctly.

## Layout

- Admin pages: use `AdminLayout` (`<AdminLayout>` wrapper or `defineOptions({ layout: AdminLayout })`)
- Portal pages: use `PortalLayout`
- Public pages: no layout wrapper

## Shared Props (always available)

```ts
usePage().props.auth.user        // AuthUser | null
usePage().props.tenant           // Tenant
usePage().props.tenantPlan       // string (plan slug)
usePage().props.unreadCount      // number (notification bell)
```

## Routing

`route()` is a global Ziggy function — typed in `resources/js/types/vue.d.ts`. Pass named params as objects:
```ts
route('admin.dogs.show', { dog: dog.id })  // correct
route('admin.dogs.show', dog.id)           // wrong
```
