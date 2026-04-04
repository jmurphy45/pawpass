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
        <div
          v-if="tenantPlan === 'trialing' || tenantPlan === 'free_tier'"
          class="inline-flex items-center self-start px-2 py-0.5 rounded-full text-xs font-medium"
          style="background-color: rgba(245,158,11,0.12); color: #fcd34d;"
        >{{ tenantPlan === 'trialing' ? 'Trial' : 'Free' }}</div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-2 pb-4 space-y-0.5">
        <Link
          :href="route('admin.dashboard')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.dashboard') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.dashboard') ? { backgroundColor: accentColor } : {}"
        >
          <HomeIcon class="h-5 w-5 shrink-0" />
          Dashboard
        </Link>

        <div class="text-[0.625rem] font-bold uppercase tracking-[0.08em] text-white/35 px-3 pt-3 pb-1">Operations</div>

        <Link
          :href="route('admin.roster.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.roster.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.roster.*') ? { backgroundColor: accentColor } : {}"
        >
          <ListBulletIcon class="h-5 w-5 shrink-0" />
          Roster
        </Link>

        <Link
          v-if="hasBoarding"
          :href="route('admin.boarding.reservations')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.boarding.reservations*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.boarding.reservations*') ? { backgroundColor: accentColor } : {}"
        >
          <HomeModernIcon class="h-5 w-5 shrink-0" />
          Boarding
        </Link>

        <Link
          v-if="hasBoarding"
          :href="route('admin.boarding.units')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.boarding.units*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.boarding.units*') ? { backgroundColor: accentColor } : {}"
        >
          <Squares2X2Icon class="h-5 w-5 shrink-0" />
          Kennel Units
        </Link>

        <Link
          :href="route('admin.customers.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.customers.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.customers.*') ? { backgroundColor: accentColor } : {}"
        >
          <UsersIcon class="h-5 w-5 shrink-0" />
          Customers
        </Link>

        <Link
          :href="route('admin.dogs.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.dogs.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.dogs.*') ? { backgroundColor: accentColor } : {}"
        >
          <UserGroupIcon class="h-5 w-5 shrink-0" />
          Dogs
        </Link>

        <div class="text-[0.625rem] font-bold uppercase tracking-[0.08em] text-white/35 px-3 pt-3 pb-1">Business</div>

        <Link
          :href="route('admin.payments.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.payments.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.payments.*') ? { backgroundColor: accentColor } : {}"
        >
          <CreditCardIcon class="h-5 w-5 shrink-0" />
          Payments
        </Link>

        <Link
          v-if="hasReports"
          :href="route('admin.reports.index')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.reports.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.reports.*') ? { backgroundColor: accentColor } : {}"
        >
          <ChartBarIcon class="h-5 w-5 shrink-0" />
          Reports
        </Link>

        <div class="text-[0.625rem] font-bold uppercase tracking-[0.08em] text-white/35 px-3 pt-3 pb-1">Communications</div>

        <Link
          v-if="hasBroadcast"
          :href="route('admin.notifications.broadcast')"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive('admin.notifications.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
          :style="isActive('admin.notifications.*') ? { backgroundColor: accentColor } : {}"
        >
          <MegaphoneIcon class="h-5 w-5 shrink-0" />
          Broadcast
        </Link>

        <template v-if="isOwner">
          <div class="text-[0.625rem] font-bold uppercase tracking-[0.08em] text-white/35 px-3 pt-3 pb-1">Owner</div>

          <Link
            :href="route('admin.packages.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.packages.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.packages.*') ? { backgroundColor: accentColor } : {}"
          >
            <ArchiveBoxIcon class="h-5 w-5 shrink-0" />
            Packages
          </Link>

          <Link
            v-if="hasAddonServices"
            :href="route('admin.services.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.services.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.services.*') ? { backgroundColor: accentColor } : {}"
          >
            <SparklesIcon class="h-5 w-5 shrink-0" />
            Services
          </Link>

          <Link
            :href="route('admin.vaccination-requirements.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.vaccination-requirements.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.vaccination-requirements.*') ? { backgroundColor: accentColor } : {}"
          >
            <ShieldCheckIcon class="h-5 w-5 shrink-0" />
            Vaccinations
          </Link>

          <Link
            :href="route('admin.settings.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.settings.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.settings.*') ? { backgroundColor: accentColor } : {}"
          >
            <Cog6ToothIcon class="h-5 w-5 shrink-0" />
            Settings
          </Link>

          <Link
            :href="route('admin.billing.index')"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="isActive('admin.billing.*') ? 'text-white' : 'text-white/65 hover:bg-sidebar-hover'"
            :style="isActive('admin.billing.*') ? { backgroundColor: accentColor } : {}"
          >
            <CurrencyDollarIcon class="h-5 w-5 shrink-0" />
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
          <button type="submit" class="w-full text-left text-xs px-2 py-1 rounded transition-colors text-white/40 hover:text-red-400">
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
        <button @click="mobileMenuOpen = true" class="p-2 text-white/60">
          <Bars3Icon class="h-6 w-6" />
        </button>
      </header>

      <!-- Mobile sidebar (Headless UI Dialog) -->
      <TransitionRoot as="template" :show="mobileMenuOpen">
        <Dialog class="relative z-40 md:hidden" @close="mobileMenuOpen = false">
          <TransitionChild
            as="template"
            enter="ease-in-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
            leave="ease-in-out duration-200" leave-from="opacity-100" leave-to="opacity-0"
          >
            <div class="fixed inset-0 bg-black/50" />
          </TransitionChild>

          <div class="fixed inset-0 flex">
            <TransitionChild
              as="template"
              enter="transition ease-in-out duration-200 transform" enter-from="-translate-x-full" enter-to="translate-x-0"
              leave="transition ease-in-out duration-200 transform" leave-from="translate-x-0" leave-to="-translate-x-full"
            >
              <DialogPanel
                class="relative flex w-full max-w-xs flex-col overflow-y-auto pb-4"
                style="background-color: #0f0e0d;"
              >
                <div class="flex items-center justify-between px-4 pt-5 pb-3">
                  <span class="text-white font-bold text-lg">{{ tenant?.name ?? 'PawPass' }}</span>
                  <button @click="mobileMenuOpen = false" class="text-white/50 hover:text-white p-1">
                    <XMarkIcon class="h-6 w-6" />
                  </button>
                </div>

                <nav class="flex-1 px-2 space-y-0.5">
                  <Link
                    v-for="item in flatNavItems"
                    :key="item.name"
                    :href="item.href"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="isActive(item.pattern) ? 'text-white' : 'text-white/65'"
                    :style="isActive(item.pattern) ? { backgroundColor: accentColor } : {}"
                    @click="mobileMenuOpen = false"
                  >{{ item.name }}</Link>
                </nav>

                <div class="px-4 pt-4 mt-4" style="border-top: 1px solid rgba(255,255,255,0.06);">
                  <form @submit.prevent="logout">
                    <button type="submit" class="text-sm px-3 py-2 text-white/40 hover:text-red-400">Sign out</button>
                  </form>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </Dialog>
      </TransitionRoot>

      <!-- Spacer for fixed mobile header -->
      <div class="md:hidden h-14" />

      <!-- Flash messages -->
      <div v-if="flash.success || flash.error" class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-4 space-y-2">
        <AppAlert v-if="flash.success" type="success" :message="flash.success" @dismiss="dismissFlash('success')" />
        <AppAlert v-if="flash.error" type="error" :message="flash.error" @dismiss="dismissFlash('error')" />
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
        <HomeIcon class="h-6 w-6" />
        <span>Home</span>
      </Link>
      <Link
        :href="route('admin.roster.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('admin.roster.*') ? 'text-indigo-600' : 'text-gray-400'"
      >
        <ListBulletIcon class="h-6 w-6" />
        <span>Roster</span>
      </Link>
      <Link
        :href="route('admin.customers.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('admin.customers.*') ? 'text-indigo-600' : 'text-gray-400'"
      >
        <UsersIcon class="h-6 w-6" />
        <span>Customers</span>
      </Link>
      <Link
        :href="route('admin.dogs.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('admin.dogs.*') ? 'text-indigo-600' : 'text-gray-400'"
      >
        <UserGroupIcon class="h-6 w-6" />
        <span>Dogs</span>
      </Link>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Dialog, DialogPanel, TransitionRoot, TransitionChild } from '@headlessui/vue';
