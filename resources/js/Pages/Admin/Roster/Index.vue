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

      <!-- Capacity bar -->
      <div v-if="props.daily_dog_limit" class="bg-white rounded-xl border border-gray-200 px-5 py-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-text-body">Capacity — {{ props.today_dog_count }} of {{ props.daily_dog_limit }} dogs today</span>
          <AppBadge v-if="capacityPct >= 100" color="red">At capacity</AppBadge>
        </div>
        <div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="{
              'bg-green-500': capacityPct < 80,
              'bg-yellow-400': capacityPct >= 80 && capacityPct < 100,
              'bg-red-500': capacityPct >= 100,
            }"
            :style="{ width: Math.min(capacityPct, 100) + '%' }"
          />
        </div>
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
          <li
            v-for="dog in filteredRoster"
            :key="dog.id"
            class="flex items-center border-b border-border-warm px-5 py-3 transition-colors hover:bg-surface last:border-b-0 gap-3"
          >
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

            <!-- Dog info -->
            <div class="flex-1 min-w-0">
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
            <div class="flex items-center gap-2 shrink-0">
              <!-- Not yet in: just Check In -->
              <template v-if="dog.attendance_state === 'not_in'">
                <AppButton
                  variant="primary"
                  size="sm"
                  :loading="pendingDogId === dog.id"
                  @click="checkin(dog)"
                >Check In</AppButton>
              </template>

              <!-- Checked in: Add-on + Check Out -->
              <template v-if="dog.attendance_state === 'checked_in'">
                <AppButton
                  variant="secondary"
                  size="sm"
                  @click="openAddonModal(dog)"
                >
                  <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add-on
                    <span v-if="dog.attendance_addons.length > 0" class="ml-0.5 bg-indigo-100 text-indigo-700 rounded-full px-1.5 py-0 text-xs font-semibold leading-4">{{ dog.attendance_addons.length }}</span>
                  </span>
                </AppButton>
                <AppButton
                  variant="primary"
                  size="sm"
                  :loading="pendingDogId === dog.id"
                  @click="checkout(dog.id)"
                >Check Out</AppButton>
              </template>

              <!-- Done: view add-ons if any -->
              <template v-if="dog.attendance_state === 'done'">
                <AppButton
                  v-if="dog.attendance_addons.length > 0"
                  variant="secondary"
                  size="sm"
                  @click="openAddonModal(dog)"
                >
                  {{ dog.attendance_addons.length }} add-on{{ dog.attendance_addons.length !== 1 ? 's' : '' }}
                </AppButton>
                <div v-else class="w-20" />
              </template>

              <!-- Comments button (all states except not_in) -->
              <template v-if="dog.attendance_state !== 'not_in'">
                <AppButton
                  variant="secondary"
                  size="sm"
                  @click="openCommentModal(dog)"
                >
                  <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4z" />
                    </svg>
                    <span v-if="dog.attendance_comments.length > 0" class="ml-0.5 bg-amber-100 text-amber-700 rounded-full px-1.5 py-0 text-xs font-semibold leading-4">{{ dog.attendance_comments.length }}</span>
                  </span>
                </AppButton>
              </template>
            </div>
          </li>
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

    <!-- Comments modal -->
    <TransitionRoot as="template" :show="commentModal.open">
      <Dialog class="relative z-50" @close="commentModal.open = false">
        <TransitionChild
          as="template"
          enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
          leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black/50 transition-opacity" />
        </TransitionChild>

        <div class="fixed inset-0 z-10 flex items-end sm:items-center justify-center sm:p-4">
          <TransitionChild
            as="template"
            enter="ease-out duration-200" enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to="opacity-100 translate-y-0 sm:scale-100"
            leave="ease-in duration-150" leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <DialogPanel class="w-full sm:max-w-sm bg-white rounded-t-2xl sm:rounded-xl shadow-xl overflow-hidden">
              <!-- Header -->
              <div class="flex items-center justify-between px-5 pt-5 pb-3">
                <DialogTitle class="text-base font-semibold text-gray-900">
                  Comments — {{ commentModal.dogName }}
                </DialogTitle>
                <button @click="commentModal.open = false" class="text-gray-400 hover:text-gray-600 transition-colors p-1 -mr-1">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <div class="px-5 pb-5 space-y-4">
                <!-- Comment list -->
                <div v-if="commentModalComments.length > 0" class="space-y-2 max-h-60 overflow-y-auto">
                  <div
                    v-for="comment in commentModalComments"
                    :key="comment.id"
                    class="bg-gray-50 rounded-lg px-3 py-2"
                  >
                    <div class="flex items-start justify-between gap-2">
                      <p class="text-sm text-gray-800 flex-1">{{ comment.body }}</p>
                      <button
                        @click="removeComment(commentModal.attendanceId, comment.id)"
                        class="text-gray-300 hover:text-red-500 transition-colors shrink-0 mt-0.5"
                        title="Remove"
                      >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                      {{ comment.created_by?.name ?? 'Staff' }} · {{ formatCommentTime(comment.created_at) }}
                    </p>
                  </div>
                </div>
                <div v-else class="text-sm text-gray-400 text-center py-2">No comments yet.</div>

                <!-- Add comment -->
                <div class="space-y-2">
                  <textarea
                    v-model="commentModal.newBody"
                    rows="3"
                    placeholder="Add a note about this visit…"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 resize-none"
                  />
                  <AppButton
                    variant="primary"
                    size="sm"
                    class="w-full"
                    :loading="commentModal.saving"
                    :disabled="!commentModal.newBody.trim()"
                    @click="addComment"
                  >Add Comment</AppButton>
                </div>

                <button
                  @click="commentModal.open = false"
                  class="w-full px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-medium text-gray-700 transition-colors"
                >Done</button>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Add-on modal -->
    <TransitionRoot as="template" :show="addonModal.open">
      <Dialog class="relative z-50" @close="addonModal.open = false">
        <TransitionChild
          as="template"
          enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
          leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black/50 transition-opacity" />
        </TransitionChild>

        <div class="fixed inset-0 z-10 flex items-end sm:items-center justify-center sm:p-4">
          <TransitionChild
            as="template"
            enter="ease-out duration-200" enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to="opacity-100 translate-y-0 sm:scale-100"
            leave="ease-in duration-150" leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <DialogPanel class="w-full sm:max-w-sm bg-white rounded-t-2xl sm:rounded-xl shadow-xl overflow-hidden">
              <!-- Header -->
              <div class="flex items-center justify-between px-5 pt-5 pb-3">
                <DialogTitle class="text-base font-semibold text-gray-900">
                  Add-ons — {{ addonModal.dogName }}
                </DialogTitle>
                <button @click="addonModal.open = false" class="text-gray-400 hover:text-gray-600 transition-colors p-1 -mr-1">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <div class="px-5 pb-5 space-y-4">
                <!-- Existing add-ons -->
                <div v-if="addonModalCurrentAddons.length > 0">
                  <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Added</p>
                  <div class="space-y-1.5">
                    <div
                      v-for="addon in addonModalCurrentAddons"
                      :key="addon.id"
                      class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2"
                    >
                      <div>
                        <span class="text-sm font-medium text-gray-800">{{ addon.name }}</span>
                        <span class="ml-2 text-xs text-gray-400">{{ addon.quantity }} × ${{ (addon.unit_price_cents / 100).toFixed(2) }}</span>
                      </div>
                      <button
                        v-if="addonModal.state === 'checked_in'"
                        @click="removeAddon(addonModal.attendanceId, addon.id)"
                        class="text-red-400 hover:text-red-600 text-xs font-medium transition-colors ml-3"
                      >Remove</button>
                    </div>
                  </div>
                </div>

                <!-- Available add-on services (only when checked in) -->
                <div v-if="addonModal.state === 'checked_in' && addonTypes.length > 0">
                  <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Add a Service</p>
                  <div class="grid grid-cols-2 gap-2">
                    <button
                      v-for="at in addonTypes"
                      :key="at.id"
                      @click="addAddonFromModal(at.id)"
                      :disabled="addonModal.pendingAddonId === at.id"
                      class="flex flex-col items-center justify-center gap-0.5 rounded-xl border border-gray-200 bg-white px-3 py-3.5 text-center hover:border-indigo-400 hover:bg-indigo-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <span class="text-sm font-semibold text-gray-800">{{ at.name }}</span>
                      <span class="text-xs text-gray-500">${{ (at.price_cents / 100).toFixed(2) }}</span>
                    </button>
                  </div>
                </div>

                <div v-if="addonModal.state === 'checked_in' && addonTypes.length === 0 && addonModalCurrentAddons.length === 0" class="text-sm text-gray-400 text-center py-2">
                  No add-on services configured.
                </div>

                <!-- Done state: view only -->
                <div v-if="addonModal.state === 'done' && addonModalCurrentAddons.length === 0" class="text-sm text-gray-400 text-center py-2">
                  No add-ons recorded.
                </div>

                <button
                  @click="addonModal.open = false"
                  class="w-full mt-1 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-medium text-gray-700 transition-colors"
                >Done</button>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </Dialog>
    </TransitionRoot>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onUnmounted, reactive, ref } from 'vue';
