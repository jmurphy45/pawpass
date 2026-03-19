<template>
  <PortalLayout>
    <div class="space-y-8">
      <!-- Greeting header -->
      <div>
        <p class="text-sm font-medium text-text-muted">{{ greeting }}</p>
        <h1 class="text-2xl font-bold text-text-body">{{ firstName }}</h1>
      </div>

      <!-- Dog credit cards -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-text-body">My Dogs</h2>
          <Link :href="route('portal.dogs.index')" class="text-sm text-indigo-600 hover:underline">Manage →</Link>
        </div>

        <div v-if="dogs.length === 0" class="card p-12 text-center">
          <div class="flex justify-center mb-3">
            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-border-warm">
              <ellipse cx="24" cy="16" rx="7" ry="9" fill="currentColor" opacity="0.4"/>
              <ellipse cx="40" cy="16" rx="7" ry="9" fill="currentColor" opacity="0.4"/>
              <ellipse cx="12" cy="32" rx="6" ry="8" transform="rotate(-15 12 32)" fill="currentColor" opacity="0.4"/>
              <ellipse cx="52" cy="32" rx="6" ry="8" transform="rotate(15 52 32)" fill="currentColor" opacity="0.4"/>
              <ellipse cx="32" cy="46" rx="14" ry="12" fill="currentColor" opacity="0.5"/>
            </svg>
          </div>
          <p class="font-semibold text-text-body">No dogs yet</p>
          <p class="text-sm text-text-muted mt-1">Add your first dog to start tracking credits</p>
          <Link :href="route('portal.dogs.create')" class="btn-primary mt-4 inline-flex">Add a Dog</Link>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="dog in dogs"
            :key="dog.id"
            class="card overflow-hidden hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200"
          >
            <!-- Gradient header strip -->
            <div
              class="h-20 relative flex items-end px-4 pb-3"
              :style="{ background: `linear-gradient(135deg, ${accentColor}ee 0%, ${accentColor}88 100%)` }"
            >
              <div class="h-12 w-12 rounded-full flex items-center justify-center text-xl font-bold text-white border-2 shrink-0" style="background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3);">
                {{ dog.name[0]?.toUpperCase() }}
              </div>
              <span
                class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-xs font-semibold"
                :class="statusBadgeClass(dog.credit_status)"
              >{{ statusLabel(dog.credit_status) }}</span>
            </div>

            <div class="p-4">
              <p class="font-semibold text-text-body">{{ dog.name }}</p>
              <p v-if="dog.breed" class="text-xs text-text-muted">{{ dog.breed }}</p>

              <div class="mt-3">
                <div class="flex items-center justify-between mb-1.5">
                  <span class="text-xs text-text-muted">Credits</span>
                  <span class="text-sm font-bold text-text-body">{{ dog.credit_balance }}</span>
                </div>
                <div class="h-1.5 w-full rounded-full bg-surface-subtle overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-700 ease-out"
                    :class="progressColor(dog.credit_status)"
                    :style="{ width: mounted ? progressWidth(dog.credit_balance) : '0%' }"
                  />
                </div>
              </div>

              <Link
                :href="route('portal.purchase')"
                class="mt-3 block text-center text-xs font-medium text-indigo-600 hover:underline"
              >Buy Credits →</Link>
            </div>
          </div>
        </div>
      </section>

      <!-- Quick Actions -->
      <section>
        <h2 class="text-base font-semibold text-text-body mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <Link
            :href="route('portal.purchase')"
            class="card p-4 flex flex-col items-center justify-center gap-2.5 hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200 text-center"
          >
            <div class="h-10 w-10 rounded-xl bg-indigo-100 flex items-center justify-center">
              <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
              </svg>
            </div>
            <span class="text-sm font-medium text-text-body">Buy Credits</span>
          </Link>

          <Link
            :href="route('portal.attendance')"
            class="card p-4 flex flex-col items-center justify-center gap-2.5 hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200 text-center"
          >
            <div class="h-10 w-10 rounded-xl bg-amber-100 flex items-center justify-center">
              <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
              </svg>
            </div>
            <span class="text-sm font-medium text-text-body">Attendance</span>
          </Link>

          <Link
            :href="route('portal.history')"
            class="card p-4 flex flex-col items-center justify-center gap-2.5 hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200 text-center"
          >
            <div class="h-10 w-10 rounded-xl bg-purple-100 flex items-center justify-center">
              <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
              </svg>
            </div>
            <span class="text-sm font-medium text-text-body">Invoices</span>
          </Link>
        </div>
      </section>

      <!-- Recent Notifications -->
      <section v-if="recentNotifications.length > 0">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-text-body">Recent Notifications</h2>
          <Link :href="route('portal.notifications')" class="text-sm text-indigo-600 hover:underline">View all →</Link>
        </div>
        <div class="card overflow-hidden">
          <div v-for="n in recentNotifications" :key="n.id" class="list-row gap-3">
            <span class="text-lg leading-none shrink-0">{{ notifIcon(n.type) }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-text-body">{{ n.data?.message ?? n.type }}</p>
              <p class="text-xs text-text-muted mt-0.5">{{ formatDate(n.created_at) }}</p>
            </div>
            <span v-if="!n.read_at" class="h-2 w-2 rounded-full bg-amber-400 shrink-0 animate-pulse" />
          </div>
        </div>
      </section>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { PageProps } from '@/types';

interface DogCard {
  id: string;
  name: string;
  breed: string | null;
  credit_balance: number;
  credits_expire_at: string | null;
  credit_status: 'ok' | 'low' | 'empty';
}

interface NotifCard {
  id: string;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
}

defineProps<{
  dogs: DogCard[];
  recentNotifications: NotifCard[];
}>();

const page = usePage<PageProps>();
const accentColor = computed(() => page.props.tenant?.primary_color ?? '#4f46e5');
const auth = computed(() => page.props.auth);

const firstName = computed(() => {
  const name = auth.value.user?.name ?? '';
  return name.split(' ')[0] || 'Welcome back';
});

const greeting = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 17) return 'Good afternoon';
  return 'Good evening';
});

const mounted = ref(false);
onMounted(() => { mounted.value = true; });

function statusBadgeClass(status: string) {
  return {
    ok: 'bg-green-500/20 text-green-100',
    low: 'bg-amber-500/20 text-amber-100',
    empty: 'bg-red-500/30 text-red-100',
  }[status] ?? 'bg-white/20 text-white';
}

function statusLabel(status: string) {
  return { ok: 'Good', low: 'Low', empty: 'Empty' }[status] ?? status;
}

function progressColor(status: string) {
  return {
    ok: 'bg-green-500',
    low: 'bg-yellow-400',
    empty: 'bg-red-400',
  }[status] ?? 'bg-gray-300';
}

function progressWidth(balance: number) {
  return `${Math.min(100, Math.max(0, (balance / 20) * 100))}%`;
}

function notifIcon(type: string) {
  if (type.includes('credits.low')) return '⚠️';
  if (type.includes('credits.empty')) return '🚨';
  if (type.includes('payment')) return '✅';
  if (type.includes('subscription')) return '♻️';
  return '🔔';
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
