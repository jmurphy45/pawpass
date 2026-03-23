<template>
  <PortalLayout>
    <div class="space-y-6 max-w-2xl">
      <!-- Header -->
      <div class="flex items-center gap-3">
        <Link :href="route('portal.boarding.index')" class="text-text-muted hover:text-text-body text-sm">← Back</Link>
        <h1 class="text-2xl font-bold text-text-body">
          {{ reservation.dog?.name ?? 'Reservation' }}
        </h1>
        <span class="badge" :class="statusBadge(reservation.status)">{{ reservation.status }}</span>
      </div>

      <!-- Stay details -->
      <div class="card p-5 space-y-3">
        <h2 class="font-semibold text-text-body">Stay Details</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
          <dt class="text-text-muted">Unit</dt>
          <dd class="text-text-body">{{ reservation.kennel_unit?.name ?? 'To be assigned' }}</dd>
          <dt class="text-text-muted">Check-in</dt>
          <dd class="text-text-body">{{ formatDate(reservation.starts_at) }}</dd>
          <dt class="text-text-muted">Check-out</dt>
          <dd class="text-text-body">{{ formatDate(reservation.ends_at) }}</dd>
          <dt v-if="reservation.nightly_rate_cents" class="text-text-muted">Nightly rate</dt>
          <dd v-if="reservation.nightly_rate_cents" class="text-text-body">${{ (reservation.nightly_rate_cents / 100).toFixed(2) }}</dd>
        </dl>
      </div>

      <!-- Care instructions (only show if any are set) -->
      <div v-if="hasCareInstructions" class="card p-5 space-y-3">
        <h2 class="font-semibold text-text-body">Care Instructions</h2>
        <dl class="space-y-2 text-sm">
          <div v-for="field in careFields" :key="field.key" v-show="(reservation as any)[field.key]">
            <dt class="text-text-muted text-xs uppercase tracking-wide">{{ field.label }}</dt>
            <dd class="text-text-body mt-0.5 whitespace-pre-wrap">{{ (reservation as any)[field.key] }}</dd>
          </div>
        </dl>
      </div>

      <!-- Daily Report Cards (read-only) -->
      <div class="card p-5 space-y-3">
        <h2 class="font-semibold text-text-body">Daily Report Cards</h2>
        <p v-if="reservation.report_cards.length === 0" class="text-sm text-text-muted">
          Report cards will appear here during your dog's stay.
        </p>
        <div
          v-for="card in reservation.report_cards"
          :key="card.id"
          class="border border-border rounded-lg p-3 text-sm space-y-1"
        >
          <p class="font-medium text-text-body">{{ card.report_date }}</p>
          <p class="text-text-muted whitespace-pre-wrap">{{ card.notes }}</p>
        </div>
      </div>

      <!-- Cancel (only for pending) -->
      <div v-if="reservation.status === 'pending'" class="card p-5">
        <h2 class="font-semibold text-text-body mb-3">Cancel Reservation</h2>
        <p class="text-sm text-text-muted mb-3">
          You can cancel this reservation since it hasn't been confirmed yet. Once confirmed, please contact us to cancel.
        </p>
        <div v-if="cancelError" class="mb-3 rounded-lg p-3 text-sm bg-red-50 text-red-700 border border-red-200">{{ cancelError }}</div>
        <button
          @click="cancelReservation"
          :disabled="cancelling"
          class="btn-secondary text-sm border-red-300 text-red-600 hover:bg-red-50"
        >
          {{ cancelling ? 'Cancelling…' : 'Cancel Reservation' }}
        </button>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import axios from 'axios';

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

const cancelling = ref(false);
const cancelError = ref('');

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

function statusBadge(status: string): string {
  return ({
    pending: 'badge-yellow',
    confirmed: 'badge-blue',
    checked_in: 'badge-green',
    checked_out: 'badge-gray',
    cancelled: 'badge-red',
  } as Record<string, string>)[status] ?? 'badge-gray';
}

async function cancelReservation() {
  cancelError.value = '';
  cancelling.value = true;
  try {
    await axios.patch(`/api/portal/v1/reservations/${props.reservation.id}/cancel`);
    router.reload({ only: ['reservation'] });
  } catch (err: any) {
    cancelError.value = err.response?.data?.error === 'CANNOT_CANCEL_RESERVATION'
      ? 'This reservation can no longer be cancelled. Please contact us for help.'
      : 'Something went wrong. Please try again.';
  } finally {
    cancelling.value = false;
  }
}
</script>
