<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Settings</h1>

      <!-- Business Settings -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Business</h2>
        <form @submit.prevent="submitBusiness" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
            <input v-model="businessForm.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            <p v-if="businessForm.errors.name" class="mt-1 text-sm text-red-600">{{ businessForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <input v-model="businessForm.timezone" type="text" placeholder="America/Chicago" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            <p v-if="businessForm.errors.timezone" class="mt-1 text-sm text-red-600">{{ businessForm.errors.timezone }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Color</label>
            <input v-model="businessForm.primary_color" type="color" class="h-9 w-20 rounded-lg border border-gray-300 px-1 py-1 cursor-pointer" />
            <p v-if="businessForm.errors.primary_color" class="mt-1 text-sm text-red-600">{{ businessForm.errors.primary_color }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Low Credit Threshold</label>
            <input v-model.number="businessForm.low_credit_threshold" type="number" min="0" class="w-40 rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            <p v-if="businessForm.errors.low_credit_threshold" class="mt-1 text-sm text-red-600">{{ businessForm.errors.low_credit_threshold }}</p>
          </div>
          <div class="flex items-center gap-3">
            <input id="checkin_block" v-model="businessForm.checkin_block_at_zero" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
            <label for="checkin_block" class="text-sm font-medium text-gray-700">Block check-in when credits reach zero</label>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
            <select v-model="businessForm.business_type" class="w-48 rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white">
              <option value="daycare">Daycare Only</option>
              <option value="kennel">Kennel / Boarding Only</option>
              <option value="hybrid">Daycare + Boarding</option>
            </select>
            <p v-if="businessForm.errors.business_type" class="mt-1 text-sm text-red-600">{{ businessForm.errors.business_type }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payout Schedule</label>
            <select v-model="businessForm.payout_schedule" class="w-48 rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
            <p v-if="businessForm.errors.payout_schedule" class="mt-1 text-sm text-red-600">{{ businessForm.errors.payout_schedule }}</p>
          </div>
          <button type="submit" :disabled="businessForm.processing" class="btn-primary">
            Save
          </button>
        </form>
      </div>

      <!-- Notification Settings -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Notifications</h2>
        <div class="space-y-3">
          <div v-for="type in TOGGLEABLE_TYPES" :key="type" class="flex items-center justify-between py-1">
            <span class="flex items-center gap-1.5 text-sm text-gray-700">
              {{ formatNotifType(type) }}
              <span class="relative group">
                <svg class="w-3.5 h-3.5 text-gray-400 cursor-help" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 rounded-lg bg-gray-900 px-3 py-2 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity z-10 shadow-lg">
                  {{ NOTIF_DESCRIPTIONS[type] }}
                  <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900" />
                </span>
              </span>
            </span>
            <button
              type="button"
              @click="notifToggles[type] = !notifToggles[type]"
              class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              :class="notifToggles[type] ? 'bg-indigo-600' : 'bg-gray-200'"
              :aria-checked="notifToggles[type]"
              role="switch"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
                :class="notifToggles[type] ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </div>
        </div>

        <div class="my-4 border-t border-gray-100" />

        <div class="mb-3">
          <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Always On</span>
        </div>
        <div class="space-y-2">
          <div v-for="type in CRITICAL_TYPES" :key="type" class="flex items-center justify-between py-1">
            <span class="flex items-center gap-1.5 text-sm text-gray-500">
              {{ formatNotifType(type) }}
              <span class="relative group">
                <svg class="w-3.5 h-3.5 text-gray-300 cursor-help" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 rounded-lg bg-gray-900 px-3 py-2 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity z-10 shadow-lg">
                  {{ NOTIF_DESCRIPTIONS[type] }}
                  <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900" />
                </span>
              </span>
            </span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 font-medium">Required</span>
          </div>
        </div>

        <div class="mt-4">
          <button type="button" @click="submitNotifications" :disabled="notifForm.processing" class="btn-primary">
            Save
          </button>
        </div>
      </div>

      <!-- Staff Management -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Staff</h2>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left font-medium text-gray-500 pb-2 pr-4">Name</th>
                <th class="text-left font-medium text-gray-500 pb-2 pr-4">Email</th>
                <th class="text-left font-medium text-gray-500 pb-2 pr-4">Role</th>
                <th class="text-left font-medium text-gray-500 pb-2 pr-4">Status</th>
                <th class="pb-2" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="member in props.staff" :key="member.id" class="py-2">
                <td class="py-2.5 pr-4 text-gray-900 font-medium">{{ member.name }}</td>
                <td class="py-2.5 pr-4 text-gray-600">{{ member.email }}</td>
                <td class="py-2.5 pr-4 text-gray-600 capitalize">{{ member.role.replace('_', ' ') }}</td>
                <td class="py-2.5 pr-4">
                  <span :class="statusClass(member.status)">{{ member.status.replace('_', ' ') }}</span>
                </td>
                <td class="py-2.5 text-right">
                  <button
                    v-if="member.status !== 'suspended' && !(member.role === 'business_owner' && activeOwnerCount <= 1)"
                    type="button"
                    @click="deactivateStaff(member.id)"
                    :disabled="deactivatingId === member.id"
                    class="btn-ghost-danger"
                  >Deactivate</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="my-5 border-t border-gray-100" />

        <h3 class="text-sm font-semibold text-gray-700 mb-3">Invite Staff Member</h3>
        <form @submit.prevent="submitInvite" class="space-y-3">
          <div class="flex gap-3">
            <div class="flex-1">
              <input v-model="inviteForm.name" type="text" placeholder="Name" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
              <p v-if="inviteForm.errors.name" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.name }}</p>
            </div>
            <div class="flex-1">
              <input v-model="inviteForm.email" type="email" placeholder="Email" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
              <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.email }}</p>
            </div>
          </div>
          <button type="submit" :disabled="inviteForm.processing" class="btn-primary">
            Send Invite
          </button>
        </form>
      </div>
    </div>
  </AdminLayout>
  <ConfirmModal :open="confirmModal.open" :title="confirmModal.title" :message="confirmModal.message" @confirm="handleConfirm" @cancel="handleCancel" />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps<{
  business: { name: string; timezone: string; primary_color: string; low_credit_threshold: number; checkin_block_at_zero: boolean; payout_schedule: string; business_type: string };
  notificationSettings: Array<{ type: string; is_enabled: boolean }>;
  staff: Array<{ id: string; name: string; email: string; role: string; status: string }>;
}>();

