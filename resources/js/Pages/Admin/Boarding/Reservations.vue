<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <h1 class="text-2xl font-bold text-text-body">Boarding Reservations</h1>
        <a :href="route('admin.boarding.occupancy')" class="btn-secondary text-sm self-start sm:self-auto">Occupancy View</a>
      </div>

      <!-- Filters -->
      <div class="card p-4 flex flex-wrap gap-3">
        <select v-model="filters.status" @change="applyFilters" class="input text-sm py-1.5">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="checked_in">Checked in</option>
          <option value="checked_out">Checked out</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input v-model="filters.from" type="date" @change="applyFilters" class="input text-sm py-1.5" placeholder="From" />
        <input v-model="filters.to" type="date" @change="applyFilters" class="input text-sm py-1.5" placeholder="To" />
      </div>

      <!-- Table -->
      <div class="card overflow-hidden">
        <div v-if="reservations.data.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No reservations found.
        </div>
        <table v-else class="w-full text-sm">
          <thead class="bg-surface-subtle border-b border-border">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Dog</th>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Customer</th>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Unit</th>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Dates</th>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Status</th>
              <th class="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reservations.data" :key="r.id" class="border-b border-border last:border-0 hover:bg-surface-subtle/50">
              <td class="px-4 py-3 font-medium text-text-body">{{ r.dog?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-text-muted">{{ r.customer?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-text-muted">{{ r.kennel_unit?.name ?? 'Unassigned' }}</td>
              <td class="px-4 py-3 text-text-muted whitespace-nowrap">{{ formatDate(r.starts_at) }} → {{ formatDate(r.ends_at) }}</td>
              <td class="px-4 py-3">
                <span class="badge" :class="statusBadge(r.status)">{{ r.status }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <a :href="route('admin.boarding.reservations.show', r.id)" class="text-primary text-xs hover:underline">View</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="reservations.last_page > 1" class="flex gap-2 justify-center text-sm">
        <a v-for="link in reservations.links" :key="link.label" v-html="link.label"
          :href="link.url ?? '#'"
          class="px-3 py-1 rounded border"
          :class="link.active ? 'bg-primary text-white border-primary' : 'border-border text-text-muted hover:bg-surface-subtle'"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
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
}>();

const filters = reactive({ ...props.filters });

function applyFilters() {
  router.get(route('admin.boarding.reservations'), filters, { preserveState: true, replace: true });
}

function formatDate(iso: string) {
  return iso ? iso.slice(0, 10) : '—';
}

function statusBadge(status: string) {
  return {
    'badge-yellow': status === 'pending',
    'badge-blue': status === 'confirmed',
    'badge-green': status === 'checked_in',
    'badge-gray': status === 'checked_out' || status === 'cancelled',
    'badge-red': status === 'cancelled',
  };
}
</script>
