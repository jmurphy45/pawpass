<template>
  <PortalLayout>
    <div class="space-y-8">

      <!-- ── Hero Banner ── -->
      <div
        class="dp-hero relative overflow-hidden rounded-2xl border border-border-warm"
        :style="{ background: `linear-gradient(155deg, ${accentColor}13 0%, #faf9f6 55%)` }"
      >
        <!-- Ambient blobs -->
        <div class="dp-blob-lg" :style="{ background: accentColor }" />
        <div class="dp-blob-sm" :style="{ background: accentColor }" />

        <div class="relative z-10 px-6 py-8 sm:px-10 sm:py-10">
          <p class="dp-eyebrow">{{ tenantName }}<span v-if="tenantName"> &nbsp;·&nbsp; </span>{{ todayLabel }}</p>
          <h1 class="dp-headline">{{ greeting }}, {{ firstName }}.</h1>
          <p v-if="dogs.length > 0" class="dp-subline">
            {{ totalCredits }} credit{{ totalCredits !== 1 ? 's' : '' }} across
            {{ dogs.length }} dog{{ dogs.length !== 1 ? 's' : '' }}
          </p>
          <p v-else class="dp-subline">Add your dogs to start tracking daycare credits.</p>
        </div>
      </div>

      <!-- ── My Dogs ── -->
      <section>
        <div class="flex items-center justify-between mb-5">
          <h2 class="dp-section-label">My Dogs</h2>
          <Link :href="route('portal.dogs.index')" class="dp-section-link">Manage →</Link>
        </div>

        <!-- Empty state -->
        <div v-if="dogs.length === 0" class="dp-empty">
          <svg width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" class="dp-empty-paw">
            <ellipse cx="36" cy="44" rx="16" ry="13" fill="currentColor"/>
            <ellipse cx="21" cy="31" rx="7" ry="9" transform="rotate(-12 21 31)" fill="currentColor" opacity="0.6"/>
            <ellipse cx="51" cy="31" rx="7" ry="9" transform="rotate(12 51 31)" fill="currentColor" opacity="0.6"/>
            <ellipse cx="28" cy="22" rx="5" ry="7" fill="currentColor" opacity="0.4"/>
            <ellipse cx="44" cy="22" rx="5" ry="7" fill="currentColor" opacity="0.4"/>
          </svg>
          <h3 class="text-xl font-bold text-text-body mt-5 tracking-tight">Your pups will love it here</h3>
          <p class="text-sm text-text-muted mt-2 max-w-xs mx-auto leading-relaxed">
            Add your first dog to start tracking daycare credits and visit history.
          </p>
          <Link :href="route('portal.dogs.create')">
            <AppButton variant="primary" class="mt-5">Add a Dog</AppButton>
          </Link>
        </div>

        <!-- Dog grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="(dog, i) in dogs"
            :key="dog.id"
            class="dp-dog-card"
            :style="{ animationDelay: `${i * 75}ms` }"
          >
            <!-- Circular gauge -->
            <div class="dp-gauge-wrap">
              <svg viewBox="0 0 120 65" class="dp-gauge-svg" aria-hidden="true">
                <!-- Track -->
                <path
                  d="M 10,60 A 50,50 0 0,1 110,60"
                  fill="none"
                  stroke="#e5e0d8"
                  stroke-width="9"
                  stroke-linecap="round"
                />
                <!-- Progress fill -->
                <path
                  d="M 10,60 A 50,50 0 0,1 110,60"
                  fill="none"
                  :stroke="gaugeColor(dog.credit_status)"
                  stroke-width="9"
                  stroke-linecap="round"
                  :stroke-dasharray="GAUGE_LEN"
                  :stroke-dashoffset="mounted ? gaugeOffset(dog.credit_balance) : GAUGE_LEN"
                  class="dp-gauge-fill"
                />
              </svg>
              <!-- Credit count overlay -->
              <div class="dp-gauge-num">
                <span class="dp-gauge-count">{{ dog.credit_balance }}</span>
                <span class="dp-gauge-unit">credits</span>
              </div>
            </div>

            <!-- Dog info -->
            <div class="px-5 pb-5 text-center">
              <p class="font-semibold text-text-body text-[15px] tracking-tight">{{ dog.name }}</p>
              <p v-if="dog.breed" class="text-xs text-text-muted mt-0.5">{{ dog.breed }}</p>

              <span class="dp-status-badge mt-3 inline-flex items-center gap-1.5" :class="statusBadgeClass(dog.credit_status)">
                <span class="dp-status-dot" :class="statusDotClass(dog.credit_status)" />
                {{ statusLabel(dog.credit_status) }}
              </span>

              <Link
                v-if="dog.credit_status !== 'ok'"
                :href="route('portal.purchase')"
                class="dp-topup mt-3 block"
              >Top up credits →</Link>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Quick Actions ── -->
      <section>
        <h2 class="dp-section-label mb-5">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

          <Link :href="route('portal.purchase')" class="dp-action-tile">
            <div class="dp-action-icon" style="background: #fff5e0;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#d97706" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
              </svg>
            </div>
            <p class="dp-action-label">Buy Credits</p>
            <p class="dp-action-sub">Top up your balance</p>
          </Link>

          <Link :href="route('portal.attendance')" class="dp-action-tile">
            <div class="dp-action-icon" style="background: #edf7ee;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#16a34a" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
              </svg>
            </div>
            <p class="dp-action-label">Attendance</p>
            <p class="dp-action-sub">View visit history</p>
          </Link>

          <Link :href="route('portal.history')" class="dp-action-tile">
            <div class="dp-action-icon" style="background: #f3efff;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#7c3aed" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
              </svg>
            </div>
            <p class="dp-action-label">Invoices</p>
            <p class="dp-action-sub">Purchase history</p>
          </Link>

          <Link v-if="showBoarding" :href="route('portal.boarding.index')" class="dp-action-tile">
            <div class="dp-action-icon" style="background: #e8f7f5;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#0d9488" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
              </svg>
            </div>
            <p class="dp-action-label">Boarding</p>
            <p class="dp-action-sub">Manage stays</p>
          </Link>

          <Link v-if="!showBoarding" :href="route('portal.dogs.index')" class="dp-action-tile">
            <div class="dp-action-icon" style="background: #fef1ec;">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#ea580c" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
              </svg>
            </div>
            <p class="dp-action-label">My Dogs</p>
            <p class="dp-action-sub">Manage your pups</p>
          </Link>

        </div>
      </section>

      <!-- ── Recent Activity ── -->
      <section v-if="recentNotifications.length > 0">
        <div class="flex items-center justify-between mb-5">
          <h2 class="dp-section-label">Recent Activity</h2>
          <Link :href="route('portal.notifications')" class="dp-section-link">View all →</Link>
        </div>

        <AppCard class="overflow-hidden px-5 pt-4 pb-2">
          <div class="dp-timeline">
            <div
              v-for="(n, i) in recentNotifications"
              :key="n.id"
              class="dp-tl-row"
            >
              <!-- Track column -->
              <div class="dp-tl-track">
                <span class="dp-tl-dot" :class="notifDotClass(n.type ?? '')" />
                <span v-if="i < recentNotifications.length - 1" class="dp-tl-line" />
              </div>

              <!-- Content column -->
              <div class="dp-tl-body" :class="{ 'pb-5': i < recentNotifications.length - 1, 'pb-2': i === recentNotifications.length - 1 }">
                <!-- Icon -->
                <div class="dp-tl-icon" :style="{ background: notifBg(n.type ?? '') }">
                  <svg v-if="(n.type ?? '').includes('credits.empty')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                  </svg>
                  <svg v-else-if="(n.type ?? '').includes('credits.low')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                  </svg>
                  <svg v-else-if="(n.type ?? '').includes('payment')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                  <svg v-else-if="(n.type ?? '').includes('subscription')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                  </svg>
                  <svg v-else fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                  </svg>
                </div>

                <!-- Text -->
                <div class="flex-1 min-w-0">
                  <p
                    class="text-sm leading-snug"
                    :class="n.read_at ? 'text-text-muted' : 'text-text-body font-medium'"
                  >{{ n.subject ?? n.body ?? formatType(n.type ?? '') }}</p>
                  <div class="flex items-center gap-2 mt-1">
                    <p class="text-xs text-text-muted">{{ relativeTime(n.created_at) }}</p>
                    <span v-if="!n.read_at" class="inline-block h-1.5 w-1.5 rounded-full bg-amber-400 flex-shrink-0" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </AppCard>
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
  subject: string | null;
  body: string | null;
  read_at: string | null;
  created_at: string;
}

