<template>
  <div class="min-h-screen bg-surface flex overflow-x-clip">
    <!-- Sidebar (desktop) -->
    <aside
      class="hidden md:flex md:flex-col w-60 fixed inset-y-0 z-30"
      style="background-color: #0f0e0d; border-right: 1px solid rgba(255,255,255,0.06); box-shadow: 4px 0 24px rgba(0,0,0,0.15);"
    >
      <!-- Logo area -->
      <div class="px-4 pt-5 pb-3">
        <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5 mb-3">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="14" cy="14" r="14" fill="#4f46e5"/>
            <ellipse cx="10" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
            <ellipse cx="18" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
            <ellipse cx="7" cy="15" rx="2.2" ry="3" transform="rotate(-20 7 15)" fill="white" opacity="0.9"/>
            <ellipse cx="21" cy="15" rx="2.2" ry="3" transform="rotate(20 21 15)" fill="white" opacity="0.9"/>
            <ellipse cx="14" cy="19" rx="5" ry="4" fill="white"/>
          </svg>
          <span class="text-white font-bold text-lg tracking-tight">{{ tenant?.name ?? 'PawPass' }}</span>
        </Link>

        <!-- Plan badge -->
        <div
          v-if="tenantPlan === 'trialing' || tenantPlan === 'free_tier'"
          class="inline-flex items-center self-start px-2 py-0.5 rounded-full text-xs font-medium"
          style="background-color: rgba(245,158,11,0.12); color: #fcd34d;"
        >
          {{ tenantPlan === 'trialing' ? 'Trial' : 'Free' }}
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-2 pb-4 space-y-0.5">
        <!-- Dashboard -->
        <Link
          :href="route('admin.dashboard')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.dashboard') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.dashboard') ? { backgroundColor: accentColor } : {}"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
          </svg>
          Dashboard
        </Link>

        <!-- Operations -->
        <div class="section-heading">Operations</div>

        <Link
          :href="route('admin.roster.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.roster.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.roster.*') ? { backgroundColor: accentColor } : {}"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
          </svg>
          Roster
        </Link>

        <Link
          :href="route('admin.customers.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.customers.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.customers.*') ? { backgroundColor: accentColor } : {}"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
          </svg>
          Customers
        </Link>

        <Link
          :href="route('admin.dogs.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.dogs.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.dogs.*') ? { backgroundColor: accentColor } : {}"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
          </svg>
          Dogs
        </Link>

        <!-- Business -->
        <div class="section-heading">Business</div>

        <Link
          :href="route('admin.payments.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.payments.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.payments.*') ? { backgroundColor: accentColor } : {}"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
          </svg>
          Payments
        </Link>

        <Link
          v-if="hasReports"
          :href="route('admin.reports.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.reports.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.reports.*') ? { backgroundColor: accentColor } : {}"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
          </svg>
          Reports
        </Link>

        <!-- Communications -->
        <div class="section-heading">Communications</div>

        <Link
          :href="route('admin.notifications.broadcast')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.notifications.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.notifications.*') ? { backgroundColor: accentColor } : {}"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m-1.394 5.52a23.926 23.926 0 0 1-3.25 2.88" />
          </svg>
          Broadcast
        </Link>

        <!-- Owner only -->
        <template v-if="isOwner">
          <div class="section-heading">Owner</div>

          <Link
            :href="route('admin.packages.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.packages.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.packages.*') ? { backgroundColor: accentColor } : {}"
          >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
            </svg>
            Packages
          </Link>

          <Link
            :href="route('admin.settings.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.settings.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.settings.*') ? { backgroundColor: accentColor } : {}"
          >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            Settings
          </Link>

          <Link
            :href="route('admin.billing.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.billing.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.billing.*') ? { backgroundColor: accentColor } : {}"
          >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Billing
          </Link>
        </template>
      </nav>

      <!-- User footer -->
      <div class="px-3 py-4" style="border-top: 1px solid rgba(255,255,255,0.06);">
        <div class="flex items-center gap-3 mb-2">
          <div
            class="h-8 w-8 rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0"
            :style="{ backgroundColor: accentColor }"
          >{{ userInitial }}</div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-xs truncate" style="color: rgba(255,255,255,0.4);">{{ auth.user?.role?.replace('_', ' ') }}</p>
          </div>
        </div>
        <form @submit.prevent="logout">
          <button
            type="submit"
            class="w-full text-left text-xs px-2 py-1 rounded transition-colors text-white/40 hover:text-red-400"
          >
            Sign out
          </button>
        </form>
      </div>
    </aside>

    <!-- Main content -->
    <div class="md:ml-60 flex-1 min-w-0 flex flex-col min-h-screen overflow-x-clip">
      <!-- Top bar (mobile) -->
      <header
        class="md:hidden fixed top-0 left-0 right-0 z-20 flex items-center justify-between h-14 px-4"
        style="background-color: #0f0e0d;"
      >
        <Link :href="route('admin.dashboard')" class="text-white text-lg font-bold">
          {{ tenant?.name ?? 'PawPass' }}
        </Link>
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="p-2 text-white/60">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
      </header>

      <!-- Mobile menu -->
      <div
        v-if="mobileMenuOpen"
        class="md:hidden fixed top-14 left-0 right-0 z-20 px-4 py-3 space-y-1 overflow-y-auto max-h-[calc(100vh-3.5rem)]"
        style="background-color: #0f0e0d; border-bottom: 1px solid rgba(255,255,255,0.06);"
      >
        <Link
          v-for="item in flatNavItems"
          :key="item.name"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="isActive(item.pattern) ? 'text-white' : 'text-white/65'"
          :style="isActive(item.pattern) ? { backgroundColor: accentColor } : {}"
          @click="mobileMenuOpen = false"
        >
          {{ item.name }}
        </Link>
        <form @submit.prevent="logout" class="pt-2" style="border-top: 1px solid rgba(255,255,255,0.06);">
          <button type="submit" class="text-sm px-3 py-2 text-white/40">Sign out</button>
        </form>
      </div>

      <!-- Spacer for fixed mobile header -->
      <div class="md:hidden h-14" />

      <!-- Flash messages -->
      <div v-if="flash.success || flash.error" class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-4">
        <div
          v-if="flash.success"
          class="flex items-center gap-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm"
        >
          <svg class="h-5 w-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
          </svg>
          {{ flash.success }}
        </div>
        <div
          v-if="flash.error"
          class="flex items-center gap-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm"
        >
          <svg class="h-5 w-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
          </svg>
          {{ flash.error }}
        </div>
      </div>

      <!-- Page content -->
      <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6">
        <slot />
      </main>
      <!-- Mobile bottom padding -->
      <div class="md:hidden h-16" />
    </div>

    <!-- Mobile bottom nav -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-border-warm flex z-30">
      <Link
        :href="route('admin.dashboard')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('admin.dashboard') ? 'text-indigo-600' : 'text-gray-400'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
        <span>Home</span>
      </Link>
      <Link
        :href="route('admin.roster.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('admin.roster.*') ? 'text-indigo-600' : 'text-gray-400'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
        <span>Roster</span>
      </Link>
      <Link
        :href="route('admin.customers.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('admin.customers.*') ? 'text-indigo-600' : 'text-gray-400'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
        </svg>
        <span>Customers</span>
      </Link>
      <Link
        :href="route('admin.dogs.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('admin.dogs.*') ? 'text-indigo-600' : 'text-gray-400'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
        </svg>
        <span>Dogs</span>
      </Link>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const auth = computed(() => page.props.auth);
