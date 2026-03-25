<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Today's Roster</h1>
          <p class="text-sm text-text-muted mt-0.5">{{ checkedInCount }} of {{ roster.length }} checked in</p>
        </div>
        <span class="inline-flex items-center badge badge-gray self-start sm:self-auto">{{ todayLabel }}</span>
      </div>

      <!-- Roster list -->
      <div class="card overflow-hidden">
        <div v-if="roster.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No dogs on the roster today.
        </div>
        <ul v-else>
          <template v-for="dog in roster" :key="dog.id">
            <!-- Main row -->
            <li class="list-row gap-3">
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
                  <span v-if="dog.attendance_addons.length > 0" class="ml-1 text-indigo-500">
                    · {{ dog.attendance_addons.length }} add-on{{ dog.attendance_addons.length !== 1 ? 's' : '' }}
                  </span>
                </p>
              </div>

              <!-- Credit badge (hidden on mobile) -->
              <span
                class="hidden sm:inline-flex badge"
                :class="{
                  'badge-red': dog.credit_balance <= 0,
                  'badge-yellow': dog.credit_status === 'low' && dog.credit_balance > 0,
                  'badge-gray': dog.credit_status === 'ok',
                }"
              >{{ dog.credit_balance }} cr</span>

              <!-- Status badge -->
              <span class="badge" :class="{
                'badge-green': dog.attendance_state === 'checked_in',
                'badge-gray': dog.attendance_state === 'not_in',
                'badge-blue': dog.attendance_state === 'done',
              }">
                {{ dog.attendance_state === 'checked_in' ? 'In' : dog.attendance_state === 'done' ? 'Done' : 'Out' }}
              </span>

              <!-- Action buttons -->
              <form v-if="dog.attendance_state === 'not_in'" @submit.prevent="checkin(dog.id)">
                <button type="submit" class="btn-primary text-xs py-1 px-3">Check In</button>
              </form>
              <form v-if="dog.attendance_state === 'checked_in'" @submit.prevent="checkout(dog.id)">
                <button type="submit" class="btn-secondary text-xs py-1 px-3">Check Out</button>
              </form>
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
                @submit.prevent="addAddon(dog.attendance_id!)"
                class="flex gap-2 pt-1 border-t border-border"
              >
                <select v-model="addonSelections[dog.id]" class="input text-xs flex-1 py-1">
                  <option value="">Select add-on…</option>
                  <option v-for="at in addonTypes" :key="at.id" :value="at.id">
                    {{ at.name }} (${{ (at.price_cents / 100).toFixed(2) }})
                  </option>
                </select>
                <button
                  type="submit"
                  :disabled="!addonSelections[dog.id]"
                  class="btn-primary text-xs py-1 px-3"
                >Add</button>
              </form>
            </li>
          </template>
        </ul>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
  attendance_addons: AddonEntry[];
}

const props = defineProps<{
  roster: RosterDog[];
  addonTypes: AddonType[];
}>();

const expandedDogId = ref<string | null>(null);
const addonSelections = reactive<Record<string, string>>({});

const checkedInCount = computed(() => props.roster.filter(d => d.attendance_state === 'checked_in').length);

const todayLabel = computed(() =>
  new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })
);

function toggleExpand(dogId: string) {
  expandedDogId.value = expandedDogId.value === dogId ? null : dogId;
}

function checkin(dogId: string) {
  useForm({ dog_id: dogId }).post(route('admin.roster.checkin'));
}

function checkout(dogId: string) {
  useForm({ dog_id: dogId }).post(route('admin.roster.checkout'));
}

function addAddon(attendanceId: string) {
  const dogId = Object.keys(addonSelections).find(id =>
    props.roster.find(d => d.id === id)?.attendance_id === attendanceId
  );
  const addonTypeId = dogId ? addonSelections[dogId] : '';
  if (!addonTypeId) return;

  router.post(route('admin.roster.attendance-addons.store', attendanceId), {
    addon_type_id: addonTypeId,
  }, {
    onSuccess: () => {
      if (dogId) addonSelections[dogId] = '';
    },
  });
}

function removeAddon(attendanceId: string, addonId: number) {
  router.delete(route('admin.roster.attendance-addons.destroy', {
    attendance: attendanceId,
    addon: addonId,
  }));
}
</script>
