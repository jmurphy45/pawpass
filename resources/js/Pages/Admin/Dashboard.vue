<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-sm text-gray-500">Checked In Today</p>
          <p class="text-3xl font-bold text-gray-900 mt-1">{{ checkinsToday }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-sm text-gray-500">Total Customers</p>
          <p class="text-3xl font-bold text-gray-900 mt-1">{{ customersCount }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-sm text-gray-500">Total Dogs</p>
          <p class="text-3xl font-bold text-gray-900 mt-1">{{ dogsCount }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Low Credit Dogs -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-base font-semibold text-gray-900 mb-4">Low Credit Dogs</h2>
          <div v-if="lowCreditDogs.length === 0" class="text-sm text-gray-500">No low credit dogs.</div>
          <ul v-else class="divide-y divide-gray-100">
            <li v-for="dog in lowCreditDogs" :key="dog.id" class="py-2 flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ dog.name }}</p>
                <p class="text-xs text-gray-500">{{ dog.customer_name }}</p>
              </div>
              <span class="text-sm font-semibold" :class="dog.credit_balance <= 0 ? 'text-red-600' : 'text-amber-600'">
                {{ dog.credit_balance }} credits
              </span>
            </li>
          </ul>
        </div>

        <!-- Recent Attendance -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-base font-semibold text-gray-900 mb-4">Recent Attendance</h2>
          <div v-if="recentAttendance.length === 0" class="text-sm text-gray-500">No recent attendance.</div>
          <ul v-else class="divide-y divide-gray-100">
            <li v-for="entry in recentAttendance" :key="entry.id" class="py-2">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ entry.dog_name }}</p>
                  <p class="text-xs text-gray-500">{{ entry.customer_name }}</p>
                </div>
                <span class="text-xs text-gray-500">
                  {{ entry.checked_out_at ? 'Checked out' : 'Checked in' }}
                </span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps<{
  checkinsToday: number;
  customersCount: number;
  dogsCount: number;
  lowCreditDogs: Array<{ id: string; name: string; credit_balance: number; customer_name: string | null }>;
  recentAttendance: Array<{ id: string; dog_name: string | null; customer_name: string | null; checked_in_at: string; checked_out_at: string | null }>;
}>();
</script>
