<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Today's Roster</h1>
          <p class="text-sm text-text-muted mt-0.5">{{ checkedInCount }} of {{ roster.length }} checked in</p>
        </div>
        <AppBadge color="gray" class="self-start sm:self-auto">{{ todayLabel }}</AppBadge>
      </div>

      <!-- Search + Tab Filters -->
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
          <AppInput v-model="searchQuery" placeholder="Search by dog or customer name…" />
        </div>
        <div class="flex gap-1 flex-wrap">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            @click="activeTab = tab.value"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
              activeTab === tab.value
                ? 'bg-indigo-600 text-white'
                : 'bg-surface-subtle text-text-muted hover:text-text-body',
            ]"
          >{{ tab.label }}</button>
        </div>
      </div>

      <!-- Roster list -->
      <AppCard class="overflow-hidden">
        <div v-if="filteredRoster.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          {{ roster.length === 0 ? 'No dogs on the roster today.' : 'No dogs match your search.' }}
        </div>
        <ul v-else>
          <template v-for="dog in filteredRoster" :key="dog.id">
            <!-- Main row -->
            <li class="flex items-center border-b border-border-warm px-5 py-3 transition-colors hover:bg-surface last:border-b-0 gap-3">
              <!-- Avatar with status dot -->
              <div class="relative shrink-0">
                <div class="h-9 w-9 rounded-full bg-surface-subtle flex items-center justify-center text-sm font-semibold text-text-body">
                  {{ dog.name[0]?.toUpperCase() }}
                </div>
                <span
                  class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white"
                  :class="{
                    'bg-green-500': dog.attendance_state === 'checked_in',
                    'bg-gray-300': dog.attendance_state === 'not_in',
                    'bg-blue-400': dog.attendance_state === 'done',
                  }"
                />
              </div>

              <!-- Dog info — clickable to expand addons if checked in -->
              <div
                class="flex-1 min-w-0"
                :class="{ 'cursor-pointer': dog.attendance_state !== 'not_in' }"
                @click="dog.attendance_state !== 'not_in' ? toggleExpand(dog.id) : null"
              >
                <p class="text-sm font-medium text-text-body truncate">{{ dog.name }}</p>
                <p class="text-xs text-text-muted truncate">
                  {{ dog.customer_name }}
                  <span v-if="dog.attendance_state === 'checked_in' && dog.checked_in_at" class="ml-1 text-green-600 font-medium">
                    · In for {{ checkinDuration(dog.checked_in_at) }}
                  </span>
                  <span v-else-if="dog.attendance_addons.length > 0" class="ml-1 text-indigo-500">
                    · {{ dog.attendance_addons.length }} add-on{{ dog.attendance_addons.length !== 1 ? 's' : '' }}
                  </span>
                </p>
              </div>

              <!-- Credit badge (hidden on mobile) -->
              <AppBadge
                class="hidden sm:inline-flex"
                :color="dog.credit_balance <= 0 && !dog.unlimited_pass_active ? 'red' : dog.credit_status === 'low' ? 'yellow' : 'gray'"
              >
                <span v-if="dog.unlimited_pass_active">Unlimited</span>
                <span v-else>{{ dog.credit_balance }} cr</span>
              </AppBadge>

              <!-- Status badge -->
              <AppBadge :color="dog.attendance_state === 'checked_in' ? 'green' : dog.attendance_state === 'done' ? 'blue' : 'gray'">
                {{ dog.attendance_state === 'checked_in' ? 'In' : dog.attendance_state === 'done' ? 'Done' : 'Out' }}
              </AppBadge>

              <!-- Action buttons -->
              <div v-if="dog.attendance_state === 'not_in'">
                <AppButton
                  variant="primary"
                  size="sm"
                  :loading="pendingDogId === dog.id"
                  @click="checkin(dog)"
                >Check In</AppButton>
              </div>
              <div v-if="dog.attendance_state === 'checked_in'">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :loading="pendingDogId === dog.id"
                  @click="checkout(dog.id)"
                >Check Out</AppButton>
              </div>
              <div v-if="dog.attendance_state === 'done'" class="w-20" />
            </li>

            <!-- Expanded add-on panel -->
            <li
              v-if="expandedDogId === dog.id && dog.attendance_state !== 'not_in'"
              class="border-t border-border bg-surface-subtle px-5 py-3 space-y-2"
            >
              <p class="text-xs font-semibold text-text-muted uppercase tracking-wide">Add-on Services</p>

              <!-- Existing addons -->
              <div v-if="dog.attendance_addons.length === 0" class="text-xs text-text-muted">No add-ons yet.</div>
              <div
                v-for="addon in dog.attendance_addons"
                :key="addon.id"
                class="flex items-center justify-between text-sm"
              >
                <span class="text-text-body">{{ addon.name }}</span>
                <div class="flex items-center gap-3">
                  <span class="text-text-muted text-xs">{{ addon.quantity }} × ${{ (addon.unit_price_cents / 100).toFixed(2) }}</span>
                  <button
                    v-if="dog.attendance_state === 'checked_in'"
                    @click="removeAddon(dog.attendance_id!, addon.id)"
                    class="text-red-400 hover:text-red-600 text-xs"
                    title="Remove"
                  >×</button>
                </div>
              </div>

              <!-- Add addon form (only when still checked in) -->
              <form
                v-if="dog.attendance_state === 'checked_in'"
                @submit.prevent="addAddon(dog.attendance_id!, dog.id)"
                class="flex gap-2 pt-1 border-t border-border"
              >
                <select v-model="addonSelections[dog.id]" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 flex-1 py-1 text-xs">
                  <option value="">Select add-on…</option>
                  <option v-for="at in addonTypes" :key="at.id" :value="at.id">
                    {{ at.name }} (${{ (at.price_cents / 100).toFixed(2) }})
                  </option>
                </select>
                <AppButton
                  type="submit"
                  variant="primary"
                  size="sm"
                  :disabled="!addonSelections[dog.id]"
                >Add</AppButton>
              </form>
            </li>
          </template>
        </ul>
      </AppCard>
    </div>

    <!-- Zero-credit override modal -->
    <AppModal
      :open="overrideModal.open"
      title="No Credits Remaining"
      confirm-label="Override & Check In"
      cancel-label="Cancel"
      :danger="false"
      @confirm="confirmOverride"
      @cancel="cancelOverride"
    >
      <p class="text-sm text-gray-600 mb-3">
        <strong>{{ overrideModal.dogName }}</strong> has {{ overrideModal.creditBalance }} credit{{ overrideModal.creditBalance === 1 ? '' : 's' }}.
        Enter a reason to check in anyway:
      </p>
      <textarea
        v-model="overrideModal.note"
        rows="3"
        placeholder="Reason for override…"
        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 resize-none"
      />
    </AppModal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onUnmounted, reactive, ref } from 'vue';
