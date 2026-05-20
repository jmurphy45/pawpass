<template>
  <AdminLayout>
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Grooming Appointments</h1>
          <p class="text-sm text-text-muted mt-0.5">Schedule and manage grooming sessions</p>
        </div>
        <AppButton variant="primary" size="sm" @click="showCreate = true">+ New Appointment</AppButton>
      </div>

      <!-- Flash -->
      <AppAlert v-if="$page.props.flash?.success" variant="success">{{ $page.props.flash.success }}</AppAlert>

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
        <input v-model="filters.date" type="date" @change="applyFilters" class="cr-input w-40" placeholder="Filter by date" />
        <button v-if="filters.status || filters.date" @click="clearFilters" class="text-sm text-indigo-600 hover:underline">Clear</button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-border-warm overflow-hidden">
        <table class="min-w-full divide-y divide-border-warm">
          <thead class="bg-surface-muted">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Dog</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Customer</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Service</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Date / Time</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Fee</th>
              <th class="relative px-4 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border-warm">
            <tr v-if="props.appointments.data.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-text-muted">No appointments found.</td>
            </tr>
            <tr v-for="appt in props.appointments.data" :key="appt.id" class="hover:bg-surface-muted/50 transition">
              <td class="px-4 py-3 text-sm font-medium text-text-body">{{ appt.dog?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-sm text-text-muted">{{ customerName(appt.customer) }}</td>
              <td class="px-4 py-3 text-sm text-text-muted max-w-48 truncate">{{ appt.grooming_detail?.service_name ?? '—' }}</td>
              <td class="px-4 py-3 text-sm text-text-muted whitespace-nowrap">{{ formatDate(appt.starts_at) }}</td>
              <td class="px-4 py-3">
                <AppBadge :variant="statusVariant(appt.status)">{{ appt.status }}</AppBadge>
              </td>
              <td class="px-4 py-3 text-sm text-text-muted">{{ appt.grooming_detail ? formatCents(appt.grooming_detail.price_cents) : '—' }}</td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('admin.grooming.appointments.show', { appointment: appt.id })" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">View</Link>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="props.appointments.last_page > 1" class="px-4 py-3 border-t border-border-warm flex items-center justify-between">
          <p class="text-sm text-text-muted">
            Showing {{ props.appointments.from }}–{{ props.appointments.to }} of {{ props.appointments.total }}
          </p>
          <div class="flex gap-2">
            <Link v-if="props.appointments.prev_page_url" :href="props.appointments.prev_page_url" class="text-sm text-indigo-600 hover:underline">Previous</Link>
            <Link v-if="props.appointments.next_page_url" :href="props.appointments.next_page_url" class="text-sm text-indigo-600 hover:underline">Next</Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="showCreate = false" />
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-5 border-b border-border-warm flex items-center justify-between">
            <h2 class="text-lg font-semibold text-text-body">New Grooming Appointment</h2>
            <button @click="showCreate = false" class="text-text-muted hover:text-text-body transition">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <form @submit.prevent="submitCreate" class="px-6 py-5 space-y-4">
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

            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Customer <span class="text-red-500">*</span></label>
              <select v-model="form.customer_id" class="cr-input">
                <option value="">Select a customer…</option>
                <option v-for="c in props.customers" :key="c.id" :value="c.id">
                  {{ c.name }}
                </option>
              </select>
              <p v-if="form.errors.customer_id" class="cr-error">{{ form.errors.customer_id }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Service <span class="text-red-500">*</span></label>
              <input v-model="form.service_name" type="text" class="cr-input" placeholder="Bath & Brush, Full Groom, Nail Trim…" />
              <p v-if="form.errors.service_name" class="cr-error">{{ form.errors.service_name }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Date &amp; Time <span class="text-red-500">*</span></label>
                <input v-model="form.starts_at" type="datetime-local" class="cr-input" />
                <p v-if="form.errors.starts_at" class="cr-error">{{ form.errors.starts_at }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Duration (mins)</label>
                <input v-model.number="form.duration_mins" type="number" min="1" class="cr-input" placeholder="60" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Fee ($) <span class="text-red-500">*</span></label>
              <input v-model="feeDollars" type="number" min="0" step="0.01" class="cr-input" placeholder="0.00" />
              <p v-if="form.errors.price_cents" class="cr-error">{{ form.errors.price_cents }}</p>
            </div>

            <div v-if="props.resources.length > 0">
              <label class="block text-sm font-medium text-text-body mb-1">Grooming Bay</label>
              <select v-model="form.resource_id" class="cr-input">
                <option value="">None</option>
                <option v-for="r in props.resources" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
              <p v-if="form.errors.resource_id" class="cr-error">{{ form.errors.resource_id }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Notes</label>
              <textarea v-model="form.notes" rows="2" class="cr-input resize-none" />
            </div>

            <div class="flex justify-end gap-2 pt-1">
              <AppButton type="button" variant="secondary" size="sm" @click="showCreate = false">Cancel</AppButton>
              <AppButton type="submit" variant="primary" size="sm" :disabled="form.processing">
                {{ form.processing ? 'Creating…' : 'Create Appointment' }}
              </AppButton>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Dog {
  id: string;
  name: string;
  customer_id?: string | null;
  customer?: { id: string; name: string } | null;
}

interface Customer {
  id: string;
  name: string;
}

interface Resource {
  id: string;
  name: string;
  resource_type: string;
  capacity: number;
}

interface GroomingDetail {
  service_name: string;
  price_cents: number;
  duration_mins: number;
}

interface Appointment {
  id: string;
  status: string;
  starts_at: string;
  ends_at: string | null;
  price_cents: number | null;
  dog?: Dog | null;
  customer?: Customer | null;
  grooming_detail?: GroomingDetail | null;
}

interface PageProps {
  appointments: {
    data: Appointment[];
    from: number;
    to: number;
    total: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  dogs: Dog[];
  customers: Customer[];
  resources: Resource[];
  filters: { status?: string; date?: string };
  [key: string]: unknown;
}

const props = defineProps<PageProps>();

const showCreate = ref(false);
const filters = ref({ status: props.filters.status ?? '', date: props.filters.date ?? '' });

const form = useForm({
  dog_id: '',
  customer_id: '',
  service_name: '',
  starts_at: '',
  ends_at: '',
  price_cents: 0,
  duration_mins: 60,
  resource_id: '',
  notes: '',
});

const feeDollars = computed({
  get: () => (form.price_cents / 100).toFixed(2),
  set: (v: string) => { form.price_cents = Math.round(parseFloat(v || '0') * 100); },
});

function applyFilters() {
  router.get(route('admin.grooming.appointments.index'), { ...filters.value }, { preserveState: true, replace: true });
}

function clearFilters() {
  filters.value = { status: '', date: '' };
  applyFilters();
}

function submitCreate() {
  form.post(route('admin.grooming.appointments.store'), {
    onSuccess: () => { showCreate.value = false; form.reset(); },
  });
}

function customerName(c?: Customer | null) {
  return c?.name ?? '—';
}

watch(() => form.dog_id, (dogId) => {
  const dog = props.dogs.find(d => d.id === dogId);
  if (dog?.customer_id) form.customer_id = dog.customer_id;
});

watch([() => form.starts_at, () => form.duration_mins], ([start, dur]) => {
  if (start && dur) {
    const d = new Date(start);
    d.setMinutes(d.getMinutes() + (dur as number));
    form.ends_at = d.toISOString().slice(0, 16);
  }
});

function formatDate(iso: string) {
  return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function formatCents(cents: number) {
  return `$${(cents / 100).toFixed(2)}`;
}

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
  const map: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
    confirmed: 'success',
    pending: 'warning',
    checked_in: 'info',
    checked_out: 'neutral',
    cancelled: 'danger',
    no_show: 'danger',
    draft: 'neutral',
  };
  return map[status] ?? 'neutral';
}
</script>
