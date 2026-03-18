<template>
  <div class="min-h-screen bg-surface flex flex-col">
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
            :class="isActive('portal.dashboard') ? 'text-gray-900 font-semibold border-indigo-600' : 'text-gray-500 hover:text-gray-800 border-transparent'"
          >Dashboard</Link>
          <Link
            :href="route('portal.dogs.index')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.dogs.*') ? 'text-gray-900 font-semibold border-indigo-600' : 'text-gray-500 hover:text-gray-800 border-transparent'"
          >My Dogs</Link>
          <Link
            :href="route('portal.purchase')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.purchase') ? 'text-gray-900 font-semibold border-indigo-600' : 'text-gray-500 hover:text-gray-800 border-transparent'"
          >Buy Credits</Link>
          <Link
            :href="route('portal.subscribe')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.subscribe') ? 'text-gray-900 font-semibold border-indigo-600' : 'text-gray-500 hover:text-gray-800 border-transparent'"
          >Subscribe</Link>
          <Link
            :href="route('portal.history')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.history') ? 'text-gray-900 font-semibold border-indigo-600' : 'text-gray-500 hover:text-gray-800 border-transparent'"
          >Invoices</Link>
          <Link
            :href="route('portal.attendance')"
            class="text-sm font-medium border-b-2 pb-0.5 transition-colors"
            :class="isActive('portal.attendance') ? 'text-gray-900 font-semibold border-indigo-600' : 'text-gray-500 hover:text-gray-800 border-transparent'"
          >Attendance</Link>
        </div>

        <!-- Right side actions -->
        <div class="flex items-center gap-3">
          <!-- Notification bell -->
          <Link :href="route('portal.notifications')" class="relative p-2 text-gray-500 hover:text-gray-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span
              v-if="unreadCount > 0"
              class="absolute top-1 right-1 flex items-center justify-center h-4 w-4 rounded-full text-white text-xs font-bold"
              :style="{ backgroundColor: accentColor }"
            >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
          </Link>

          <!-- User dropdown -->
          <div class="relative" ref="dropdownRef">
            <button
              @click="dropdownOpen = !dropdownOpen"
              class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 font-medium"
            >
              <span class="hidden sm:block">{{ auth.user?.name }}</span>
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <Transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="opacity-100 scale-100"
              leave-to-class="opacity-0 scale-95"
            >
              <div
                v-if="dropdownOpen"
                class="absolute right-0 mt-2 w-44 bg-white rounded-lg shadow-lg py-1 z-50"
                style="border: 1px solid #e5e0d8;"
              >
                <Link
                  :href="route('portal.account')"
                  class="block px-4 py-2 text-sm text-gray-700 hover:bg-surface"
                  @click="dropdownOpen = false"
                >Account</Link>
                <form @submit.prevent="logout">
                  <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                    Sign out
                  </button>
                </form>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </nav>

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

    <!-- Mobile bottom nav -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-border-warm flex z-30">
      <Link
        :href="route('portal.dashboard')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('portal.dashboard') ? 'text-indigo-600' : 'text-gray-400 hover:text-gray-900'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
        <span>Home</span>
      </Link>
      <Link
        :href="route('portal.dogs.index')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('portal.dogs.*') ? 'text-indigo-600' : 'text-gray-400 hover:text-gray-900'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
        </svg>
        <span>Dogs</span>
      </Link>
      <Link
        :href="route('portal.purchase')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('portal.purchase') ? 'text-indigo-600' : 'text-gray-400 hover:text-gray-900'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span>Buy</span>
      </Link>
      <Link
        :href="route('portal.subscribe')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('portal.subscribe') ? 'text-indigo-600' : 'text-gray-400 hover:text-gray-900'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9-4.5h-9m9-4.5h-9m-3.75 9a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 5.25 3h13.5A2.25 2.25 0 0 1 21 5.25v11.25A2.25 2.25 0 0 1 18.75 19.5H5.25Z" />
        </svg>
        <span>Subscribe</span>
      </Link>
      <Link
        :href="route('portal.history')"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs"
        :class="isActive('portal.history') ? 'text-indigo-600' : 'text-gray-400 hover:text-gray-900'"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
        </svg>
        <span>History</span>
      </Link>
    </nav>
    <!-- Mobile bottom padding -->
    <div class="md:hidden h-16" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const auth = computed(() => page.props.auth);
const unreadCount = computed(() => page.props.unreadCount);
const flash = computed(() => page.props.flash ?? { success: null, error: null });

const accentColor = computed(() => tenant.value?.primary_color ?? '#4f46e5');

const dropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const logoutForm = useForm({});
function logout() {
  logoutForm.post(route('portal.logout'));
}

function isActive(pattern: string): boolean {
  try { return !!(route as any)().current(pattern); } catch { return false; }
}

function handleClickOutside(e: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    dropdownOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>