import { router, usePoll } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppInput from '@/Components/AppInput.vue';
import AppModal from '@/Components/AppModal.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppCard from '@/Components/AppCard.vue';
import AppButton from '@/Components/AppButton.vue';

interface AddonEntry {
  id: number;
  name: string;
  quantity: number;
  unit_price_cents: number;
}

interface AddonType {
  id: string;
  name: string;
  price_cents: number;
}

interface RosterDog {
  id: string;
  name: string;
  customer_name: string | null;
  credit_balance: number;
  credit_status: string;
  attendance_state: string;
  attendance_id: string | null;
  checked_in_at: string | null;
  unlimited_pass_active: boolean;
  attendance_addons: AddonEntry[];
}

const props = defineProps<{
  roster: RosterDog[];
  addonTypes: AddonType[];
}>();

const expandedDogId = ref<string | null>(null);
const addonSelections = reactive<Record<string, string>>({});
const searchQuery = ref('');
const activeTab = ref<'all' | 'checked_in' | 'not_in' | 'done'>('all');
const pendingDogId = ref<string | null>(null);

// Override modal state
const overrideModal = reactive({
  open: false,
  dogId: '',
  dogName: '',
  creditBalance: 0,
  note: '',
});

// Live clock for "in for X" durations — ticks every 60s
const now = ref(new Date());
const clockTimer = setInterval(() => { now.value = new Date(); }, 60_000);
onUnmounted(() => clearInterval(clockTimer));