import { router, usePoll } from '@inertiajs/vue3';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
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

interface CommentEntry {
  id: number;
  body: string;
  is_public: boolean;
  created_at: string | null;
  created_by: { id: string; name: string } | null;
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
  attendance_comments: CommentEntry[];
}

const props = defineProps<{
  roster: RosterDog[];
  addonTypes: AddonType[];
  daily_dog_limit: number | null;
  today_dog_count: number;
}>();

const capacityPct = computed(() => props.daily_dog_limit ? Math.round((props.today_dog_count / props.daily_dog_limit) * 100) : 0);

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

// Comment modal state
const commentModal = reactive({
  open: false,
  dogId: '',
  dogName: '',
  attendanceId: '',
  newBody: '',
  saving: false,
});

// Derive current comments from live roster props
const commentModalComments = computed<CommentEntry[]>(() => {
  if (!commentModal.open) return [];
  return props.roster.find(d => d.id === commentModal.dogId)?.attendance_comments ?? [];
});

// Add-on modal state
const addonModal = reactive({
  open: false,
  dogId: '',
  dogName: '',
  attendanceId: '',
  state: '' as 'checked_in' | 'done' | '',
  pendingAddonId: null as string | null,
});

// Derive current add-ons from live roster props so partial reloads update the modal
const addonModalCurrentAddons = computed<AddonEntry[]>(() => {
  if (!addonModal.open) return [];
  return props.roster.find(d => d.id === addonModal.dogId)?.attendance_addons ?? [];
});