const flash = computed(() => page.props.flash ?? { success: null, error: null });
const accentColor = computed(() => tenant.value?.primary_color ?? '#4f46e5');
const userInitial = computed(() => auth.value.user?.name?.[0]?.toUpperCase() ?? '?');

const mobileMenuOpen = ref(false);

const isOwner = computed(() => auth.value.user?.role === 'business_owner');
const tenantPlan = computed(() => page.props.tenantPlan);
const hasReports = computed(() => {
  const plan = tenantPlan.value;
  return plan === 'starter' || plan === 'pro' || plan === 'business';
});

function isActive(pattern: string): boolean {
  try { return !!(route as any)().current(pattern); } catch { return false; }
}

const flatNavItems = computed(() => {
  const items: Array<{ name: string; href: string; pattern: string }> = [
    { name: 'Dashboard', href: route('admin.dashboard'), pattern: 'admin.dashboard' },
    { name: 'Roster', href: route('admin.roster.index'), pattern: 'admin.roster.*' },
    { name: 'Customers', href: route('admin.customers.index'), pattern: 'admin.customers.*' },
    { name: 'Dogs', href: route('admin.dogs.index'), pattern: 'admin.dogs.*' },
    { name: 'Payments', href: route('admin.payments.index'), pattern: 'admin.payments.*' },
  ];

  if (hasReports.value) {
    items.push({ name: 'Reports', href: route('admin.reports.index'), pattern: 'admin.reports.*' });
  }

  items.push({ name: 'Broadcast', href: route('admin.notifications.broadcast'), pattern: 'admin.notifications.*' });

  if (isOwner.value) {
    items.push(
      { name: 'Packages', href: route('admin.packages.index'), pattern: 'admin.packages.*' },
      { name: 'Settings', href: route('admin.settings.index'), pattern: 'admin.settings.*' },
      { name: 'Billing', href: route('admin.billing.index'), pattern: 'admin.billing.*' },
    );
  }

  return items;
});

const logoutForm = useForm({});
function logout() {
  logoutForm.post(route('admin.logout'));
}
</script>
