<template>
  <AdminLayout>
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Boarding Reservations</h1>
          <p class="text-sm text-text-muted mt-0.5">Manage all guest stays</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input v-model="filters.from" type="date" @change="applyFilters" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition py-1.5 w-36" />
          <span class="text-text-muted text-xs">→</span>
          <input v-model="filters.to" type="date" @change="applyFilters" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition py-1.5 w-36" />
          <Link :href="route('admin.boarding.occupancy')"><AppButton variant="secondary" size="sm">Occupancy</AppButton></Link>
          <AppButton variant="primary" size="sm" @click="showCreate = true">+ New Reservation</AppButton>
        </div>
      </div>

      <!-- Create Reservation Modal -->
      <Teleport to="body">
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/40" @click="closeCreate" />
          <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-border-warm flex items-center justify-between">
              <h2 class="text-lg font-semibold text-text-body">New Reservation</h2>
              <button @click="closeCreate" class="text-text-muted hover:text-text-body transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <form @submit.prevent="submitCreate" class="px-6 py-5 space-y-4">
              <!-- Dog -->
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Dog <span class="text-red-500">*</span></label>
                <select v-model="form.dog_id" class="cr-input">
                  <option value="">Select a dog…</option>
                  <option v-for="dog in props.dogs" :key="dog.id" :value="dog.id">
                    {{ dog.name }}{{ dog.customer ? ` (${dog.customer.name})` : '' }}
                  </option>
                </select>
                <p v-if="form.errors.dog_id" class="cr-error">{{ form.errors.dog_id }}</p>
              </div>

              <!-- Dates -->
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-text-body mb-1">Check-in <span class="text-red-500">*</span></label>
                  <input v-model="form.starts_at" type="date" class="cr-input" />
                  <p v-if="form.errors.starts_at" class="cr-error">{{ form.errors.starts_at }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-text-body mb-1">Check-out <span class="text-red-500">*</span></label>
                  <input v-model="form.ends_at" type="date" class="cr-input" />
                  <p v-if="form.errors.ends_at" class="cr-error">{{ form.errors.ends_at }}</p>
                </div>
              </div>

              <!-- Kennel Unit -->
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Kennel Unit</label>
                <select v-model="form.kennel_unit_id" class="cr-input" @change="onUnitChange">
                  <option value="">None / unassigned</option>
                  <option v-for="unit in props.kennelUnits" :key="unit.id" :value="unit.id">
                    {{ unit.name }} — ${{ (unit.nightly_rate_cents / 100).toFixed(2) }}/night
                  </option>
                </select>
                <p v-if="form.errors.kennel_unit_id" class="cr-error">{{ form.errors.kennel_unit_id }}</p>
              </div>

              <!-- Nightly Rate -->
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Nightly Rate ($)</label>
                <input v-model="nightlyRateDollars" type="number" min="0" step="0.01" class="cr-input" placeholder="0.00" />
              </div>

              <!-- Notes -->
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Notes</label>
                <textarea v-model="form.notes" rows="3" class="cr-input resize-none" placeholder="Care instructions, special requests…" />
              </div>

              <!-- Skip vaccination check -->
              <label class="flex items-center gap-2 text-sm text-text-body cursor-pointer">
                <input v-model="form.ignore_vaccination_check" type="checkbox" class="rounded border-border-warm" />
                Skip vaccination compliance check
              </label>

              <!-- Actions -->
              <div class="flex justify-end gap-2 pt-1">
                <AppButton type="button" variant="secondary" size="sm" @click="closeCreate">Cancel</AppButton>
                <AppButton type="submit" variant="primary" size="sm" :disabled="form.processing">
                  {{ form.processing ? 'Creating…' : 'Create Reservation' }}
                </AppButton>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- Status tabs -->
      <div class="rv-tabs">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          @click="setStatus(tab.value)"
          class="rv-tab"
          :class="{ 'rv-tab--active': filters.status === tab.value }"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- List -->
      <AppCard class="overflow-hidden">

        <!-- Empty state -->
        <div v-if="reservations.data.length === 0" class="rv-empty">
          <div class="rv-empty-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
          </div>
          <p class="rv-empty-title">No reservations found</p>
          <p class="rv-empty-sub">Try adjusting your filters</p>
        </div>

        <!-- Rows -->
        <ul v-else class="rv-list">
          <li v-for="r in reservations.data" :key="r.id" class="rv-row" :style="{ '--rv-status': statusColor(r.status) }">
            <a :href="route('admin.boarding.reservations.show', r.id)" class="rv-row-link" />

            <!-- Status strip -->
            <div class="rv-strip" />

            <!-- Dog avatar -->
            <div class="rv-avatar">
              {{ (r.dog?.name ?? '?')[0].toUpperCase() }}
            </div>

            <!-- Main content -->
            <div class="rv-content">
              <div class="rv-name">{{ r.dog?.name ?? '—' }}</div>
              <div class="rv-meta">
                <span>{{ r.customer?.name ?? '—' }}</span>
                <span v-if="r.kennel_unit" class="rv-dot">·</span>
                <span v-if="r.kennel_unit">{{ r.kennel_unit.name }}</span>
              </div>
            </div>

            <!-- Date + nights -->
            <div class="rv-dates">
              <div class="rv-daterange">{{ formatDate(r.starts_at) }} → {{ formatDate(r.ends_at) }}</div>
              <div class="rv-nights">{{ nightCount(r.starts_at, r.ends_at) }}</div>
            </div>

            <!-- Status -->
            <div class="rv-status-col">
              <AppBadge :color="statusBadgeColor(r.status)">{{ statusLabel(r.status) }}</AppBadge>
            </div>

            <!-- Arrow -->
            <div class="rv-arrow">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </div>
          </li>
        </ul>
      </AppCard>

      <!-- Pagination -->
      <div v-if="reservations.last_page > 1" class="flex gap-2 justify-center text-sm">
        <a
          v-for="link in reservations.links"
          :key="link.label"
          v-html="link.label"
          :href="link.url ?? '#'"
          class="px-3 py-1 rounded border"
          :class="link.active ? 'bg-primary text-white border-primary' : 'border-border text-text-muted hover:bg-surface-subtle'"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { reactive, ref, computed } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  reservations: {
    data: Array<{
      id: string;
      dog: { name: string } | null;
      customer: { name: string } | null;
      kennel_unit: { name: string } | null;
      starts_at: string;
      ends_at: string;
      status: string;
    }>;
    links: Array<{ label: string; url: string | null; active: boolean }>;
    last_page: number;
  };
  filters: { status: string; from: string; to: string };
  dogs: Array<{ id: string; name: string; customer: { name: string } | null }>;
  kennelUnits: Array<{ id: string; name: string; nightly_rate_cents: number }>;
}>();