// Auto-refresh roster every 60 seconds using Inertia's built-in polling
usePoll(60_000, { only: ['roster', 'addonTypes'] });

const tabs = [
  { value: 'all', label: 'All' },
  { value: 'checked_in', label: 'In' },
  { value: 'not_in', label: 'Out' },
  { value: 'done', label: 'Done' },
] as const;

const stateOrder: Record<string, number> = { checked_in: 0, not_in: 1, done: 2 };

const filteredRoster = computed(() => {
  const q = searchQuery.value.toLowerCase();

  return [...props.roster]
    .filter(dog => {
      if (activeTab.value !== 'all' && dog.attendance_state !== activeTab.value) return false;
      if (q) {
        const name = dog.name.toLowerCase();
        const customer = (dog.customer_name ?? '').toLowerCase();
        if (!name.includes(q) && !customer.includes(q)) return false;
      }
      return true;
    })
    .sort((a, b) => (stateOrder[a.attendance_state] ?? 1) - (stateOrder[b.attendance_state] ?? 1));
});

const checkedInCount = computed(() => props.roster.filter(d => d.attendance_state === 'checked_in').length);

const todayLabel = computed(() =>
  new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })
);

function toggleExpand(dogId: string) {
  expandedDogId.value = expandedDogId.value === dogId ? null : dogId;
}

function checkinDuration(checkedInAt: string): string {
  const diffMs = now.value.getTime() - new Date(checkedInAt).getTime();
  const totalMins = Math.max(0, Math.floor(diffMs / 60_000));
  const hours = Math.floor(totalMins / 60);
  const mins = totalMins % 60;
  if (hours > 0) return `${hours}h ${mins}m`;
  return `${mins}m`;
}

function checkin(dog: RosterDog) {
  // Show override modal for zero-credit dogs (unlimited pass exempt)
  if (dog.credit_balance <= 0 && !dog.unlimited_pass_active) {
    overrideModal.open = true;
    overrideModal.dogId = dog.id;
    overrideModal.dogName = dog.name;
    overrideModal.creditBalance = dog.credit_balance;
    overrideModal.note = '';
    return;
  }
  doCheckin(dog.id);
}

function doCheckin(dogId: string, override = false, note = '') {
  pendingDogId.value = dogId;
  router.post(
    route('admin.roster.checkin'),
    { dog_id: dogId, zero_credit_override: override, override_note: note || undefined },
    {
      preserveScroll: true,
      only: ['roster', 'addonTypes'],
      onFinish: () => { pendingDogId.value = null; },
    },
  );
}

function confirmOverride() {
  const { dogId, note } = overrideModal;
  overrideModal.open = false;
  doCheckin(dogId, true, note);
}

function cancelOverride() {
  overrideModal.open = false;
}

function checkout(dogId: string) {
  pendingDogId.value = dogId;
  router.post(
    route('admin.roster.checkout'),
    { dog_id: dogId },
    {
      preserveScroll: true,
      only: ['roster', 'addonTypes'],
      onFinish: () => { pendingDogId.value = null; },
    },
  );
}

function addAddon(attendanceId: string, dogId: string) {
  const addonTypeId = addonSelections[dogId];
  if (!addonTypeId) return;

  router.post(route('admin.roster.attendance-addons.store', attendanceId), {
    addon_type_id: addonTypeId,
  }, {
    preserveScroll: true,
    only: ['roster', 'addonTypes'],
    onSuccess: () => { addonSelections[dogId] = ''; },
  });
}

function removeAddon(attendanceId: string, addonId: number) {
  router.delete(route('admin.roster.attendance-addons.destroy', {
    attendance: attendanceId,
    addon: addonId,
  }), {
    preserveScroll: true,
    only: ['roster', 'addonTypes'],
  });
}
</script>
