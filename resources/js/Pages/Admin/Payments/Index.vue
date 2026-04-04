<template>
  <AdminLayout>
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Payments</h1>
          <p class="text-sm text-gray-500 mt-0.5">All transactions processed through PawPass</p>
        </div>
      </div>

      <!-- Filter tabs -->
      <div class="flex gap-1 border-b border-gray-200">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="setFilter(tab.value)"
          class="px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px"
          :class="currentStatus === tab.value
            ? 'border-indigo-600 text-indigo-600'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div v-if="payments.data.length === 0" class="px-6 py-16 text-center">
          <div class="text-gray-400 text-sm">No payments found</div>
        </div>

        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/70">
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Order</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Date</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Customer</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Description</th>
              <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Amount</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Status</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="payment in payments.data"
              :key="payment.id"
              class="hover:bg-gray-50/50 transition-colors"
            >
              <!-- Order ref -->
              <td class="px-5 py-3.5">
                <span class="font-mono text-sm font-semibold text-gray-800">{{ payment.short_ref }}</span>
                <span
                  v-if="payment.type === 'boarding'"
                  class="ml-2 text-xs px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 font-medium"
                >boarding</span>
              </td>

              <!-- Date -->
              <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">
                {{ formatDate(payment.paid_at ?? payment.created_at) }}
              </td>

              <!-- Customer -->
              <td class="px-5 py-3.5">
                <span class="font-medium text-gray-900">{{ payment.customer_name ?? '—' }}</span>
              </td>

              <!-- Description -->
              <td class="px-5 py-3.5 text-gray-600">{{ payment.description }}</td>

              <!-- Amount -->
              <td class="px-5 py-3.5 text-right font-semibold text-gray-900">
                {{ formatAmount(payment.amount_cents) }}
              </td>

              <!-- Status -->
              <td class="px-5 py-3.5">
                <span
                  class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full"
                  :class="statusClass(payment.status)"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(payment.status)"></span>
                  {{ payment.status }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-5 py-3.5">
                <div class="flex items-center justify-end gap-3">
                  <a
                    v-if="payment.status === 'paid' && payment.stripe_pi_id"
                    :href="route('admin.orders.receipt', { order: payment.order_id })"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors"
                    target="_blank"
                  >Receipt ↗</a>
                  <button
                    v-if="payment.status === 'paid'"
                    @click="openRefundModal(payment)"
                    class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors"
                  >Refund</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="payments.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
        <span>
          Showing {{ payments.from }}–{{ payments.to }} of {{ payments.total }}
        </span>
        <div class="flex gap-1">
          <Link
            v-for="link in payments.links"
            :key="link.label"
            :href="link.url ?? '#'"
            :class="[
              'px-3 py-1.5 rounded-lg border text-sm transition-colors',
              link.active
                ? 'bg-indigo-600 border-indigo-600 text-white font-medium'
                : link.url
                  ? 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                  : 'border-gray-100 bg-white text-gray-300 cursor-not-allowed pointer-events-none',
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- Refund confirmation modal -->
    <Teleport to="body">
      <div
        v-if="refundTarget"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="refundTarget = null"
      >
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="refundTarget = null"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="font-semibold text-gray-900 text-base">Refund this payment?</h3>
              <p class="text-sm text-gray-500 mt-1">
                {{ refundTarget?.customer_name ?? 'Customer' }} · {{ formatAmount(refundTarget?.amount_cents ?? 0) }}
                <span class="font-mono text-xs text-gray-400 ml-1">{{ refundTarget?.short_ref }}</span>
              </p>
              <p class="text-xs text-amber-600 mt-2 bg-amber-50 rounded-lg px-3 py-2">
                This will reverse the charge on Stripe and remove all remaining credits.
              </p>
            </div>
          </div>
          <div class="flex gap-3 mt-5">
            <button
              @click="refundTarget = null"
              class="flex-1 px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
            >Cancel</button>
            <button
              @click="confirmRefund"
              :disabled="refunding"
              class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 disabled:opacity-50 transition-colors"
            >
              {{ refunding ? 'Processing…' : 'Confirm Refund' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

type Payment = {
  id: string;
  order_id: string;
  short_ref: string;
  type: string;
  payment_type: string;
  stripe_pi_id: string | null;
  customer_name: string | null;
  description: string;
  amount_cents: number;
  status: string;
  paid_at: string | null;
  created_at: string;
  refunded_at: string | null;
};

type PaginatedPayments = {
  data: Payment[];
  from: number;
  to: number;
  total: number;
  last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
  payments: PaginatedPayments;
  filters: { status: string };
}>();

const tabs = [
  { label: 'All', value: '' },
  { label: 'Paid', value: 'paid' },
  { label: 'Authorized', value: 'authorized' },
  { label: 'Refunded', value: 'refunded' },
];

const currentStatus = computed(() => props.filters.status ?? '');

function setFilter(status: string) {
  router.get(route('admin.payments.index'), { status: status || undefined }, { preserveState: true, replace: true });
}

function formatAmount(cents: number): string {
  return '$' + (cents / 100).toFixed(2);
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function statusClass(status: string): string {
  const map: Record<string, string> = {
    paid: 'bg-emerald-50 text-emerald-700',
    refunded: 'bg-gray-100 text-gray-500',
    authorized: 'bg-amber-50 text-amber-700',
    pending: 'bg-gray-100 text-gray-500',
  };
  return map[status] ?? 'bg-gray-100 text-gray-500';
}

function statusDotClass(status: string): string {
  const map: Record<string, string> = {
    paid: 'bg-emerald-500',
    refunded: 'bg-gray-400',
    authorized: 'bg-amber-500',
    pending: 'bg-gray-400',
  };
  return map[status] ?? 'bg-gray-400';
}

// Refund modal
const refundTarget = ref<Payment | null>(null);
const refunding = ref(false);

function openRefundModal(payment: Payment) {
  refundTarget.value = payment;
}

function confirmRefund() {
  if (!refundTarget.value) return;
  refunding.value = true;
  useForm({}).post(route('admin.payments.refund', { order: refundTarget.value.order_id }), {
    onFinish: () => {
      refunding.value = false;
      refundTarget.value = null;
    },
  });
}
</script>