// ── Business form ─────────────────────────────────────────────────────────────

const businessForm = useForm({
  name:                  props.business.name,
  timezone:              props.business.timezone,
  primary_color:         props.business.primary_color,
  low_credit_threshold:  props.business.low_credit_threshold,
  checkin_block_at_zero: props.business.checkin_block_at_zero,
  payout_schedule:       props.business.payout_schedule,
  business_type:         props.business.business_type,
});

function submitBusiness() {
  businessForm.patch(route('admin.settings.business'));
}

// ── Notification settings ─────────────────────────────────────────────────────

const TOGGLEABLE_TYPES = ['credits.low', 'subscription.renewed', 'staff.invite'];
const CRITICAL_TYPES   = [
  'payment.confirmed', 'payment.refunded', 'subscription.payment_failed',
  'subscription.cancelled', 'credits.empty', 'auth.verify_email', 'auth.password_reset',
];

const NOTIF_DESCRIPTIONS: Record<string, string> = {
  'credits.low':                   'Sent to a customer when their dog\'s credit balance drops to or below the low credit threshold.',
  'subscription.renewed':          'Sent to a customer when their monthly subscription renews and credits are added.',
  'staff.invite':                  'Sent to a new staff member with a link to set up their account.',
  'payment.confirmed':             'Sent to a customer immediately after a one-time payment succeeds.',
  'payment.refunded':              'Sent to a customer when a payment is refunded.',
  'subscription.payment_failed':   'Sent to a customer when a subscription renewal payment fails.',
  'subscription.cancelled':        'Sent to a customer when their subscription is cancelled.',
  'credits.empty':                 'Sent to a customer when their dog\'s credit balance reaches zero.',
  'auth.verify_email':             'Sent to a new user with a link to verify their email address.',
  'auth.password_reset':           'Sent when a user requests a password reset link.',
};

function buildNotifToggles(): Record<string, boolean> {
  const map: Record<string, boolean> = {};
  for (const t of TOGGLEABLE_TYPES) map[t] = true;
  for (const row of props.notificationSettings) {
    if (row.type in map) map[row.type] = row.is_enabled;
  }
  return map;
}

const notifToggles = ref(buildNotifToggles());
const notifForm    = useForm<{ settings: Array<{ type: string; is_enabled: boolean }> }>({ settings: [] });

function submitNotifications() {
  notifForm.settings = TOGGLEABLE_TYPES.map(t => ({ type: t, is_enabled: notifToggles.value[t] }));
  notifForm.patch(route('admin.settings.notifications'));
}

function formatNotifType(type: string): string {
  return type.split('.').map(s => s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ')).join(': ');
}

// ── Staff management ──────────────────────────────────────────────────────────

const activeOwnerCount = computed(() =>
  props.staff.filter(m => m.role === 'business_owner' && m.status === 'active').length
);

const deactivatingId = ref<string | null>(null);
const deactivateForm = useForm({});
const inviteForm     = useForm({ name: '', email: '' });

const confirmModal = ref<{ open: boolean; title: string; message: string; onConfirm: (() => void) | null }>
  ({ open: false, title: '', message: '', onConfirm: null });

function askConfirm(title: string, message: string, onConfirm: () => void) {
  confirmModal.value = { open: true, title, message, onConfirm };
}
function handleConfirm() { confirmModal.value.onConfirm?.(); confirmModal.value.open = false; }
function handleCancel() { confirmModal.value.open = false; }

function deactivateStaff(id: string) {
  askConfirm(
    'Deactivate Staff Member',
    'This staff member will lose access to the application.',
    () => {
      deactivatingId.value = id;
      deactivateForm.patch(route('admin.settings.staff.deactivate', { user: id }), {
        onFinish: () => { deactivatingId.value = null; },
      });
    },
  );
}

function submitInvite() {
  inviteForm.post(route('admin.settings.staff.invite'), {
    onSuccess: () => inviteForm.reset(),
  });
}

function statusClass(status: string): string {
  const map: Record<string, string> = {
    active:         'bg-green-100 text-green-700',
    pending_invite: 'bg-amber-100 text-amber-700',
    suspended:      'bg-red-100 text-red-700',
  };
  return `text-xs px-2 py-0.5 rounded-full font-medium ${map[status] ?? 'bg-gray-100 text-gray-600'}`;
}
</script>
