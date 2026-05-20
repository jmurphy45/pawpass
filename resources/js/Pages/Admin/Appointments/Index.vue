<template>
  <AdminLayout>
    <div class="space-y-5">

      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Appointments</h1>
          <p class="text-sm text-text-muted mt-0.5">All scheduled services across your practice</p>
        </div>
      </div>

      <AppAlert v-if="$page.props.flash?.success" variant="success">{{ $page.props.flash.success }}</AppAlert>

      <!-- Service type tabs -->
      <div class="flex gap-1 border-b border-border-warm">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
          :class="activeTab === tab.value
            ? 'border-indigo-600 text-indigo-600'
            : 'border-transparent text-text-muted hover:text-text-body'"
          @click="setTab(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3 items-center">
        <select v-model="filters.status" @change="applyFilters" class="cr-input w-40">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="checked_in">Checked In</option>
          <option value="checked_out">Checked Out</option>
          <option value="no_show">No Show</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input v-model="filters.date" type="date" @change="applyFilters" class="cr-input w-40" />
        <button v-if="filters.status || filters.date" @click="clearFilters" class="text-sm text-indigo-600 hover:underline">Clear</button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-border-warm overflow-hidden">
        <table class="min-w-full divide-y divide-border-warm">
          <thead class="bg-surface-muted">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Dog</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Service</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Date / Time</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Resource</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border-warm">
            <tr v-if="props.appointments.data.length === 0">
              <td colspan="5" class="px-4 py-10 text-center text-sm text-text-muted">No appointments found.</td>
            </tr>
            <tr v-for="appt in props.appointments.data" :key="appt.id" class="hover:bg-surface-muted/50 transition">
              <td class="px-4 py-3 text-sm font-medium text-text-body">{{ appt.dog?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-sm text-text-muted capitalize">{{ serviceLabel(appt.service_type) }}</td>
              <td class="px-4 py-3 text-sm text-text-muted whitespace-nowrap">{{ formatDate(appt.starts_at) }}</td>
              <td class="px-4 py-3 text-sm text-text-muted">{{ appt.resource?.name ?? '—' }}</td>
              <td class="px-4 py-3">
                <AppBadge :variant="statusVariant(appt.status)">{{ appt.status }}</AppBadge>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="props.appointments.next_cursor || props.appointments.prev_cursor" class="flex justify-between text-sm">
        <Link v-if="props.appointments.prev_cursor" :href="pageUrl(props.appointments.prev_cursor)" class="text-indigo-600 hover:underline">← Previous</Link>
        <Link v-if="props.appointments.next_cursor" :href="pageUrl(props.appointments.next_cursor)" class="text-indigo-600 hover:underline ml-auto">Next →</Link>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppAlert from '@/Components/AppAlert.vue'

interface Appointment {
  id: string
  service_type: string
  status: string
  starts_at: string
  dog?: { name: string } | null
  resource?: { name: string; resource_type: string } | null
}

interface PaginatedAppointments {
  data: Appointment[]
  next_cursor: string | null
  prev_cursor: string | null
}

const props = defineProps<{
  appointments: PaginatedAppointments
}>()

const tabs = [
  { label: 'All', value: '' },
  { label: 'Vet', value: 'vet' },
  { label: 'Grooming', value: 'grooming' },
  { label: 'Daycare Booking', value: 'daycare_booking' },
  { label: 'Boarding', value: 'boarding' },
]

const activeTab = ref('')
const filters = reactive({ status: '', date: '' })

function setTab(value: string) {
  activeTab.value = value
  applyFilters()
}

function applyFilters() {
  router.get(route('admin.appointments.index'), {
    service_type: activeTab.value || undefined,
    status: filters.status || undefined,
    date: filters.date || undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  filters.status = ''
  filters.date = ''
  applyFilters()
}

function pageUrl(cursor: string) {
  return route('admin.appointments.index', {
    cursor,
    service_type: activeTab.value || undefined,
    status: filters.status || undefined,
    date: filters.date || undefined,
  })
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: 'numeric', minute: '2-digit',
  })
}

function serviceLabel(type: string) {
  const map: Record<string, string> = {
    vet: 'Vet',
    grooming: 'Grooming',
    daycare_booking: 'Daycare Booking',
    boarding: 'Boarding',
    daycare: 'Daycare',
  }
  return map[type] ?? type
}

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'default' {
  if (['confirmed', 'checked_in', 'checked_out'].includes(status)) return 'success'
  if (status === 'pending') return 'warning'
  if (['cancelled', 'no_show'].includes(status)) return 'danger'
  return 'default'
}
</script>