import {
  ArchiveBoxIcon,
  Bars3Icon,
  ChartBarIcon,
  Cog6ToothIcon,
  CreditCardIcon,
  CurrencyDollarIcon,
  HomeIcon,
  HomeModernIcon,
  ListBulletIcon,
  MegaphoneIcon,
  ShieldCheckIcon,
  SparklesIcon,
  Squares2X2Icon,
  UserGroupIcon,
  UsersIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import type { PageProps } from '@/types';
import { useFeatures } from '@/composables/useFeatures';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const auth = computed(() => page.props.auth);
const flash = computed(() => page.props.flash ?? { success: null, error: null });
const accentColor = computed(() => tenant.value?.primary_color ?? '#4f46e5');
const userInitial = computed(() => auth.value.user?.name?.[0]?.toUpperCase() ?? '?');

const mobileMenuOpen = ref(false);

const isOwner = computed(() => auth.value.user?.role === 'business_owner');
const tenantPlan = computed(() => page.props.tenantPlan);
const { hasFeature } = useFeatures();
const hasReports = computed(() => hasFeature('basic_reporting'));
const hasBoarding = computed(() => hasFeature('boarding'));
const hasAddonServices = computed(() => hasFeature('addon_services'));
const hasBroadcast = computed(() => hasFeature('broadcast_notifications'));

// Flash dismiss (reactive so AppAlert can hide without page reload)
const dismissedFlash = ref<Record<string, boolean>>({});
function dismissFlash(key: string) { dismissedFlash.value[key] = true; }

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

  if (hasBoarding.value) {
    items.push(
      { name: 'Boarding', href: route('admin.boarding.reservations'), pattern: 'admin.boarding.reservations*' },
      { name: 'Kennel Units', href: route('admin.boarding.units'), pattern: 'admin.boarding.units*' },
    );
  }
  if (hasReports.value) items.push({ name: 'Reports', href: route('admin.reports.index'), pattern: 'admin.reports.*' });
  if (hasBroadcast.value) items.push({ name: 'Broadcast', href: route('admin.notifications.broadcast'), pattern: 'admin.notifications.*' });
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
function logout() { logoutForm.post(route('admin.logout')); }
</script>
