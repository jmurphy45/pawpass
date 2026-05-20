<template>
  <AdminLayout>
    <div class="space-y-5 max-w-3xl">

      <!-- Header -->
      <div class="flex items-center justify-between gap-3">
        <div>
          <Link :href="route('admin.vet.appointments.index')" class="text-sm text-indigo-600 hover:underline mb-1 inline-block">← Vet Appointments</Link>
          <h1 class="text-2xl font-bold text-text-body">
            Vet Appointment — {{ props.appointment.dog?.name ?? 'Unknown Dog' }}
          </h1>
          <p class="text-sm text-text-muted mt-0.5">
            {{ formatDate(props.appointment.starts_at) }}
          </p>
        </div>
        <AppBadge :variant="statusVariant(props.appointment.status)" class="text-base px-3 py-1">
          {{ props.appointment.status }}
        </AppBadge>
      </div>

      <!-- Flash -->
      <AppAlert v-if="$page.props.flash?.success" variant="success">{{ $page.props.flash.success }}</AppAlert>

      <!-- Detail card -->
      <AppCard>
        <template #header>Appointment Details</template>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
          <div>
            <dt class="text-text-muted font-medium">Dog</dt>
            <dd class="mt-0.5 text-text-body">{{ props.appointment.dog?.name ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-text-muted font-medium">Customer</dt>
            <dd class="mt-0.5 text-text-body">{{ customerName(props.appointment.customer) }}</dd>
          </div>
          <div>
            <dt class="text-text-muted font-medium">Date &amp; Time</dt>
            <dd class="mt-0.5 text-text-body">{{ formatDate(props.appointment.starts_at) }}</dd>
          </div>
          <div v-if="props.appointment.ends_at">
            <dt class="text-text-muted font-medium">End Time</dt>
            <dd class="mt-0.5 text-text-body">{{ formatDate(props.appointment.ends_at) }}</dd>
          </div>
          <div v-if="detail">
            <dt class="text-text-muted font-medium">Reason</dt>
            <dd class="mt-0.5 text-text-body">{{ detail.reason }}</dd>
          </div>
          <div v-if="detail?.diagnosis">
            <dt class="text-text-muted font-medium">Diagnosis</dt>
            <dd class="mt-0.5 text-text-body">{{ detail.diagnosis }}</dd>
          </div>
          <div v-if="detail">
            <dt class="text-text-muted font-medium">Fee</dt>
            <dd class="mt-0.5 text-text-body">{{ formatCents(detail.price_cents) }}</dd>
          </div>
          <div v-if="detail">
            <dt class="text-text-muted font-medium">Duration</dt>
            <dd class="mt-0.5 text-text-body">{{ detail.duration_mins }} min</dd>
          </div>
          <div v-if="detail?.resource">
            <dt class="text-text-muted font-medium">Exam Room</dt>
            <dd class="mt-0.5 text-text-body">{{ detail.resource.name }}</dd>
          </div>
          <div v-if="props.appointment.notes">
            <dt class="text-text-muted font-medium">Notes</dt>
            <dd class="mt-0.5 text-text-body">{{ props.appointment.notes }}</dd>
          </div>
          <div v-if="props.appointment.cancellation_reason">
            <dt class="text-text-muted font-medium">Cancellation Reason</dt>
            <dd class="mt-0.5 text-text-body">{{ props.appointment.cancellation_reason }}</dd>
          </div>
        </dl>
      </AppCard>

      <!-- Actions -->
      <div v-if="!isTerminal" class="flex flex-wrap gap-2">
        <form v-if="props.appointment.status === 'pending'" @submit.prevent="confirmAppt">
          <AppButton type="submit" variant="primary" size="sm" :disabled="confirmForm.processing">Confirm</AppButton>
        </form>
        <AppButton v-if="!isCancelled" variant="secondary" size="sm" @click="showEditModal = true">Edit</AppButton>
        <AppButton v-if="!isCancelled" variant="danger" size="sm" @click="showCancelModal = true">Cancel Appointment</AppButton>
      </div>

      <!-- Edit Modal -->
      <Teleport to="body">
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/40" @click="showEditModal = false" />
          <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-border-warm flex items-center justify-between">
              <h2 class="text-lg font-semibold text-text-body">Edit Appointment</h2>
              <button @click="showEditModal = false" class="text-text-muted hover:text-text-body">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <form @submit.prevent="submitEdit" class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Reason</label>
                <input v-model="editForm.reason" type="text" class="cr-input" />
                <p v-if="editForm.errors.reason" class="cr-error">{{ editForm.errors.reason }}</p>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-text-body mb-1">Date &amp; Time</label>
                  <input v-model="editForm.starts_at" type="datetime-local" class="cr-input" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-text-body mb-1">End Time</label>
                  <input v-model="editForm.ends_at" type="datetime-local" class="cr-input" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Diagnosis</label>
                <textarea v-model="editForm.diagnosis" rows="2" class="cr-input resize-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Notes</label>
                <textarea v-model="editForm.notes" rows="2" class="cr-input resize-none" />
              </div>
              <div class="flex justify-end gap-2 pt-1">
                <AppButton type="button" variant="secondary" size="sm" @click="showEditModal = false">Cancel</AppButton>
                <AppButton type="submit" variant="primary" size="sm" :disabled="editForm.processing">Save Changes</AppButton>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- Cancel Modal -->
      <Teleport to="body">
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/40" @click="showCancelModal = false" />
          <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm">
            <div class="px-6 py-5 border-b border-border-warm">
              <h2 class="text-lg font-semibold text-text-body">Cancel Appointment</h2>
            </div>
            <form @submit.prevent="submitCancel" class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-sm font-medium text-text-body mb-1">Reason (optional)</label>
                <textarea v-model="cancelForm.cancellation_reason" rows="3" class="cr-input resize-none" />
              </div>
              <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" size="sm" @click="showCancelModal = false">Back</AppButton>
                <AppButton type="submit" variant="danger" size="sm" :disabled="cancelForm.processing">Cancel Appointment</AppButton>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Dog { id: string; name: string }
interface Customer { id: string; name: string }
interface Resource { id: string; name: string }
interface VetDetail {
  id: string;
  reason: string;
  diagnosis: string | null;
  price_cents: number;
  duration_mins: number;
  pims_appt_id: string | null;
  resource?: Resource | null;
}

interface Appointment {
  id: string;
  status: string;
  starts_at: string;
  ends_at: string | null;
  notes: string | null;
  cancellation_reason: string | null;
  dog?: Dog | null;
  customer?: Customer | null;
  vet_detail?: VetDetail | null;
}

interface PageProps {
  appointment: Appointment;
  [key: string]: unknown;
}

const props = defineProps<PageProps>();

const showEditModal = ref(false);
const showCancelModal = ref(false);

const detail = computed(() => props.appointment.vet_detail ?? null);
const isCancelled = computed(() => props.appointment.status === 'cancelled');
const isTerminal = computed(() => ['checked_out', 'cancelled', 'no_show'].includes(props.appointment.status));

const confirmForm = useForm({});
const editForm = useForm({
  reason: detail.value?.reason ?? '',
  starts_at: props.appointment.starts_at?.slice(0, 16) ?? '',
  ends_at: props.appointment.ends_at?.slice(0, 16) ?? '',
  diagnosis: detail.value?.diagnosis ?? '',
  notes: props.appointment.notes ?? '',
});
const cancelForm = useForm({ cancellation_reason: '' });

function confirmAppt() {
  confirmForm.post(route('admin.vet.appointments.confirm', { appointment: props.appointment.id }));
}

function submitEdit() {
  editForm.patch(route('admin.vet.appointments.update', { appointment: props.appointment.id }), {
    onSuccess: () => { showEditModal.value = false; },
  });
}

function submitCancel() {
  cancelForm.post(route('admin.vet.appointments.cancel', { appointment: props.appointment.id }), {
    onSuccess: () => { showCancelModal.value = false; },
  });
}

function customerName(c?: Customer | null) {
  return c?.name ?? '—';
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function formatCents(cents: number) {
  return `$${(cents / 100).toFixed(2)}`;
}

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
  const map: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
    confirmed: 'success', pending: 'warning', checked_in: 'info',
    checked_out: 'neutral', cancelled: 'danger', no_show: 'danger', draft: 'neutral',
  };
  return map[status] ?? 'neutral';
}
</script>
