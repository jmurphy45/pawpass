<template>
  <div class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar (desktop) -->
    <aside class="hidden md:flex md:flex-col w-64 bg-white border-r border-gray-200 fixed inset-y-0 z-30">
      <div class="flex items-center h-16 px-6 border-b border-gray-200">
        <Link :href="route('admin.dashboard')" class="flex items-center gap-2">
          <img v-if="tenant?.logo_url" :src="tenant.logo_url" :alt="tenant.name" class="h-8 w-auto" />
          <span v-else class="text-lg font-bold tracking-tight" :style="{ color: accentColor }">
            {{ tenant?.name ?? 'PawPass' }}
          </span>
        </Link>
      </div>

      <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <Link
          v-for="item in navItems"
          :key="item.name"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900"
        >
          {{ item.name }}
        </Link>
      </nav>

      <div class="border-t border-gray-200 p-4">
        <div class="flex items-center gap-3 mb-3">
          <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-semibold text-sm">
            {{ userInitial }}
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ auth.user?.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ auth.user?.role }}</p>
          </div>
        </div>
        <form @submit.prevent="logout">
          <button type="submit" class="w-full text-left text-sm text-red-600 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50">
            Sign out
          </button>
        </form>
      </div>
    </aside>

    <!-- Main content -->
    <div class="md:ml-64 flex-1 flex flex-col min-h-screen">
      <!-- Top bar (mobile) -->
      <header class="md:hidden bg-white border-b border-gray-200 sticky top-0 z-20 flex items-center justify-between h-14 px-4">
        <Link :href="route('admin.dashboard')" class="text-lg font-bold" :style="{ color: accentColor }">
          {{ tenant?.name ?? 'PawPass' }}
        </Link>
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="p-2 text-gray-500">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
      </header>

      <!-- Mobile menu -->
      <div v-if="mobileMenuOpen" class="md:hidden bg-white border-b border-gray-200 px-4 py-3 space-y-1 z-20">
        <Link
          v-for="item in navItems"
          :key="item.name"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50"
          @click="mobileMenuOpen = false"
        >
          {{ item.name }}
        </Link>
        <form @submit.prevent="logout" class="pt-2 border-t border-gray-100">
          <button type="submit" class="text-sm text-red-600 px-3 py-2">Sign out</button>
        </form>
      </div>

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
    </div>

    <!-- Mobile bottom nav -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 flex z-30">
      <Link :href="route('admin.dashboard')" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs text-gray-500">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
        <span>Home</span>
      </Link>
      <Link :href="route('admin.roster.index')" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs text-gray-500">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
        <span>Roster</span>
      </Link>
      <Link :href="route('admin.customers.index')" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs text-gray-500">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
        </svg>
        <span>Customers</span>
      </Link>
      <Link :href="route('admin.dogs.index')" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs text-gray-500">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
        </svg>
        <span>Dogs</span>
      </Link>
    </nav>
    <!-- Mobile bottom padding -->
    <div class="md:hidden h-16" />
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

const navItems = computed(() => {
  const items = [
    { name: 'Dashboard', href: route('admin.dashboard') },
    { name: 'Roster', href: route('admin.roster.index') },
    { name: 'Customers', href: route('admin.customers.index') },
    { name: 'Dogs', href: route('admin.dogs.index') },
    { name: 'Payments', href: route('admin.payments.index') },
  ];

  if (hasReports.value) {
    items.push({ name: 'Reports', href: route('admin.reports.index') });
  }

  if (isOwner.value) {
    items.push(
      { name: 'Packages', href: route('admin.packages.index') },
      { name: 'Settings', href: route('admin.settings.index') },
      { name: 'Billing', href: route('admin.billing.index') },
    );
  }

  return items;
});

const logoutForm = useForm({});
function logout() {
  logoutForm.post(route('admin.logout'));
}
</script>
