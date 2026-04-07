<template>
  <div>
    <!-- Mobile slide-over sidebar -->
    <TransitionRoot as="template" :show="sidebarOpen">
      <Dialog class="relative z-50 lg:hidden" @close="sidebarOpen = false">
        <TransitionChild
          as="template"
          enter="transition-opacity ease-linear duration-300"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="transition-opacity ease-linear duration-300"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-gray-900/80" />
        </TransitionChild>

        <div class="fixed inset-0 flex">
          <TransitionChild
            as="template"
            enter="transition ease-in-out duration-300 transform"
            enter-from="-translate-x-full"
            enter-to="translate-x-0"
            leave="transition ease-in-out duration-300 transform"
            leave-from="translate-x-0"
            leave-to="-translate-x-full"
          >
            <DialogPanel class="relative mr-16 flex w-full max-w-xs flex-1">
              <TransitionChild
                as="template"
                enter="ease-in-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in-out duration-300"
                leave-from="opacity-100"
                leave-to="opacity-0"
              >
                <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                  <button type="button" class="-m-2.5 p-2.5" @click="sidebarOpen = false">
                    <span class="sr-only">Close sidebar</span>
                    <XMarkIcon class="size-6 text-white" aria-hidden="true" />
                  </button>
                </div>
              </TransitionChild>

              <!-- Mobile sidebar content -->
              <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-indigo-800 px-6 pb-4 ring-1 ring-white/10">
                <div class="flex h-16 shrink-0 items-center">
                  <Link :href="route('portal.dashboard')" class="flex items-center gap-2.5" @click="sidebarOpen = false">
                    <img v-if="tenant?.logo_url" :src="tenant.logo_url" :alt="tenant.name" class="h-8 w-auto" />
                    <span v-else class="text-white font-bold text-lg tracking-tight">{{ tenant?.name ?? 'PawPass' }}</span>
                  </Link>
                </div>
                <nav class="flex flex-1 flex-col">
                  <ul role="list" class="flex flex-1 flex-col gap-y-7">
                    <li>
                      <ul role="list" class="-mx-2 space-y-1">
                        <li>
                          <Link :href="route('portal.dashboard')" :class="[isActive('portal.dashboard') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <HomeIcon class="size-6 shrink-0" aria-hidden="true" />
                            Dashboard
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('portal.dogs.index')" :class="[isActive('portal.dogs.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <UserGroupIcon class="size-6 shrink-0" aria-hidden="true" />
                            My Dogs
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('portal.purchase')" :class="[isActive('portal.purchase') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <PlusIcon class="size-6 shrink-0" aria-hidden="true" />
                            Buy Credits
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('portal.history')" :class="[isActive('portal.history') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <ClipboardDocumentListIcon class="size-6 shrink-0" aria-hidden="true" />
                            Invoices
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('portal.attendance')" :class="[isActive('portal.attendance') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <CalendarDaysIcon class="size-6 shrink-0" aria-hidden="true" />
                            Attendance
                          </Link>
                        </li>
                        <li v-if="showBoarding">
                          <Link :href="route('portal.boarding.index')" :class="[isActive('portal.boarding.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <HomeModernIcon class="size-6 shrink-0" aria-hidden="true" />
                            Boarding
                          </Link>
                        </li>
                      </ul>
                    </li>

                    <!-- User footer -->
                    <li class="-mx-6 mt-auto">
                      <div class="border-t border-white/10 px-6 pt-3 pb-1 space-y-1">
                        <Link :href="route('portal.notifications')" class="flex items-center gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-indigo-100 hover:bg-indigo-950/25 hover:text-white" @click="sidebarOpen = false">
                          <BellIcon class="size-6 shrink-0" aria-hidden="true" />
                          <span>Notifications</span>
                          <span v-if="unreadCount > 0" class="ml-auto inline-flex items-center justify-center size-5 rounded-full bg-indigo-600 text-white text-xs font-bold">
                            {{ unreadCount > 9 ? '9+' : unreadCount }}
                          </span>
                        </Link>
                        <Link :href="route('portal.account')" class="flex items-center gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-indigo-100 hover:bg-indigo-950/25 hover:text-white" @click="sidebarOpen = false">
                          <UserCircleIcon class="size-6 shrink-0" aria-hidden="true" />
                          Account
                        </Link>
                      </div>
                      <div class="flex items-center gap-x-4 px-6 py-3">
                        <div class="size-8 rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0 bg-indigo-600">
                          {{ userInitial }}
                        </div>
                        <div class="min-w-0 flex-1">
                          <p class="text-sm/6 font-semibold text-white truncate">{{ auth.user?.name }}</p>
                        </div>
                        <form @submit.prevent="logout">
                          <button type="submit" class="text-xs text-indigo-200 hover:text-red-400 transition-colors">
                            Sign out
                          </button>
                        </form>
                      </div>
                    </li>
                  </ul>
                </nav>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Desktop sidebar -->
    <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
      <div class="relative flex grow flex-col gap-y-5 overflow-y-auto bg-indigo-800 px-6 after:pointer-events-none after:absolute after:inset-y-0 after:right-0 after:w-px after:bg-white/10">
        <!-- Logo -->
        <div class="flex h-16 shrink-0 items-center">
          <Link :href="route('portal.dashboard')" class="flex items-center gap-2.5">
            <img v-if="tenant?.logo_url" :src="tenant.logo_url" :alt="tenant.name" class="h-8 w-auto" />
            <span v-else class="text-white font-bold text-lg tracking-tight">{{ tenant?.name ?? 'PawPass' }}</span>
          </Link>
        </div>

        <nav class="flex flex-1 flex-col">
          <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <li>
              <ul role="list" class="-mx-2 space-y-1">
                <li>
                  <Link :href="route('portal.dashboard')" :class="[isActive('portal.dashboard') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <HomeIcon class="size-6 shrink-0" aria-hidden="true" />
                    Dashboard
                  </Link>
                </li>
                <li>
                  <Link :href="route('portal.dogs.index')" :class="[isActive('portal.dogs.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <UserGroupIcon class="size-6 shrink-0" aria-hidden="true" />
                    My Dogs
                  </Link>
                </li>
                <li>
                  <Link :href="route('portal.purchase')" :class="[isActive('portal.purchase') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <PlusIcon class="size-6 shrink-0" aria-hidden="true" />
                    Buy Credits
                  </Link>
                </li>
                <li>
                  <Link :href="route('portal.history')" :class="[isActive('portal.history') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <ClipboardDocumentListIcon class="size-6 shrink-0" aria-hidden="true" />
                    Invoices
                  </Link>
                </li>
                <li>
                  <Link :href="route('portal.attendance')" :class="[isActive('portal.attendance') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <CalendarDaysIcon class="size-6 shrink-0" aria-hidden="true" />
                    Attendance
                  </Link>
                </li>
                <li v-if="showBoarding">
                  <Link :href="route('portal.boarding.index')" :class="[isActive('portal.boarding.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <HomeModernIcon class="size-6 shrink-0" aria-hidden="true" />
                    Boarding
                  </Link>
                </li>
              </ul>
            </li>

            <!-- User footer -->
            <li class="-mx-6 mt-auto">
              <div class="border-t border-white/10 px-4 pt-3 pb-1 space-y-1">
                <Link :href="route('portal.notifications')" class="flex items-center gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-indigo-100 hover:bg-indigo-950/25 hover:text-white">
                  <BellIcon class="size-6 shrink-0" aria-hidden="true" />
                  <span>Notifications</span>
                  <span v-if="unreadCount > 0" class="ml-auto inline-flex items-center justify-center size-5 rounded-full bg-indigo-600 text-white text-xs font-bold">
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                  </span>
                </Link>
                <Link :href="route('portal.account')" class="flex items-center gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-indigo-100 hover:bg-indigo-950/25 hover:text-white">
                  <UserCircleIcon class="size-6 shrink-0" aria-hidden="true" />
                  Account
                </Link>
              </div>
              <div class="flex items-center gap-x-4 px-6 py-3">
                <div class="size-8 rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0 bg-indigo-600">
                  {{ userInitial }}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm/6 font-semibold text-white truncate">{{ auth.user?.name }}</p>
                </div>
                <form @submit.prevent="logout">
                  <button type="submit" class="text-xs text-indigo-200 hover:text-red-400 transition-colors">
                    Sign out
                  </button>
                </form>
              </div>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Mobile top bar -->
    <div class="sticky top-0 z-40 flex items-center gap-x-6 bg-indigo-800 px-4 py-4 shadow-sm after:pointer-events-none after:absolute after:inset-x-0 after:bottom-0 after:h-px after:bg-white/10 sm:px-6 lg:hidden">
      <button type="button" class="-m-2.5 p-2.5 text-indigo-200 hover:text-white" @click="sidebarOpen = true">
        <span class="sr-only">Open sidebar</span>
        <Bars3Icon class="size-6" aria-hidden="true" />
      </button>
      <div class="flex-1 text-sm/6 font-semibold text-white">{{ tenant?.name ?? 'PawPass' }}</div>
      <Link :href="route('portal.notifications')" class="relative p-1 text-indigo-200 hover:text-white">
        <BellIcon class="size-6" aria-hidden="true" />
        <span
          v-if="unreadCount > 0"
          class="absolute top-0 right-0 flex items-center justify-center size-4 rounded-full bg-indigo-600 text-white text-xs font-bold"
        >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
      </Link>
    </div>

    <!-- Main content -->
    <main class="py-10 lg:pl-72">
      <div class="px-4 sm:px-6 lg:px-8">
        <!-- Flash messages -->
        <div v-if="flash.success || flash.error" class="mb-6 space-y-2">
          <AppAlert v-if="flash.success" type="success" :message="flash.success" @dismiss="dismissFlash('success')" />
          <AppAlert v-if="flash.error" type="error" :message="flash.error" @dismiss="dismissFlash('error')" />
        </div>

        <slot />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Dialog, DialogPanel, TransitionRoot, TransitionChild } from '@headlessui/vue';
import {
  Bars3Icon,
  BellIcon,
  CalendarDaysIcon,
  ClipboardDocumentListIcon,
  HomeIcon,
  HomeModernIcon,
  PlusIcon,
  UserCircleIcon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import AppAlert from '@/Components/AppAlert.vue';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const auth = computed(() => page.props.auth);
const unreadCount = computed(() => page.props.unreadCount);
const flash = computed(() => page.props.flash ?? { success: null, error: null });
const userInitial = computed(() => auth.value.user?.name?.[0]?.toUpperCase() ?? '?');

const showBoarding = computed(() => tenant.value?.business_type === 'kennel' || tenant.value?.business_type === 'hybrid');

const sidebarOpen = ref(false);

const dismissedFlash = ref<Record<string, boolean>>({});
function dismissFlash(key: string) { dismissedFlash.value[key] = true; }

const logoutForm = useForm({});
function logout() { logoutForm.post(route('portal.logout')); }

function isActive(pattern: string): boolean {
  try { return !!(route as any)().current(pattern); } catch { return false; }
}
</script>
