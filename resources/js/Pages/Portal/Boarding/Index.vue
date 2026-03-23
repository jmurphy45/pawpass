<template>
  <PortalLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-text-body">Boarding Reservations</h1>
        <Link :href="route('portal.boarding.create')" class="btn-primary text-sm">New Reservation</Link>
      </div>

      <!-- Status filter -->
      <div class="card p-4 flex flex-wrap gap-3">
        <select v-model="selectedStatus" @change="applyFilter" class="input text-sm py-1.5">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="checked_in">Checked in</option>
          <option value="checked_out">Checked out</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <!-- Empty state -->
      <div v-if="reservations.data.length === 0" class="card p-12 text-center">
        <p class="text-4xl mb-3">🏠</p>
        <p class="font-semibold text-text-body">No reservations yet</p>
        <p class="text-sm text-text-muted mt-1">Book a boarding stay for your dog below</p>
        <Link :href="route('portal.boarding.create')" class="btn-primary text-sm mt-4 inline-block">Book a Stay</Link>
      </div>

      <div v-else class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-surface-subtle border-b border-border">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Dog</th>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Unit</th>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Dates</th>
              <th class="px-4 py-3 text-left font-medium text-text-muted">Status</th>
              <th class="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in reservations.data"
              :key="r.id"
              class="border-b border-border last:border-0 hover:bg-surface-subtle/50"
            >
              <td class="px-4 py-3 font-medium text-text-body">{{ r.dog?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-text-muted">{{ r.kennel_unit?.name ?? 'Unassigned' }}</td>
              <td class="px-4 py-3 text-text-muted whitespace-nowrap">{{ formatDate(r.starts_at) }} → {{ formatDate(r.ends_at) }}</td>
              <td class="px-4 py-3">
                <span class="badge" :class="statusBadge(r.status)">{{ r.status }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('portal.boarding.show', r.id)" class="text-primary text-xs hover:underline">View</Link>
              </td>
            </tr>
          </tbody>
        </table>
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

function applyFilter() {
  router.get(route('portal.boarding.index'), { status: selectedStatus.value || undefined }, { preserveState: true });
}

function formatDate(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function statusBadge(status: string): string {
  return {
    pending: 'badge-yellow',
    confirmed: 'badge-blue',
    checked_in: 'badge-green',
    checked_out: 'badge-gray',
    cancelled: 'badge-red',
  }[status] ?? 'badge-gray';
}
</script>