const props = defineProps<{
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

const todayLabel = computed(() =>
  new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })
);

const totalCredits = computed(() =>
  props.dogs.reduce((sum, d) => sum + d.credit_balance, 0)
);

// SVG arc gauge: M 10,60 A 50,50 0 0,1 110,60  (180° semicircle, r=50)
const GAUGE_LEN = Math.PI * 50; // ≈ 157.08

function gaugeOffset(balance: number): number {
  const pct = Math.min(1, Math.max(0, balance / 20));
  return GAUGE_LEN * (1 - pct);
}

function gaugeColor(status: string): string {
  return ({ ok: '#22c55e', low: '#f59e0b', empty: '#f43f5e' } as Record<string, string>)[status] ?? '#9ca3af';
}

const mounted = ref(false);
onMounted(() => { mounted.value = true; });

function statusBadgeClass(status: string): string {
  return ({ ok: 'dp-badge-ok', low: 'dp-badge-low', empty: 'dp-badge-empty' } as Record<string, string>)[status] ?? '';
}

function statusDotClass(status: string): string {
  return ({ ok: 'bg-green-500', low: 'bg-amber-400', empty: 'bg-rose-500' } as Record<string, string>)[status] ?? 'bg-gray-400';
}

function statusLabel(status: string): string {
  return ({ ok: 'Good standing', low: 'Low credits', empty: 'No credits' } as Record<string, string>)[status] ?? status;
}

