<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center gap-3 flex-wrap">
        <a :href="route('admin.boarding.reservations')" class="text-text-muted hover:text-text-body text-sm">← Back</a>
        <h1 class="text-2xl font-bold text-text-body">
          {{ reservation.dog?.name ?? 'Reservation' }}
        </h1>
        <span class="badge" :class="statusBadge(reservation.status)">{{ reservation.status }}</span>
      </div>

      <!-- Status Actions (non-checkout statuses) -->
      <div v-if="availableActions.length > 0" class="card overflow-hidden">
        <div class="rsh-card-head">
          <div class="rsh-card-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
          </div>
          <span class="rsh-card-title">Actions</span>
        </div>
        <div class="p-4">

        <!-- Confirmation prompt -->
        <div v-if="confirmingAction" class="flex items-center gap-3 text-sm">
          <span class="text-text-body">{{ confirmMessage }}</span>
          <button @click="executeAction" class="btn-primary text-xs py-1 px-3">Confirm</button>
          <button @click="confirmingAction = null" class="btn-secondary text-xs py-1 px-3">Cancel</button>
        </div>

        <!-- Action buttons -->
        <div v-else class="flex flex-wrap gap-2">
          <button
            v-for="action in availableActions"
            :key="action.status"
            @click="handleAction(action)"
            :class="action.variant === 'danger' ? 'btn-danger' : action.variant === 'secondary' ? 'btn-secondary' : 'btn-primary'"
            class="text-sm"
          >
            {{ action.label }}
          </button>
        </div>
        </div>
      </div>

      <!-- Checkout Form (checked_in only) -->
      <div v-if="reservation.status === 'checked_in'" class="card overflow-hidden">
        <div class="rsh-checkout-head">
          <div class="rsh-card-icon rsh-card-icon--checkout">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
          </div>
          <span class="rsh-card-title rsh-card-title--light">Checkout &amp; Billing</span>
        </div>
        <div class="p-5 space-y-3">
          <div>
            <label class="text-sm text-text-muted block mb-1">Actual end date</label>
            <input v-model="checkoutDate" type="date" class="input text-sm w-48" :min="checkoutMinDate" />
          </div>

          <!-- Breakdown -->
          <dl class="text-sm space-y-1 border-t border-border pt-3">
            <div class="flex justify-between">
              <dt class="text-text-muted">{{ checkoutNights }} nights × ${{ nightlyRateFormatted }}</dt>
              <dd class="text-text-body">${{ nightsTotalFormatted }}</dd>
            </div>
            <div v-if="addonsTotal > 0" class="flex justify-between">
              <dt class="text-text-muted">Add-ons</dt>
              <dd class="text-text-body">${{ (addonsTotal / 100).toFixed(2) }}</dd>
            </div>
            <div v-if="depositAmount" class="flex justify-between">
              <dt class="text-text-muted">Deposit captured</dt>
              <dd class="text-text-body">− ${{ depositAmount }}</dd>
            </div>
            <div class="flex justify-between font-semibold border-t border-border pt-1">
              <dt class="text-text-body">Balance due</dt>
              <dd class="text-text-body">${{ checkoutBalance }}</dd>
            </div>
          </dl>

          <!-- Card info -->
          <p v-if="savedCard?.pm_id" class="text-sm text-text-muted">
            Will charge {{ savedCard.brand }} ···{{ savedCard.last4 }}
          </p>
          <p v-else-if="checkoutBalanceCents > 0" class="text-sm text-amber-600">
            No card on file — checkout will be recorded without charge.
          </p>
          <p v-else class="text-sm text-text-muted">No balance due — checkout without charge.</p>

          <button @click="submitCheckout" class="rsh-checkout-btn">
            {{ checkoutBalanceCents > 0 && savedCard?.pm_id ? `Confirm Checkout & Charge $${checkoutBalance}` : 'Confirm Checkout' }}
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: details -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Stay details -->
          <div class="card overflow-hidden">
            <div class="rsh-card-head">
              <div class="rsh-card-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
              </div>
              <span class="rsh-card-title">Stay Details</span>
            </div>
            <dl class="rsh-dl">
              <div class="rsh-dl-row"><dt>Customer</dt><dd>{{ reservation.customer?.name ?? '—' }}</dd></div>
              <div class="rsh-dl-row"><dt>Unit</dt><dd>{{ reservation.kennel_unit?.name ?? 'Unassigned' }}</dd></div>
              <div class="rsh-dl-row"><dt>Check-in</dt><dd>{{ formatDate(reservation.starts_at) }}</dd></div>
              <div class="rsh-dl-row"><dt>Check-out (planned)</dt><dd>{{ formatDate(reservation.ends_at) }}</dd></div>
              <div class="rsh-dl-row"><dt>Nightly rate</dt><dd>{{ reservation.nightly_rate_cents ? '$' + ((reservation.nightly_rate_cents as number) / 100).toFixed(2) : '—' }}</dd></div>
              <template v-if="reservation.status === 'checked_out'">
                <div class="rsh-dl-row"><dt>Actual checkout</dt><dd>{{ formatDate(reservation.actual_checkout_at) }}</dd></div>
                <div class="rsh-dl-row">
                  <dt>Charged at checkout</dt>
                  <dd>
                    <span v-if="reservation.checkout_charge_cents">
                      ${{ ((reservation.checkout_charge_cents as number) / 100).toFixed(2) }}
                      <span v-if="reservation.checkout_pi_id" class="text-xs text-text-muted font-mono ml-1">…{{ (reservation.checkout_pi_id as string).slice(-8) }}</span>
                    </span>
                    <span v-else class="text-text-muted">No charge</span>
                  </dd>
                </div>
              </template>
            </dl>
          </div>

          <!-- Care instructions -->
          <div class="card overflow-hidden">
            <div class="rsh-card-head">
              <div class="rsh-card-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
              </div>
              <span class="rsh-card-title">Care Instructions</span>
            </div>
            <div class="rsh-care">
              <div v-for="field in careFields" :key="field.key" class="rsh-care-item">
                <p class="rsh-care-label">{{ field.label }}</p>
                <p class="rsh-care-value">{{ (reservation as any)[field.key] || '—' }}</p>
              </div>
            </div>
          </div>

          <!-- Daily Report Cards -->
          <div class="card overflow-hidden">
            <div class="rsh-card-head">
              <div class="rsh-card-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
              </div>
              <span class="rsh-card-title">Daily Report Cards</span>
            </div>
            <div class="p-5 space-y-4">
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
          </div>

          <!-- Add-ons -->
          <div class="card overflow-hidden">
            <div class="rsh-card-head">
              <div class="rsh-card-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
              </div>
              <span class="rsh-card-title">Add-on Services</span>
            </div>
            <div class="p-5 space-y-4">
            <div v-if="addons.length === 0" class="text-sm text-text-muted">No add-ons yet.</div>
            <div v-for="addon in addons" :key="addon.id" class="flex items-center justify-between text-sm">
              <span class="text-text-body">{{ addon.addon_type?.name ?? addon.addon_name }}</span>
              <div class="flex items-center gap-3">
                <span class="text-text-muted">{{ addon.quantity }} × ${{ (addon.unit_price_cents / 100).toFixed(2) }}</span>
                <button
                  v-if="reservation.status !== 'checked_out'"
                  @click="removeAddon(addon.id)"
                  class="text-red-400 hover:text-red-600 text-xs"
                  title="Remove add-on"
                >×</button>
              </div>
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
        </div>

        <!-- Right column -->
        <div class="space-y-4">

          <!-- Vaccination compliance -->
          <div class="card overflow-hidden">
            <div class="rsh-card-head">
              <div class="rsh-card-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
              </div>
              <span class="rsh-card-title">Vaccination Compliance</span>
            </div>
            <div class="p-4 space-y-2">
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

          <!-- Payment & Deposit -->
          <div class="card overflow-hidden">
            <div class="rsh-card-head">
              <div class="rsh-card-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
              </div>
              <span class="rsh-card-title">Payment &amp; Deposit</span>
            </div>
            <div class="p-4 space-y-2">
            <div v-if="!depositAmount" class="text-sm text-text-muted">No deposit collected.</div>

            <dl v-else class="space-y-2 text-sm">
              <div class="flex justify-between">
                <dt class="text-text-muted">Deposit</dt>
                <dd class="font-medium text-text-body">${{ depositAmount }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-text-muted">Status</dt>
                <dd>
                  <span class="badge" :class="depositBadgeClass">{{ depositStatus }}</span>
                </dd>
              </div>
              <div v-if="reservation.stripe_pi_id" class="flex justify-between">
                <dt class="text-text-muted">Reference</dt>
                <dd class="text-text-muted font-mono text-xs">…{{ (reservation.stripe_pi_id as string).slice(-8) }}</dd>
              </div>
              <div v-if="reservation.deposit_captured_at" class="flex justify-between">
                <dt class="text-text-muted">Captured</dt>
                <dd class="text-text-body">{{ formatDate(reservation.deposit_captured_at) }}</dd>
              </div>
              <div v-if="reservation.deposit_refunded_at" class="flex justify-between">
                <dt class="text-text-muted">Released</dt>
                <dd class="text-text-body">{{ formatDate(reservation.deposit_refunded_at) }}</dd>
              </div>
            </dl>
            </div>
          </div>

        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface AddonType { id: string; name: string; price_cents: number }
interface Addon { id: number; addon_type?: AddonType; addon_name?: string; quantity: number; unit_price_cents: number }
interface ReportCard { id: string; report_date: string; notes: string }
interface VaxCompliance { vaccine_name: string; status: 'valid' | 'missing' | 'expired' }
interface SavedCard { last4: string | null; brand: string | null; pm_id: string | null }

interface Action {
  label: string;
  status: string;
  variant: 'primary' | 'secondary' | 'danger';
  confirm?: string;
}

const props = defineProps<{
  reservation: Record<string, unknown>;
  reportCards: ReportCard[];
  addons: Addon[];
  addonTypes: AddonType[];
  vaccinationCompliance: VaxCompliance[];
  savedCard: SavedCard;
}>();

const careFields = [
  { key: 'feeding_schedule', label: 'Feeding schedule' },
  { key: 'medication_notes', label: 'Medication notes' },
  { key: 'behavioral_notes', label: 'Behavioral notes' },
  { key: 'emergency_contact', label: 'Emergency contact' },
];

// -------------------------------------------------------------------------
// State machine actions
// -------------------------------------------------------------------------

const ACTION_MAP: Record<string, Action[]> = {
  pending: [
    { label: 'Confirm Reservation', status: 'confirmed', variant: 'primary' },
    { label: 'Cancel', status: 'cancelled', variant: 'danger', confirm: 'Cancel this reservation? Any deposit hold will be released.' },
  ],
  confirmed: [
    { label: 'Check In', status: 'checked_in', variant: 'primary', confirm: 'Check in and capture the deposit? This cannot be undone.' },
    { label: 'Cancel', status: 'cancelled', variant: 'danger', confirm: 'Cancel this reservation? Any deposit hold will be released.' },
  ],
  // checked_in: handled by the dedicated checkout form below
};

const availableActions = computed<Action[]>(() => {
  const status = props.reservation.status as string;
  return ACTION_MAP[status] ?? [];
});

const confirmingAction = ref<Action | null>(null);

const confirmMessage = computed(() => confirmingAction.value?.confirm ?? '');

function handleAction(action: Action) {
  if (action.confirm) {
    confirmingAction.value = action;
  } else {
    submitStatus(action.status);
  }
}

function executeAction() {
  if (confirmingAction.value) {
    submitStatus(confirmingAction.value.status);
    confirmingAction.value = null;
  }
}

function submitStatus(status: string) {
  const res = props.reservation as { id: string };
  router.patch(route('admin.boarding.reservations.update', res.id), { status });
}

// -------------------------------------------------------------------------
// Checkout form
// -------------------------------------------------------------------------

const today = new Date().toISOString().slice(0, 10);
const checkoutDate = ref(today);

const checkoutMinDate = computed(() => {
  const startsAt = props.reservation.starts_at as string | null;
  return startsAt ? startsAt.slice(0, 10) : undefined;
});

const checkoutNights = computed(() => {
  const startsAt = props.reservation.starts_at as string | null;
  if (!startsAt || !checkoutDate.value) return 0;
  const start = new Date(startsAt.slice(0, 10));
  const end   = new Date(checkoutDate.value);
  const diff  = Math.floor((end.getTime() - start.getTime()) / 86_400_000);
  return Math.max(0, diff);
});

const nightlyRateCents = computed(() => (props.reservation.nightly_rate_cents as number) ?? 0);
const nightlyRateFormatted = computed(() => (nightlyRateCents.value / 100).toFixed(2));
const nightsTotalFormatted = computed(() => ((checkoutNights.value * nightlyRateCents.value) / 100).toFixed(2));

const addonsTotal = computed(() =>
  props.addons.reduce((sum, a) => sum + a.unit_price_cents * a.quantity, 0)
);

const checkoutBalanceCents = computed(() => {
  const deposit = (props.reservation.deposit_amount_cents as number) ?? 0;
  return Math.max(0, checkoutNights.value * nightlyRateCents.value + addonsTotal.value - deposit);
});

const checkoutBalance = computed(() => (checkoutBalanceCents.value / 100).toFixed(2));

function submitCheckout() {
  const res = props.reservation as { id: string };
  router.post(route('admin.boarding.reservations.checkout', res.id), {
    actual_checkout_date: checkoutDate.value,
  });
}

// -------------------------------------------------------------------------
// Report cards & add-ons
// -------------------------------------------------------------------------

const cardForm = reactive({ report_date: '', notes: '' });
const addonForm = reactive({ addon_type_id: '' });

function submitReportCard() {
  const res = props.reservation as { id: string };
  router.post(route('admin.boarding.reservations.report-cards.store', res.id), { ...cardForm }, {
    onSuccess: () => { cardForm.report_date = ''; cardForm.notes = ''; },
  });
}

function addAddon() {
  const res = props.reservation as { id: string };
  router.post(route('admin.boarding.reservations.addons.store', res.id), { addon_type_id: addonForm.addon_type_id }, {
    onSuccess: () => { addonForm.addon_type_id = ''; },
  });
}

function removeAddon(addonId: number) {
  const res = props.reservation as { id: string };
  router.delete(route('admin.boarding.reservations.addons.destroy', { reservation: res.id, addon: addonId }));
}

// -------------------------------------------------------------------------
// Deposit helpers
// -------------------------------------------------------------------------

const depositAmount = computed(() => {
  const cents = props.reservation.deposit_amount_cents as number | null;
  return cents ? (cents / 100).toFixed(2) : null;
});

const depositStatus = computed(() => {
  if (props.reservation.deposit_captured_at) return 'Captured';
  if (props.reservation.deposit_refunded_at) return 'Released';
  if (props.reservation.stripe_pi_id) return 'Authorized';
  return 'None';
});

const depositBadgeClass = computed(() => ({
  'badge-green': depositStatus.value === 'Captured',
  'badge-gray':  depositStatus.value === 'Released',
  'badge-blue':  depositStatus.value === 'Authorized',
  'badge-yellow': depositStatus.value === 'None',
}));

// -------------------------------------------------------------------------
// Formatting
// -------------------------------------------------------------------------

function formatDate(iso: unknown) {
  return typeof iso === 'string' ? iso.slice(0, 10) : '—';
}

function statusBadge(status: unknown) {
  return {
    'badge-yellow': status === 'pending',
    'badge-blue':   status === 'confirmed',
    'badge-green':  status === 'checked_in',
    'badge-gray':   status === 'checked_out',
    'badge-red':    status === 'cancelled',
  };
}
</script>

<style scoped>
/* ── Card header ── */
.rsh-card-head {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid #f0ede8;
}

.rsh-card-icon {
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

.rsh-card-icon svg {
  width: 1rem;
  height: 1rem;
}

.rsh-card-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #2a2522;
}

/* ── Checkout card ── */
.rsh-checkout-head {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.875rem 1.25rem;
  background: #2a2522;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.rsh-card-icon--checkout {
  background: rgba(255,255,255,0.1);
  color: #ffffff;
}

.rsh-card-title--light {
  color: #ffffff;
}

.rsh-checkout-btn {
  width: 100%;
  padding: 0.75rem 1.25rem;
  background: #2a2522;
  color: #ffffff;
  font-size: 0.9375rem;
  font-weight: 700;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: background 150ms ease, box-shadow 150ms ease, transform 120ms ease;
}

.rsh-checkout-btn:hover {
  background: #1a1514;
  box-shadow: 0 6px 20px rgba(42,37,34,0.3);
  transform: translateY(-1px);
}

/* ── Definition list ── */
.rsh-dl {
  padding: 0.25rem 0;
}

.rsh-dl-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
  padding: 0.6rem 1.25rem;
  border-bottom: 1px solid #f8f5f0;
  font-size: 0.875rem;
}

.rsh-dl-row:last-child {
  border-bottom: none;
}

.rsh-dl-row dt {
  color: #6b6560;
  font-size: 0.8125rem;
  flex-shrink: 0;
}

.rsh-dl-row dd {
  color: #2a2522;
  font-weight: 500;
  text-align: right;
}

/* ── Care instructions ── */
.rsh-care {
  padding: 0.25rem 0;
}

.rsh-care-item {
  padding: 0.625rem 1.25rem;
  border-bottom: 1px solid #f8f5f0;
}

.rsh-care-item:last-child {
  border-bottom: none;
}

.rsh-care-label {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  margin-bottom: 0.25rem;
}

.rsh-care-value {
  font-size: 0.875rem;
  color: #2a2522;
  white-space: pre-wrap;
}
</style>
