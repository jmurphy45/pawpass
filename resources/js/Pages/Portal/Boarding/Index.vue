<template>
  <PortalLayout>
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Boarding Stays</h1>
          <p class="text-sm text-text-muted mt-0.5">Your reservations &amp; upcoming stays</p>
        </div>
        <Link :href="route('portal.boarding.create')" class="btn-primary text-sm">New Reservation</Link>
      </div>

      <!-- Status tabs -->
      <div class="rv-tabs">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          @click="setStatus(tab.value)"
          class="rv-tab"
          :class="{ 'rv-tab--active': selectedStatus === tab.value }"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="reservations.data.length === 0" class="card p-12 text-center">
        <div class="mx-auto h-14 w-14 rounded-full bg-surface-subtle flex items-center justify-center mb-3">
          <svg class="h-7 w-7 text-text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
          </svg>
        </div>
        <p class="font-semibold text-text-body">No reservations yet</p>
        <p class="text-sm text-text-muted mt-1">Book a boarding stay for your dog</p>
        <Link :href="route('portal.boarding.create')" class="btn-primary text-sm mt-4 inline-block">Book a Stay</Link>
      </div>

      <!-- List -->
      <div v-else class="card overflow-hidden">
        <ul class="rv-list">
          <li v-for="r in reservations.data" :key="r.id" class="rv-row" :style="{ '--rv-status': statusColor(r.status) }">
            <Link :href="route('portal.boarding.show', r.id)" class="rv-row-link" />

            <div class="rv-strip" />

            <div class="rv-avatar">
              {{ (r.dog?.name ?? '?')[0].toUpperCase() }}
            </div>

            <div class="rv-content">
              <div class="rv-name">{{ r.dog?.name ?? '—' }}</div>
              <div class="rv-meta">
                <span>{{ formatDate(r.starts_at) }} → {{ formatDate(r.ends_at) }}</span>
                <span v-if="r.kennel_unit" class="rv-dot">·</span>
                <span v-if="r.kennel_unit">{{ r.kennel_unit.name }}</span>
              </div>
            </div>

            <div class="rv-right">
              <span class="badge" :class="statusBadge(r.status)">{{ statusLabel(r.status) }}</span>
              <div v-if="r.nightly_rate_cents" class="rv-rate">${{ (r.nightly_rate_cents / 100).toFixed(0) }}/night</div>
            </div>

            <div class="rv-arrow">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </div>
          </li>
        </ul>
      </div>

      <!-- Pagination -->
      <div v-if="reservations.last_page > 1" class="flex gap-2 justify-center text-sm">
        <Link
          v-if="reservations.current_page > 1"
          :href="route('portal.boarding.index', { page: reservations.current_page - 1, status: selectedStatus || undefined })"
          class="btn-secondary text-xs py-1.5 px-3"
        >Previous</Link>
        <Link
          v-if="reservations.current_page < reservations.last_page"
          :href="route('portal.boarding.index', { page: reservations.current_page + 1, status: selectedStatus || undefined })"
          class="btn-secondary text-xs py-1.5 px-3"
        >Next</Link>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps<{
  reservations: {
    data: Array<{
      id: string;
      status: string;
      starts_at: string;
      ends_at: string;
      nightly_rate_cents: number | null;
      dog: { id: string; name: string } | null;
      kennel_unit: { id: string; name: string } | null;
    }>;
    current_page: number;
    last_page: number;
  };
}>();

const selectedStatus = ref('');

const statusTabs = [
  { label: 'All', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Checked In', value: 'checked_in' },
  { label: 'Checked Out', value: 'checked_out' },
  { label: 'Cancelled', value: 'cancelled' },
];

function setStatus(value: string) {
  selectedStatus.value = value;
  router.get(route('portal.boarding.index'), { status: value || undefined }, { preserveState: true });
}

function formatDate(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function statusColor(status: string): string {
  return {
    pending:     '#d97706',
    confirmed:   '#4f46e5',
    checked_in:  '#16a34a',
    checked_out: '#9ca3af',
    cancelled:   '#ef4444',
  }[status] ?? '#9ca3af';
}

function statusLabel(status: string): string {
  return {
    pending:     'Pending',
    confirmed:   'Confirmed',
    checked_in:  'Checked In',
    checked_out: 'Checked Out',
    cancelled:   'Cancelled',
  }[status] ?? status;
}

function statusBadge(status: string): string {
  return {
    pending:     'badge-yellow',
    confirmed:   'badge-blue',
    checked_in:  'badge-green',
    checked_out: 'badge-gray',
    cancelled:   'badge-red',
  }[status] ?? 'badge-gray';
}
</script>

<style scoped>
.rv-tabs {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.rv-tab {
  padding: 0.375rem 0.875rem;
  font-size: 0.8125rem;
  font-weight: 500;
  border-radius: 9999px;
  border: 1.5px solid #e5e0d8;
  background: #ffffff;
  color: #6b6560;
  cursor: pointer;
  transition: all 150ms ease;
}

.rv-tab:hover {
  border-color: #c8c3ba;
  background: #faf9f6;
  color: #2a2522;
}

.rv-tab--active {
  background: #2a2522;
  border-color: #2a2522;
  color: #ffffff;
}

.rv-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.rv-row {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f0ede8;
  transition: background 140ms ease;
}

.rv-row:last-child {
  border-bottom: none;
}

.rv-row:hover {
  background: #faf9f6;
}

.rv-row-link {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.rv-strip {
  width: 4px;
  align-self: stretch;
  border-radius: 2px;
  background: var(--rv-status);
  flex-shrink: 0;
  margin: -1rem 0;
}

.rv-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 9999px;
  background: #f0ede8;
  color: #6b6560;
  font-size: 0.875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.rv-content {
  flex: 1;
  min-width: 0;
}

.rv-name {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #2a2522;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.rv-meta {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: #6b6560;
  margin-top: 0.125rem;
  flex-wrap: wrap;
}

.rv-dot {
  color: #c8c3ba;
}

.rv-right {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
}

.rv-rate {
  font-size: 0.6875rem;
  color: #9ca3af;
}

.rv-arrow {
  width: 1rem;
  height: 1rem;
  color: #c8c3ba;
  flex-shrink: 0;
}
</style>
