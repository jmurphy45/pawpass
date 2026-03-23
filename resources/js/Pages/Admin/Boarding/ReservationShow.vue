<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center gap-3">
        <a :href="route('admin.boarding.reservations')" class="text-text-muted hover:text-text-body text-sm">← Back</a>
        <h1 class="text-2xl font-bold text-text-body">
          {{ reservation.dog?.name ?? 'Reservation' }}
        </h1>
        <span class="badge" :class="statusBadge(reservation.status)">{{ reservation.status }}</span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: details -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Stay details -->
          <div class="card p-5 space-y-3">
            <h2 class="font-semibold text-text-body">Stay Details</h2>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
              <dt class="text-text-muted">Customer</dt><dd class="text-text-body">{{ reservation.customer?.name ?? '—' }}</dd>
              <dt class="text-text-muted">Unit</dt><dd class="text-text-body">{{ reservation.kennel_unit?.name ?? 'Unassigned' }}</dd>
              <dt class="text-text-muted">Check-in</dt><dd class="text-text-body">{{ formatDate(reservation.starts_at) }}</dd>
              <dt class="text-text-muted">Check-out</dt><dd class="text-text-body">{{ formatDate(reservation.ends_at) }}</dd>
              <dt class="text-text-muted">Nightly rate</dt><dd class="text-text-body">{{ reservation.nightly_rate_cents ? '$' + (reservation.nightly_rate_cents / 100).toFixed(2) : '—' }}</dd>
            </dl>
          </div>

          <!-- Care instructions -->
          <div class="card p-5 space-y-3">
            <h2 class="font-semibold text-text-body">Care Instructions</h2>
            <dl class="space-y-2 text-sm">
              <div v-for="field in careFields" :key="field.key">
                <dt class="text-text-muted text-xs uppercase tracking-wide">{{ field.label }}</dt>
                <dd class="text-text-body mt-0.5 whitespace-pre-wrap">{{ (reservation as any)[field.key] || '—' }}</dd>
              </div>
            </dl>
          </div>

          <!-- Daily Report Cards -->
          <div class="card p-5 space-y-4">
            <h2 class="font-semibold text-text-body">Daily Report Cards</h2>
            <div v-if="reportCards.length === 0" class="text-sm text-text-muted">No report cards yet.</div>
            <div v-for="card in reportCards" :key="card.id" class="border border-border rounded-lg p-3 text-sm space-y-1">
              <p class="font-medium text-text-body">{{ card.report_date }}</p>
              <p class="text-text-muted whitespace-pre-wrap">{{ card.notes }}</p>
            </div>

            <!-- New card form -->
            <form @submit.prevent="submitReportCard" class="space-y-2 pt-2 border-t border-border">
              <h3 class="text-sm font-medium text-text-body">Add Report Card</h3>
              <input v-model="cardForm.report_date" type="date" class="input text-sm w-full" required />
              <textarea v-model="cardForm.notes" class="input text-sm w-full h-24 resize-none" placeholder="How was the stay today?" required />
              <button type="submit" class="btn-primary text-sm">Save</button>
            </form>
          </div>

          <!-- Add-ons -->
          <div class="card p-5 space-y-4">
            <h2 class="font-semibold text-text-body">Add-on Services</h2>
            <div v-if="addons.length === 0" class="text-sm text-text-muted">No add-ons yet.</div>
            <div v-for="addon in addons" :key="addon.id" class="flex items-center justify-between text-sm">
              <span class="text-text-body">{{ addon.addon_type?.name ?? addon.addon_name }}</span>
              <span class="text-text-muted">{{ addon.quantity }} × ${{ (addon.unit_price_cents / 100).toFixed(2) }}</span>
            </div>
            <form @submit.prevent="addAddon" class="flex gap-2 pt-2 border-t border-border">
              <select v-model="addonForm.addon_type_id" class="input text-sm flex-1">
                <option value="">Select add-on…</option>
                <option v-for="at in addonTypes" :key="at.id" :value="at.id">
                  {{ at.name }} (${{ (at.price_cents / 100).toFixed(2) }})
                </option>
              </select>
              <button type="submit" class="btn-primary text-sm px-4" :disabled="!addonForm.addon_type_id">Add</button>
            </form>
          </div>
        </div>

        <!-- Right column: vaccination compliance -->
        <div class="space-y-4">
          <div class="card p-5 space-y-3">
            <h2 class="font-semibold text-text-body">Vaccination Compliance</h2>
            <div v-if="vaccinationCompliance.length === 0" class="text-sm text-text-muted">No vaccination requirements configured.</div>
            <ul class="space-y-2">
              <li v-for="vc in vaccinationCompliance" :key="vc.vaccine_name" class="flex items-center gap-2 text-sm">
                <span
                  class="h-2.5 w-2.5 rounded-full shrink-0"
                  :class="{ 'bg-green-500': vc.status === 'valid', 'bg-red-500': vc.status !== 'valid' }"
                />
                <span class="text-text-body">{{ vc.vaccine_name }}</span>
                <span class="ml-auto text-xs" :class="{ 'text-green-600': vc.status === 'valid', 'text-red-500': vc.status !== 'valid' }">
                  {{ vc.status }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface AddonType { id: string; name: string; price_cents: number }
interface Addon { id: number; addon_type?: AddonType; addon_name?: string; quantity: number; unit_price_cents: number }
interface ReportCard { id: string; report_date: string; notes: string }
interface VaxCompliance { vaccine_name: string; status: 'valid' | 'missing' | 'expired' }

const props = defineProps<{
  reservation: Record<string, unknown>;
  reportCards: ReportCard[];
  addons: Addon[];
  addonTypes: AddonType[];
  vaccinationCompliance: VaxCompliance[];
}>();

const careFields = [
  { key: 'feeding_schedule', label: 'Feeding schedule' },
  { key: 'medication_notes', label: 'Medication notes' },
  { key: 'behavioral_notes', label: 'Behavioral notes' },
  { key: 'emergency_contact', label: 'Emergency contact' },
];

const cardForm = reactive({ report_date: '', notes: '' });
const addonForm = reactive({ addon_type_id: '' });

function submitReportCard() {
  const res = props.reservation as { id: string };
  router.post(`/api/admin/v1/reservations/${res.id}/report-cards`, { ...cardForm }, {
    onSuccess: () => { cardForm.report_date = ''; cardForm.notes = ''; },
  });
}

function addAddon() {
  const res = props.reservation as { id: string };
  router.post(`/api/admin/v1/reservations/${res.id}/addons`, { addon_type_id: addonForm.addon_type_id }, {
    onSuccess: () => { addonForm.addon_type_id = ''; },
  });
}

function formatDate(iso: unknown) {
  return typeof iso === 'string' ? iso.slice(0, 10) : '—';
}

function statusBadge(status: unknown) {
  return {
    'badge-yellow': status === 'pending',
    'badge-blue': status === 'confirmed',
    'badge-green': status === 'checked_in',
    'badge-gray': status === 'checked_out',
    'badge-red': status === 'cancelled',
  };
}
</script>
