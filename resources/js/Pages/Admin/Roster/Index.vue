<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Roster</h1>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="dog in roster" :key="dog.id" class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ dog.name }}</p>
            <p class="text-xs text-gray-500">{{ dog.customer_name }} · {{ dog.credit_balance }} credits</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs px-2 py-0.5 rounded-full" :class="{
              'bg-green-100 text-green-700': dog.attendance_state === 'checked_in',
              'bg-gray-100 text-gray-600': dog.attendance_state === 'not_in',
              'bg-blue-100 text-blue-700': dog.attendance_state === 'done',
            }">{{ dog.attendance_state }}</span>
            <form v-if="dog.attendance_state === 'not_in'" @submit.prevent="checkin(dog.id)">
              <button type="submit" class="text-xs bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700">Check In</button>
            </form>
            <form v-if="dog.attendance_state === 'checked_in'" @submit.prevent="checkout(dog.id)">
              <button type="submit" class="text-xs bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700">Check Out</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineProps<{
  roster: Array<{ id: string; name: string; customer_name: string | null; credit_balance: number; credit_status: string; attendance_state: string }>;
}>();

function checkin(dogId: string) {
  useForm({ dog_id: dogId }).post(route('admin.roster.checkin'));
}

function checkout(dogId: string) {
  useForm({ dog_id: dogId }).post(route('admin.roster.checkout'));
}
</script>
