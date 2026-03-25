<template>
  <PortalLayout>
    <div class="space-y-8">
      <!-- Hero greeting banner -->
      <div
        class="dp-hero"
        :style="{
          background: `linear-gradient(135deg, ${accentColor}18 0%, ${accentColor}08 100%)`,
          borderBottom: `1px solid ${accentColor}20`,
        }"
      >
        <div class="dp-hero-accent" :style="{ background: accentColor }" />
        <div class="dp-hero-content">
          <p class="dp-hero-greeting">{{ greeting }}</p>
          <h1 class="dp-hero-name">{{ firstName }}</h1>
          <p v-if="tenantName" class="dp-hero-tenant">{{ tenantName }}</p>
        </div>
      </div>

      <!-- Dog credit cards -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-text-body">My Dogs</h2>
          <Link :href="route('portal.dogs.index')" class="text-sm text-text-muted hover:text-text-body">Manage →</Link>
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
                class="mt-3 block text-center text-xs font-medium text-text-muted hover:text-text-body"
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
            class="card p-4 flex items-center gap-3 hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200"
          >
            <div class="dp-action-icon" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); box-shadow: 0 4px 12px #4f46e525;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-text-body">Buy Credits</p>
              <p class="text-xs text-text-muted">Top up your balance</p>
            </div>
          </Link>

          <Link
            :href="route('portal.attendance')"
            class="card p-4 flex items-center gap-3 hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200"
          >
            <div class="dp-action-icon" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); box-shadow: 0 4px 12px #d9770625;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-text-body">Attendance</p>
              <p class="text-xs text-text-muted">View visit history</p>
            </div>
          </Link>

          <Link
            :href="route('portal.history')"
            class="card p-4 flex items-center gap-3 hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200"
          >
            <div class="dp-action-icon" style="background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); box-shadow: 0 4px 12px #7c3aed25;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-text-body">Invoices</p>
              <p class="text-xs text-text-muted">Purchase history</p>
            </div>
          </Link>

          <Link
            v-if="showBoarding"
            :href="route('portal.boarding.index')"
            class="card p-4 flex items-center gap-3 hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200"
          >
            <div class="dp-action-icon" style="background: linear-gradient(135deg, #0d9488 0%, #2dd4bf 100%); box-shadow: 0 4px 12px #0d948825;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-text-body">Boarding</p>
              <p class="text-xs text-text-muted">Manage stays</p>
            </div>
          </Link>
        </div>
      </section>

      <!-- Recent Notifications -->
      <section v-if="recentNotifications.length > 0">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-text-body">Recent Notifications</h2>
          <Link :href="route('portal.notifications')" class="text-sm text-text-muted hover:text-text-body">View all →</Link>
        </div>
        <div class="card overflow-hidden">
          <div v-for="n in recentNotifications" :key="n.id" class="list-row gap-3">
            <div class="dp-notif-icon shrink-0" :style="{ background: notifBg(n.type) }">
              <svg v-if="n.type.includes('credits.empty')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
              <svg v-else-if="n.type.includes('credits.low')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
              </svg>
              <svg v-else-if="n.type.includes('payment')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
              </svg>
              <svg v-else-if="n.type.includes('subscription')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              <svg v-else fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
              </svg>
            </div>
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
const tenantName = computed(() => page.props.tenant?.name ?? '');
const auth = computed(() => page.props.auth);
const showBoarding = computed(() => {
  const bt = page.props.tenant?.business_type;
  return bt === 'kennel' || bt === 'hybrid';
});

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

function notifBg(type: string): string {
  if (type.includes('credits.empty')) return '#fee2e2';
  if (type.includes('credits.low')) return '#fef3c7';
  if (type.includes('payment')) return '#dcfce7';
  if (type.includes('subscription')) return '#dbeafe';
  return '#e0e7ff';
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<style scoped>
/* ── Hero banner ── */
.dp-hero {
  position: relative;
  display: flex;
  align-items: stretch;
  border-radius: 0.75rem;
  overflow: hidden;
  border: 1px solid #f0ede8;
}

.dp-hero-accent {
  width: 4px;
  flex-shrink: 0;
}

.dp-hero-content {
  padding: 1.25rem 1.5rem;
}

.dp-hero-greeting {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #9ca3af;
  margin-bottom: 0.25rem;
}

.dp-hero-name {
  font-size: 1.875rem;
  font-weight: 900;
  color: #2a2522;
  line-height: 1.1;
  letter-spacing: -0.03em;
}

.dp-hero-tenant {
  font-size: 0.8125rem;
  color: #6b6560;
  margin-top: 0.25rem;
}

/* ── Action cards ── */
.dp-action-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.625rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #ffffff;
}

.dp-action-icon svg {
  width: 1.25rem;
  height: 1.25rem;
}

/* ── Notification icon circles ── */
.dp-notif-icon {
  width: 2rem;
  height: 2rem;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.dp-notif-icon svg {
  width: 1rem;
  height: 1rem;
  color: #374151;
}
</style>
