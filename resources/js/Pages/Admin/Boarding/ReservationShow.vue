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

          <button @click="submitCheckout" class="btn-primary text-sm">
            {{ checkoutBalanceCents > 0 && savedCard?.pm_id ? `Confirm Checkout & Charge $${checkoutBalance}` : 'Confirm Checkout' }}
          </button>
        </div>
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
              <dt class="text-text-muted">Check-out (planned)</dt><dd class="text-text-body">{{ formatDate(reservation.ends_at) }}</dd>
              <dt class="text-text-muted">Nightly rate</dt><dd class="text-text-body">{{ reservation.nightly_rate_cents ? '$' + ((reservation.nightly_rate_cents as number) / 100).toFixed(2) : '—' }}</dd>
              <!-- Actual checkout summary -->
              <template v-if="reservation.status === 'checked_out'">
                <dt class="text-text-muted">Actual checkout</dt><dd class="text-text-body">{{ formatDate(reservation.actual_checkout_at) }}</dd>
                <dt class="text-text-muted">Charged at checkout</dt>
                <dd class="text-text-body">
                  <span v-if="reservation.checkout_charge_cents">
                    ${{ ((reservation.checkout_charge_cents as number) / 100).toFixed(2) }}
                    <span v-if="reservation.checkout_pi_id" class="text-xs text-text-muted font-mono ml-1">…{{ (reservation.checkout_pi_id as string).slice(-8) }}</span>
                  </span>
                  <span v-else class="text-text-muted">No charge</span>
                </dd>
              </template>
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

        <!-- Right column -->
        <div class="space-y-4">

          <!-- Vaccination compliance -->
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

          <!-- Payment & Deposit -->
          <div class="card p-5 space-y-3">
            <h2 class="font-semibold text-text-body">Payment & Deposit</h2>

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
