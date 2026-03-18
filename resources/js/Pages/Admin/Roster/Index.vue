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
          <li v-for="dog in roster" :key="dog.id" class="list-row gap-3">
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
              <p class="text-xs text-text-muted truncate">{{ dog.customer_name }}</p>
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
        </ul>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
  roster: Array<{ id: string; name: string; customer_name: string | null; credit_balance: number; credit_status: string; attendance_state: string }>;
}>();

const checkedInCount = computed(() => props.roster.filter(d => d.attendance_state === 'checked_in').length);

const todayLabel = computed(() =>
  new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })
);

function checkin(dogId: string) {
  useForm({ dog_id: dogId }).post(route('admin.roster.checkin'));
}

function checkout(dogId: string) {
  useForm({ dog_id: dogId }).post(route('admin.roster.checkout'));
}
</script>
