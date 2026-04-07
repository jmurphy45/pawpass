<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-text-body">Dashboard</h1>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <AppStatCard label="Checked In Today" :value="checkinsToday" />
        <AppStatCard label="Total Customers" :value="customersCount" />
        <AppStatCard label="Total Dogs" :value="dogsCount" />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Low Credit Dogs -->
        <AppCard class="overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-border">
            <h2 class="text-sm font-semibold text-text-body">Low Credit Dogs</h2>
            <AppBadge v-if="lowCreditDogs.length > 0" color="red">{{ lowCreditDogs.length }}</AppBadge>
          </div>
          <div v-if="lowCreditDogs.length === 0" class="px-5 py-6 text-sm text-text-muted">No low credit dogs.</div>
          <ul v-else>
            <li v-for="dog in lowCreditDogs" :key="dog.id" class="flex items-center border-b border-border-warm px-5 py-3 transition-colors hover:bg-surface last:border-b-0">
              <div class="h-8 w-8 rounded-full bg-surface-subtle flex items-center justify-center text-sm font-semibold text-text-body shrink-0 mr-3">
                {{ dog.name[0]?.toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-text-body truncate">{{ dog.name }}</p>
                <p class="text-xs text-text-muted truncate">{{ dog.customer_name }}</p>
              </div>
              <AppBadge class="ml-3" :color="dog.credit_balance <= 0 ? 'red' : 'yellow'">
                {{ dog.credit_balance }} cr
              </AppBadge>
            </li>
          </ul>
        </AppCard>

        <!-- Recent Attendance -->
        <AppCard class="overflow-hidden">
          <div class="px-5 py-4 border-b border-border">
            <h2 class="text-sm font-semibold text-text-body">Recent Attendance</h2>
          </div>
          <div v-if="recentAttendance.length === 0" class="px-5 py-6 text-sm text-text-muted">No recent attendance.</div>
          <ul v-else>
            <li v-for="entry in recentAttendance" :key="entry.id" class="flex items-center border-b border-border-warm px-5 py-3 transition-colors hover:bg-surface last:border-b-0">
              <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-semibold shrink-0 mr-3"
                :class="entry.checked_out_at ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
              >
                {{ (entry.dog_name ?? '?')[0]?.toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-text-body truncate">{{ entry.dog_name }}</p>
                <p class="text-xs text-text-muted truncate">{{ entry.customer_name }}</p>
              </div>
              <span class="text-xs font-medium" :class="entry.checked_out_at ? 'text-blue-600' : 'text-green-600'">
                {{ entry.checked_out_at ? 'Out' : 'In' }}
              </span>
            </li>
          </ul>
        </AppCard>
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
