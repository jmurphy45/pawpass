<template>
  <PortalLayout>
    <div class="space-y-5 max-w-2xl">

      <!-- Header -->
      <div class="flex items-center gap-3 flex-wrap">
        <Link :href="route('portal.boarding.index')" class="text-text-muted hover:text-text-body text-sm">← Back</Link>
        <h1 class="text-2xl font-bold text-text-body">
          {{ reservation.dog?.name ?? 'Reservation' }}
        </h1>
        <AppBadge :color="statusBadgeColor(reservation.status)">{{ statusLabel(reservation.status) }}</AppBadge>
      </div>

      <!-- Stay details -->
      <AppCard class="overflow-hidden" :style="{ borderTop: `3px solid ${statusColor(reservation.status)}` }">
        <div class="rs-card-head">
          <div class="rs-card-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
          </div>
          <span class="rs-card-title">Stay Details</span>
        </div>
        <dl class="rs-dl">
          <div class="rs-dl-row">
            <dt>Unit</dt>
            <dd>{{ reservation.kennel_unit?.name ?? 'To be assigned' }}</dd>
          </div>
          <div class="rs-dl-row">
            <dt>Check-in</dt>
            <dd>{{ formatDate(reservation.starts_at) }}</dd>
          </div>
          <div class="rs-dl-row">
            <dt>Check-out</dt>
            <dd>{{ formatDate(reservation.ends_at) }}</dd>
          </div>
          <div v-if="reservation.nightly_rate_cents" class="rs-dl-row">
            <dt>Nightly rate</dt>
            <dd>${{ (reservation.nightly_rate_cents / 100).toFixed(2) }}</dd>
          </div>
        </dl>
      </AppCard>

      <!-- Care instructions -->
      <AppCard v-if="hasCareInstructions" class="overflow-hidden">
        <div class="rs-card-head">
          <div class="rs-card-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>
          </div>
          <span class="rs-card-title">Care Instructions</span>
        </div>
        <div class="rs-care">
          <div v-for="field in careFields" :key="field.key" v-show="(reservation as any)[field.key]" class="rs-care-item">
            <p class="rs-care-label">{{ field.label }}</p>
            <p class="rs-care-value">{{ (reservation as any)[field.key] }}</p>
          </div>
        </div>
      </AppCard>

      <!-- Daily Report Cards -->
      <AppCard class="overflow-hidden">
        <div class="rs-card-head">
          <div class="rs-card-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
            </svg>
          </div>
          <span class="rs-card-title">Daily Report Cards</span>
        </div>
        <div class="rs-report-body">
          <p v-if="reservation.report_cards.length === 0" class="rs-empty-note">
            Report cards will appear here during your dog's stay.
          </p>
          <div v-for="card in reservation.report_cards" :key="card.id" class="rs-report-card">
            <p class="rs-report-date">{{ card.report_date }}</p>
            <p class="rs-report-notes">{{ card.notes }}</p>
          </div>
        </div>
      </AppCard>

      <!-- Cancel -->
      <AppCard v-if="reservation.status === 'pending'" class="overflow-hidden" style="border-top: 3px solid #ef4444;">
        <div class="rs-card-head">
          <div class="rs-card-icon rs-card-icon--danger">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </div>
          <span class="rs-card-title">Cancel Reservation</span>
        </div>
        <div class="px-5 pb-5">
          <p class="text-sm text-text-muted mb-3">
            You can cancel this reservation since it hasn't been confirmed yet. Once confirmed, please contact us to cancel.
          </p>
          <div v-if="cancelForm.errors.status" class="mb-3 rounded-lg p-3 text-sm bg-red-50 text-red-700 border border-red-200">
            {{ cancelForm.errors.status }}
          </div>
          <AppButton
            variant="secondary"
            :disabled="cancelForm.processing"
            class="text-sm border-red-300 text-red-600 hover:bg-red-50"
            @click="cancelReservation"
          >
            {{ cancelForm.processing ? 'Cancelling…' : 'Cancel Reservation' }}
          </AppButton>
        </div>
      </AppCard>

    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps<{
  reservation: {
    id: string;
    status: string;
    starts_at: string;
    ends_at: string;
    nightly_rate_cents: number | null;
    notes: string | null;
    feeding_schedule: string | null;
    medication_notes: string | null;
    behavioral_notes: string | null;
    emergency_contact: string | null;
    cancelled_at: string | null;
    dog: { id: string; name: string } | null;
    kennel_unit: { id: string; name: string; type: string } | null;
    report_cards: Array<{ id: string; report_date: string; notes: string }>;
  };
}>();

const cancelForm = useForm({});

const careFields = [
  { key: 'feeding_schedule', label: 'Feeding Schedule' },
  { key: 'medication_notes', label: 'Medication Notes' },
  { key: 'behavioral_notes', label: 'Behavioral Notes' },
  { key: 'emergency_contact', label: 'Emergency Contact' },
  { key: 'notes', label: 'Additional Notes' },
];

const hasCareInstructions = computed(() =>
  careFields.some((f) => !!(props.reservation as any)[f.key]),
);

function formatDate(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
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
  return ({
    pending: 'yellow',
    confirmed: 'blue',
    checked_in: 'green',
    checked_out: 'gray',
    cancelled: 'red',
  } as Record<string, string>)[status] ?? 'gray';
}

function cancelReservation() {
  cancelForm.post(route('portal.boarding.cancel', props.reservation.id));
}
</script>

<style scoped>
/* ── Card header ── */
.rs-card-head {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid #f0ede8;
}

.rs-card-icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 0.375rem;
  background: #f0ede8;
  color: #6b6560;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.rs-card-icon svg {
  width: 1rem;
  height: 1rem;
}

.rs-card-icon--danger {
  background: #fee2e2;
  color: #dc2626;
}

.rs-card-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #2a2522;
}

/* ── Definition list ── */
.rs-dl {
  padding: 0.25rem 0;
}

.rs-dl-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
  padding: 0.6rem 1.25rem;
  border-bottom: 1px solid #f8f5f0;
  font-size: 0.875rem;
}

.rs-dl-row:last-child {
  border-bottom: none;
}

.rs-dl-row dt {
  color: #6b6560;
  font-size: 0.8125rem;
  flex-shrink: 0;
}

.rs-dl-row dd {
  color: #2a2522;
  font-weight: 500;
  text-align: right;
}

/* ── Care instructions ── */
.rs-care {
  padding: 0.5rem 0;
}

.rs-care-item {
  padding: 0.625rem 1.25rem;
  border-bottom: 1px solid #f8f5f0;
}

.rs-care-item:last-child {
  border-bottom: none;
}

.rs-care-label {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  margin-bottom: 0.25rem;
}

.rs-care-value {
  font-size: 0.875rem;
  color: #2a2522;
  white-space: pre-wrap;
}

/* ── Report cards ── */
.rs-report-body {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.rs-empty-note {
  font-size: 0.875rem;
  color: #9ca3af;
  padding: 0.5rem 0;
}

.rs-report-card {
  border: 1px solid #e5e0d8;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
}

.rs-report-date {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #2a2522;
  margin-bottom: 0.25rem;
}

.rs-report-notes {
  font-size: 0.875rem;
  color: #6b6560;
  white-space: pre-wrap;
}
</style>
