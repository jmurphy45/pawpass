<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-text-body">Dashboard</h1>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="stat-card">
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
              <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-medium text-text-muted uppercase tracking-wide">Checked In Today</p>
              <p class="text-3xl font-bold text-text-body leading-tight">{{ checkinsToday }}</p>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
              <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-medium text-text-muted uppercase tracking-wide">Total Customers</p>
              <p class="text-3xl font-bold text-text-body leading-tight">{{ customersCount }}</p>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
              <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-medium text-text-muted uppercase tracking-wide">Total Dogs</p>
              <p class="text-3xl font-bold text-text-body leading-tight">{{ dogsCount }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Low Credit Dogs -->
        <div class="card overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid #e5e0d8;">
            <h2 class="text-sm font-semibold text-text-body">Low Credit Dogs</h2>
            <span v-if="lowCreditDogs.length > 0" class="badge badge-red">{{ lowCreditDogs.length }}</span>
          </div>
          <div v-if="lowCreditDogs.length === 0" class="px-5 py-6 text-sm text-text-muted">No low credit dogs.</div>
          <ul v-else>
            <li v-for="dog in lowCreditDogs" :key="dog.id" class="list-row">
              <div class="h-8 w-8 rounded-full bg-surface-subtle flex items-center justify-center text-sm font-semibold text-text-body shrink-0 mr-3">
                {{ dog.name[0]?.toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-text-body truncate">{{ dog.name }}</p>
                <p class="text-xs text-text-muted truncate">{{ dog.customer_name }}</p>
              </div>
              <span class="badge ml-3 shrink-0" :class="dog.credit_balance <= 0 ? 'badge-red' : 'badge-yellow'">
                {{ dog.credit_balance }} cr
              </span>
            </li>
          </ul>
        </div>

        <!-- Recent Attendance -->
        <div class="card overflow-hidden">
          <div class="px-5 py-4" style="border-bottom: 1px solid #e5e0d8;">
            <h2 class="text-sm font-semibold text-text-body">Recent Attendance</h2>
          </div>
          <div v-if="recentAttendance.length === 0" class="px-5 py-6 text-sm text-text-muted">No recent attendance.</div>
          <ul v-else>
            <li v-for="entry in recentAttendance" :key="entry.id" class="list-row">
              <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-semibold shrink-0 mr-3"
                :class="entry.checked_out_at ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
              >
                {{ (entry.dog_name ?? '?')[0]?.toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-text-body truncate">{{ entry.dog_name }}</p>
                <p class="text-xs text-text-muted truncate">{{ entry.customer_name }}</p>
              </div>
              <span class="text-xs font-medium shrink-0" :class="entry.checked_out_at ? 'text-blue-600' : 'text-green-600'">
                {{ entry.checked_out_at ? 'Out' : 'In' }}
              </span>
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
