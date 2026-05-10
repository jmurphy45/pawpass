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

      <!-- Outstanding Balances -->
      <AppCard class="overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-border">
          <div>
            <h2 class="text-sm font-semibold text-text-body">Outstanding Balances</h2>
            <p v-if="outstandingCount > 0" class="text-xs text-text-muted mt-0.5">
              {{ outstandingCount }} customer{{ outstandingCount === 1 ? '' : 's' }} with open balances
            </p>
          </div>
          <span v-if="outstandingTotal > 0" class="text-sm font-semibold text-red-600">{{ formatCurrency(outstandingTotal) }}</span>
        </div>
        <div v-if="outstandingCustomers.length === 0" class="px-5 py-6 text-sm text-text-muted">No outstanding balances.</div>
        <ul v-else>
          <li
            v-for="customer in outstandingCustomers"
            :key="customer.id"
            class="flex items-center border-b border-border-warm px-5 py-3 last:border-b-0 transition-colors hover:bg-surface"
          >
            <Link :href="route('admin.customers.show', customer.id)" class="flex-1 min-w-0 hover:underline">
              <p class="text-sm font-medium text-text-body truncate">{{ customer.name }}</p>
              <p v-if="customer.email" class="text-xs text-text-muted truncate">{{ customer.email }}</p>
            </Link>
            <span class="text-sm font-semibold text-red-600 mr-3">{{ formatCurrency(customer.outstanding_balance_cents) }}</span>
            <div class="shrink-0 w-20 text-right">
              <AppBadge v-if="customer.charge_pending_at" color="yellow">Pending</AppBadge>
              <AppBadge v-else-if="!customer.has_payment_method" color="gray">No card</AppBadge>
              <button
                v-else-if="isOwner"
                :disabled="charging[customer.id]"
                class="text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 px-2.5 py-1 rounded-md transition-colors"
                @click="chargeCustomer(customer)"
              >
                {{ charging[customer.id] ? '…' : 'Charge' }}
              </button>
            </div>
          </li>
        </ul>
        <div v-if="outstandingCustomers.length > 0" class="px-5 py-3 border-t border-border-warm">
          <Link :href="route('admin.customers.index')" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
            View all customers →
          </Link>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
  <AppModal :open="confirmModal.open" :title="confirmModal.title" :message="confirmModal.message" @confirm="handleConfirm" @cancel="handleCancel" />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import type { PageProps } from '@/types';

interface OnboardingStep {
  key: string;
  label: string;
  done: boolean;
  owner_only: boolean;
  route: string;
}

interface OutstandingCustomer {
  id: string;
  name: string;
  email: string | null;
  outstanding_balance_cents: number;
  has_payment_method: boolean;
  charge_pending_at: string | null;
}

const props = defineProps<{
  checkinsToday: number;
  customersCount: number;
  dogsCount: number;
  lowCreditDogs: Array<{ id: string; name: string; credit_balance: number; customer_name: string | null }>;
  recentAttendance: Array<{ id: string; dog_name: string | null; customer_name: string | null; checked_in_at: string; checked_out_at: string | null }>;
  onboarding: OnboardingStep[];
  outstandingTotal: number;
  outstandingCount: number;
  outstandingCustomers: OutstandingCustomer[];
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

function formatCurrency(cents: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100);
}

const charging = ref<Record<string, boolean>>({});

const confirmModal = ref<{ open: boolean; title: string; message: string; onConfirm: (() => void) | null }>
  ({ open: false, title: '', message: '', onConfirm: null });

function handleConfirm() { confirmModal.value.onConfirm?.(); confirmModal.value.open = false; }
function handleCancel() { confirmModal.value.open = false; }

function chargeCustomer(customer: OutstandingCustomer) {
  const label = customer.email ? `${customer.name} (${customer.email})` : customer.name;
  confirmModal.value = {
    open: true,
    title: 'Charge Outstanding Balance',
    message: `Charge ${formatCurrency(customer.outstanding_balance_cents)} to ${label}?`,
    onConfirm: () => {
      charging.value[customer.id] = true;
      router.post(route('admin.customers.charge-balance', customer.id), {}, {
        onFinish: () => { charging.value[customer.id] = false; },
      });
    },
  };
}
</script>
