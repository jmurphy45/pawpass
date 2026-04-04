<template>
  <div class="min-h-screen bg-surface flex flex-col overflow-x-clip">
    <!-- Top Navbar -->
    <nav class="bg-white shadow-sm border-b border-border-warm sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
        <!-- Logo / Tenant Name -->
        <Link :href="route('portal.dashboard')" class="flex items-center gap-2">
          <img v-if="tenant?.logo_url" :src="tenant.logo_url" :alt="tenant.name" class="h-8 w-auto" />
          <span
            v-else
            class="text-xl font-bold tracking-tight"
            :style="{ color: accentColor }"
          >{{ tenant?.name ?? 'PawPass' }}</span>
        </Link>

        <!-- Desktop nav items -->
        <div class="hidden md:flex items-center gap-6">
          <Link
            :href="route('portal.dashboard')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.dashboard') ? 'text-text-body font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
            :style="{ borderBottomColor: isActive('portal.dashboard') ? accentColor : 'transparent' }"
          >Dashboard</Link>
          <Link
            :href="route('portal.dogs.index')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.dogs.*') ? 'text-text-body font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
            :style="{ borderBottomColor: isActive('portal.dogs.*') ? accentColor : 'transparent' }"
          >My Dogs</Link>
          <Link
            :href="route('portal.purchase')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.purchase') ? 'text-text-body font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
            :style="{ borderBottomColor: isActive('portal.purchase') ? accentColor : 'transparent' }"
          >Buy Credits</Link>
          <Link
            :href="route('portal.history')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.history') ? 'text-text-body font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
            :style="{ borderBottomColor: isActive('portal.history') ? accentColor : 'transparent' }"
          >Invoices</Link>
          <Link
            :href="route('portal.attendance')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.attendance') ? 'text-text-body font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
            :style="{ borderBottomColor: isActive('portal.attendance') ? accentColor : 'transparent' }"
          >Attendance</Link>
          <Link
            v-if="showBoarding"
            :href="route('portal.boarding.index')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.boarding.*') ? 'text-text-body font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
            :style="{ borderBottomColor: isActive('portal.boarding.*') ? accentColor : 'transparent' }"
          >Boarding</Link>
        </div>

        <!-- Right side actions -->
        <div class="flex items-center gap-3">
          <!-- Notification bell -->
          <Link :href="route('portal.notifications')" class="relative p-2 text-gray-500 hover:text-gray-700">
            <BellIcon class="h-6 w-6" />
            <span
              v-if="unreadCount > 0"
              class="absolute top-1 right-1 flex items-center justify-center h-4 w-4 rounded-full text-white text-xs font-bold"
              :style="{ backgroundColor: accentColor }"
            >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
          </Link>

          <!-- User dropdown (Headless UI Menu) -->
          <Menu as="div" class="relative">
            <MenuButton class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 font-medium">
              <span class="hidden sm:block">{{ auth.user?.name }}</span>
              <ChevronDownIcon class="h-4 w-4" />
            </MenuButton>

            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="opacity-100 scale-100"
              leave-to-class="opacity-0 scale-95"
            >
              <MenuItems class="absolute right-0 mt-2 w-44 bg-white rounded-lg shadow-card-md border border-border-warm py-1 z-50 focus:outline-none">
                <MenuItem v-slot="{ active }">
                  <Link
                    :href="route('portal.account')"
                    :class="['block px-4 py-2 text-sm text-gray-700', active ? 'bg-surface-subtle' : '']"
                  >Account</Link>
                </MenuItem>
                <MenuItem v-slot="{ active }">
                  <form @submit.prevent="logout">
                    <button
                      type="submit"
                      :class="['block w-full text-left px-4 py-2 text-sm text-red-600', active ? 'bg-red-50' : '']"
                    >Sign out</button>
                  </form>
                </MenuItem>
              </MenuItems>
            </transition>
          </Menu>
        </div>
      </div>
    </nav>

    <!-- Flash messages -->
    <div v-if="flash.success || flash.error" class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-4 space-y-2">
      <AppAlert v-if="flash.success" type="success" :message="flash.success" @dismiss="dismissFlash('success')" />
      <AppAlert v-if="flash.error" type="error" :message="flash.error" @dismiss="dismissFlash('error')" />
    </div>

    <!-- Page content -->
    <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>

    <!-- Mobile bottom nav -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-border-warm flex z-30">
      <Link
        :href="route('portal.dashboard')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs transition-colors"
        :class="isActive('portal.dashboard') ? 'font-medium' : 'text-gray-400 hover:text-gray-900'"
        :style="isActive('portal.dashboard') ? { color: accentColor } : {}"
      >
        <HomeIcon class="h-6 w-6" />
        <span>Home</span>
      </Link>
      <Link
        :href="route('portal.dogs.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs transition-colors"
        :class="isActive('portal.dogs.*') ? 'font-medium' : 'text-gray-400 hover:text-gray-900'"
        :style="isActive('portal.dogs.*') ? { color: accentColor } : {}"
      >
        <UserGroupIcon class="h-6 w-6" />
        <span>Dogs</span>
      </Link>
      <Link
        :href="route('portal.purchase')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs transition-colors"
        :class="isActive('portal.purchase') ? 'font-medium' : 'text-gray-400 hover:text-gray-900'"
        :style="isActive('portal.purchase') ? { color: accentColor } : {}"
      >
        <PlusIcon class="h-6 w-6" />
        <span>Buy</span>
      </Link>
      <Link
        :href="route('portal.history')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs transition-colors"
        :class="isActive('portal.history') ? 'font-medium' : 'text-gray-400 hover:text-gray-900'"
        :style="isActive('portal.history') ? { color: accentColor } : {}"
      >
        <ClipboardDocumentListIcon class="h-6 w-6" />
        <span>History</span>
      </Link>
      <Link
        v-if="showBoarding"
        :href="route('portal.boarding.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs transition-colors"
        :class="isActive('portal.boarding.*') ? 'font-medium' : 'text-gray-400 hover:text-gray-900'"
        :style="isActive('portal.boarding.*') ? { color: accentColor } : {}"
      >
        <HomeModernIcon class="h-6 w-6" />
        <span>Boarding</span>
      </Link>
    </nav>
    <!-- Mobile bottom padding -->
    <div class="md:hidden h-16" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';
import {
  BellIcon,
  ChevronDownIcon,
  ClipboardDocumentListIcon,
  HomeIcon,
  HomeModernIcon,
  PlusIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const auth = computed(() => page.props.auth);
const unreadCount = computed(() => page.props.unreadCount);
const flash = computed(() => page.props.flash ?? { success: null, error: null });

const accentColor = computed(() => tenant.value?.primary_color ?? '#4f46e5');
const showBoarding = computed(() => tenant.value?.business_type === 'kennel' || tenant.value?.business_type === 'hybrid');

const dismissedFlash = ref<Record<string, boolean>>({});
function dismissFlash(key: string) { dismissedFlash.value[key] = true; }

const logoutForm = useForm({});
function logout() { logoutForm.post(route('portal.logout')); }

function isActive(pattern: string): boolean {
  try { return !!(route as any)().current(pattern); } catch { return false; }
}
</script>