// Live clock for "in for X" durations — ticks every 60s
const now = ref(new Date());
const clockTimer = setInterval(() => { now.value = new Date(); }, 60_000);
onUnmounted(() => clearInterval(clockTimer));

// Auto-refresh roster every 60 seconds
usePoll(60_000, { only: ['roster', 'addonTypes', 'today_dog_count'] });

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

function checkinDuration(checkedInAt: string): string {
  const diffMs = now.value.getTime() - new Date(checkedInAt).getTime();
  const totalMins = Math.max(0, Math.floor(diffMs / 60_000));
  const hours = Math.floor(totalMins / 60);
  const mins = totalMins % 60;
  if (hours > 0) return `${hours}h ${mins}m`;
  return `${mins}m`;
}

function checkin(dog: RosterDog) {
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
      only: ['roster', 'addonTypes', 'flash', 'today_dog_count'],
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
      only: ['roster', 'addonTypes', 'flash', 'today_dog_count'],
      onFinish: () => { pendingDogId.value = null; },
    },
  );
}

function openAddonModal(dog: RosterDog) {
  addonModal.open = true;
  addonModal.dogId = dog.id;
  addonModal.dogName = dog.name;
  addonModal.attendanceId = dog.attendance_id ?? '';
  addonModal.state = dog.attendance_state as 'checked_in' | 'done';
  addonModal.pendingAddonId = null;
}

function addAddonFromModal(addonTypeId: string) {
  if (!addonModal.attendanceId) return;
  addonModal.pendingAddonId = addonTypeId;
  router.post(
    route('admin.roster.attendance-addons.store', addonModal.attendanceId),
    { addon_type_id: addonTypeId },
    {
      preserveScroll: true,
      only: ['roster', 'addonTypes', 'flash', 'today_dog_count'],
      onFinish: () => { addonModal.pendingAddonId = null; },
    },
  );
}

function removeAddon(attendanceId: string, addonId: number) {
  router.delete(
    route('admin.roster.attendance-addons.destroy', { attendance: attendanceId, addon: addonId }),
    {
      preserveScroll: true,
      only: ['roster', 'addonTypes', 'flash', 'today_dog_count'],
    },
  );
}

function openCommentModal(dog: RosterDog) {
  commentModal.open = true;
  commentModal.dogId = dog.id;
  commentModal.dogName = dog.name;
  commentModal.attendanceId = dog.attendance_id ?? '';
  commentModal.newBody = '';
  commentModal.saving = false;
}

function addComment() {
  if (!commentModal.attendanceId || !commentModal.newBody.trim()) return;
  commentModal.saving = true;
  router.post(
    route('admin.roster.attendance-comments.store', commentModal.attendanceId),
    { body: commentModal.newBody.trim() },
    {
      preserveScroll: true,
      only: ['roster', 'flash'],
      onSuccess: () => { commentModal.newBody = ''; },
      onFinish: () => { commentModal.saving = false; },
    },
  );
}

function removeComment(attendanceId: string, commentId: number) {
  router.delete(
    route('admin.roster.attendance-comments.destroy', { attendance: attendanceId, comment: commentId }),
    {
      preserveScroll: true,
      only: ['roster', 'flash'],
    },
  );
}

function formatCommentTime(iso: string | null): string {
  if (!iso) return '';
  return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>
