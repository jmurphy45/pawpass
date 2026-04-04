<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-text-body">Dashboard</h1>

      <!-- Onboarding checklist -->
      <div v-if="visibleSteps.length > 0 && !dismissed" class="bg-white rounded-xl border border-border-warm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-border-warm">
          <div>
            <h2 class="text-sm font-semibold text-text-body">Getting Started</h2>
            <p class="text-xs text-text-muted mt-0.5">{{ completedCount }} of {{ visibleSteps.length }} steps complete</p>
          </div>
          <button @click="dismiss" class="text-text-muted hover:text-text-body transition-colors p-1 -mr-1">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <!-- Progress bar -->
        <div class="h-1 bg-surface-subtle">
          <div
            class="h-1 bg-indigo-500 transition-all duration-500"
            :style="{ width: `${(completedCount / visibleSteps.length) * 100}%` }"
          />
        </div>
        <ul class="divide-y divide-border-warm">
          <li v-for="step in visibleSteps" :key="step.key" class="flex items-center gap-3 px-5 py-3.5">
            <!-- Status icon -->
            <div v-if="step.done" class="h-5 w-5 rounded-full bg-green-100 flex items-center justify-center shrink-0">
              <svg class="h-3 w-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
              </svg>
            </div>
            <div v-else class="h-5 w-5 rounded-full border-2 border-border-warm shrink-0" />
            <!-- Label -->
            <span class="flex-1 text-sm" :class="step.done ? 'text-text-muted line-through' : 'text-text-body'">{{ step.label }}</span>
            <!-- Action link -->
            <Link
              v-if="!step.done"
              :href="route(step.route)"
              class="text-xs font-medium text-indigo-600 hover:text-indigo-800 shrink-0"
            >
              Go →
            </Link>
          </li>
        </ul>
      </div>

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
          <div class="flex items-center justify-between px-5 py-4 border-b border-border">
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
              <span class="badge ml-3" :class="dog.credit_balance <= 0 ? 'badge-red' : 'badge-yellow'">
                {{ dog.credit_balance }} cr
              </span>
            </li>
          </ul>
        </div>

        <!-- Recent Attendance -->
        <div class="card overflow-hidden">
          <div class="px-5 py-4 border-b border-border">
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
              <span class="text-xs font-medium" :class="entry.checked_out_at ? 'text-blue-600' : 'text-green-600'">
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
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import type { PageProps } from '@/types';

interface OnboardingStep {
  key: string;
  label: string;
  done: boolean;
  owner_only: boolean;
  route: string;
}

const props = defineProps<{
  checkinsToday: number;
  customersCount: number;
  dogsCount: number;
  lowCreditDogs: Array<{ id: string; name: string; credit_balance: number; customer_name: string | null }>;
  recentAttendance: Array<{ id: string; dog_name: string | null; customer_name: string | null; checked_in_at: string; checked_out_at: string | null }>;
  onboarding: OnboardingStep[];
}>();

const page = usePage<PageProps>();
const isOwner = computed(() => page.props.auth?.user?.role === 'business_owner');

const dismissed = ref(localStorage.getItem('onboarding_dismissed') === '1');

function dismiss() {
  localStorage.setItem('onboarding_dismissed', '1');
  dismissed.value = true;
}

const visibleSteps = computed(() =>
  props.onboarding.filter(s => !s.owner_only || isOwner.value)
);

const completedCount = computed(() => visibleSteps.value.filter(s => s.done).length);
</script>