function notifBg(type: string): string {
  if (type.includes('credits.empty')) return '#fee2e2';
  if (type.includes('credits.low')) return '#fef3c7';
  if (type.includes('payment')) return '#dcfce7';
  if (type.includes('subscription')) return '#dbeafe';
  return '#e0e7ff';
}

function notifDotClass(type: string): string {
  if (type.includes('credits.empty')) return 'bg-rose-400';
  if (type.includes('credits.low')) return 'bg-amber-400';
  if (type.includes('payment')) return 'bg-green-400';
  if (type.includes('subscription')) return 'bg-blue-400';
  return 'bg-indigo-300';
}

function formatType(type: string): string {
  return type.split('.').pop()?.replace(/_/g, ' ') ?? type;
}

function relativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  const h = Math.floor(m / 60);
  const d = Math.floor(h / 24);
  if (m < 2) return 'just now';
  if (m < 60) return `${m}m ago`;
  if (h < 24) return `${h}h ago`;
  if (d < 7) return `${d}d ago`;
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>

<style scoped>
/* ── Hero ── */
.dp-blob-lg {
  position: absolute;
  top: -60px;
  right: -60px;
  width: 240px;
  height: 240px;
  border-radius: 9999px;
  opacity: 0.09;
  pointer-events: none;
  filter: blur(50px);
}

.dp-blob-sm {
  position: absolute;
  bottom: -30px;
  right: 100px;
  width: 120px;
  height: 120px;
  border-radius: 9999px;
  opacity: 0.07;
  pointer-events: none;
  filter: blur(30px);
}