const showCreate = ref(false);

const form = useForm({
  dog_id: '',
  starts_at: '',
  ends_at: '',
  kennel_unit_id: '',
  nightly_rate_cents: 0,
  notes: '',
  ignore_vaccination_check: false,
});

const nightlyRateDollars = computed({
  get: () => (form.nightly_rate_cents / 100).toFixed(2),
  set: (val: string) => {
    const parsed = parseFloat(val);
    form.nightly_rate_cents = isNaN(parsed) || val === '' ? 0 : Math.round(parsed * 100);
  },
});

function onUnitChange() {
  const unit = props.kennelUnits.find(u => u.id === form.kennel_unit_id);
  if (unit) form.nightly_rate_cents = unit.nightly_rate_cents;
}

function closeCreate() {
  showCreate.value = false;
  form.reset();
  form.clearErrors();
}

function submitCreate() {
  form.post(route('admin.boarding.reservations.store'));
}

const filters = reactive({ ...props.filters });

const statusTabs = [
  { label: 'All', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Checked In', value: 'checked_in' },
  { label: 'Checked Out', value: 'checked_out' },
  { label: 'Cancelled', value: 'cancelled' },
];

function setStatus(value: string) {
  filters.status = value;
  applyFilters();
}

function applyFilters() {
  router.get(route('admin.boarding.reservations'), filters, { preserveState: true, replace: true });
}

function formatDate(iso: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function nightCount(start: string, end: string): string {
  const diff = Math.round((new Date(end).getTime() - new Date(start).getTime()) / 86400000);
  return diff === 1 ? '1 night' : `${diff} nights`;
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

function statusBadgeColor(status: string): string {
  return {
    pending:     'yellow',
    confirmed:   'blue',
    checked_in:  'green',
    checked_out: 'gray',
    cancelled:   'red',
  }[status] ?? 'gray';
}
</script>

<style scoped>
/* ── Status tabs ── */
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

/* ── List ── */
.rv-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

/* ── Row ── */
.rv-row {
  position: relative;
  display: flex;
  align-items: center;
  gap: 1rem;
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

/* full-row clickable overlay */
.rv-row-link {
  position: absolute;
  inset: 0;
  z-index: 1;
}

/* ── Status strip ── */
.rv-strip {
  width: 4px;
  align-self: stretch;
  border-radius: 2px;
  background: var(--rv-status);
  flex-shrink: 0;
  margin: -1rem 0;
}

/* ── Avatar ── */
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

/* ── Content ── */
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
}

.rv-dot {
  color: #c8c3ba;
}

/* ── Dates ── */
.rv-dates {
  flex-shrink: 0;
  text-align: right;
  display: none;
}

@media (min-width: 640px) {
  .rv-dates { display: block; }
}

.rv-daterange {
  font-size: 0.8125rem;
  color: #2a2522;
  white-space: nowrap;
}

.rv-nights {
  font-size: 0.6875rem;
  color: #9ca3af;
  margin-top: 0.125rem;
  text-align: right;
}

/* ── Status col ── */
.rv-status-col {
  flex-shrink: 0;
}

/* ── Arrow ── */
.rv-arrow {
  width: 1rem;
  height: 1rem;
  color: #c8c3ba;
  flex-shrink: 0;
  position: relative;
  z-index: 0;
}

/* ── Create form inputs ── */
.cr-input {
  display: block;
  width: 100%;
  border-radius: 0.5rem;
  border: 1.5px solid #e5e0d8;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: #2a2522;
  background: #ffffff;
  outline: none;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}

.cr-input:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgb(99 102 241 / 0.12);
}

.cr-error {
  font-size: 0.75rem;
  color: #ef4444;
  margin-top: 0.25rem;
}

/* ── Empty state ── */
.rv-empty {
  padding: 4rem 1rem;
  text-align: center;
}

.rv-empty-icon {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 9999px;
  background: #f0ede8;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.875rem;
}

.rv-empty-icon svg {
  width: 1.75rem;
  height: 1.75rem;
  color: #9ca3af;
}

.rv-empty-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #2a2522;
}

.rv-empty-sub {
  font-size: 0.8125rem;
  color: #6b6560;
  margin-top: 0.25rem;
}
</style>