.dp-eyebrow {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #9a9087;
  margin-bottom: 0.5rem;
}

.dp-headline {
  font-size: clamp(2rem, 5vw, 3rem);
  font-weight: 800;
  color: #2a2522;
  line-height: 1.05;
  letter-spacing: -0.04em;
}

.dp-subline {
  font-size: 0.9375rem;
  color: #6b6560;
  margin-top: 0.625rem;
}

/* ── Section headers ── */
.dp-section-label {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #9a9087;
}

.dp-section-link {
  font-size: 0.8125rem;
  color: #6b6560;
  transition: color 0.15s;
}
.dp-section-link:hover { color: #2a2522; }

/* ── Empty state ── */
.dp-empty {
  text-align: center;
  padding: 3.5rem 1.5rem;
  background: #ffffff;
  border: 1px solid #e5e0d8;
  border-radius: 1rem;
}

.dp-empty-paw { color: #d4cfc8; display: inline-block; }

/* ── Dog cards ── */
@keyframes slideUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

.dp-dog-card {
  background: #ffffff;
  border: 1px solid #e5e0d8;
  border-radius: 1rem;
  overflow: hidden;
  padding-top: 1.5rem;
  animation: slideUp 0.45s ease-out both;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dp-dog-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.05);
}

/* ── Arc gauge ── */
.dp-gauge-wrap {
  position: relative;
  width: 140px;
  margin: 0 auto;
}

.dp-gauge-svg {
  width: 100%;
  height: auto;
  display: block;
}

.dp-gauge-fill {
  transition: stroke-dashoffset 0.9s cubic-bezier(0.34, 1.1, 0.64, 1);
}

/* Overlay sits at bottom of svg wrapper (arc endpoints are near bottom of viewBox) */
.dp-gauge-num {
  position: absolute;
  bottom: 2px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1;
  white-space: nowrap;
}

.dp-gauge-count {
  font-size: 1.875rem;
  font-weight: 800;
  color: #2a2522;
  letter-spacing: -0.04em;
  line-height: 1;
}

.dp-gauge-unit {
  font-size: 0.5625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #b0a99f;
  margin-top: 2px;
}

/* ── Status badge ── */
.dp-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.dp-status-dot {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 9999px;
  flex-shrink: 0;
}

.dp-badge-ok    { background: #f0fdf4; color: #15803d; }
.dp-badge-low   { background: #fffbeb; color: #b45309; }
.dp-badge-empty { background: #fff1f2; color: #be123c; }

/* ── Top-up link ── */
.dp-topup {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #6b6560;
  transition: color 0.15s;
}
.dp-topup:hover { color: #2a2522; }

/* ── Quick action tiles ── */
.dp-action-tile {
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #e5e0d8;
  border-radius: 1rem;
  padding: 1.25rem;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.dp-action-tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.07), 0 2px 6px rgba(0,0,0,0.04);
  border-color: #cfc9c1;
}

.dp-action-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-bottom: 0.875rem;
}

.dp-action-icon svg {
  width: 1.375rem;
  height: 1.375rem;
}

.dp-action-label {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #2a2522;
  line-height: 1.2;
}

.dp-action-sub {
  font-size: 0.75rem;
  color: #6b6560;
  margin-top: 0.25rem;
}

/* ── Timeline notifications ── */
.dp-timeline { padding-bottom: 0.25rem; }

.dp-tl-row {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.dp-tl-track {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 1.25rem;
  padding-top: 0.3125rem;
}

.dp-tl-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 9999px;
  flex-shrink: 0;
}

.dp-tl-line {
  width: 1px;
  flex: 1;
  min-height: 1.5rem;
  background: #e5e0d8;
  margin-top: 0.375rem;
}

.dp-tl-body {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  flex: 1;
  padding-top: 0.0625rem;
}

.dp-tl-icon {
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dp-tl-icon svg {
  width: 0.9375rem;
  height: 0.9375rem;
  color: #374151;
}
</style>
