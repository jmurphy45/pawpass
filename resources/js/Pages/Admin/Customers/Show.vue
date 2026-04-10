<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-text-body">{{ customer.name }}</h1>
          <p class="text-sm text-text-muted mt-0.5">Member since {{ formatDate(customer.created_at) }}</p>
        </div>
        <span
          v-if="customer.has_portal"
          class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20"
        >Portal access</span>
      </div>

      <!-- Stats bar -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-border-warm p-4">
          <p class="text-xs text-text-muted uppercase tracking-wide">Dogs</p>
          <p class="text-2xl font-bold text-text-body mt-1">{{ dogs.length }}</p>
        </div>
        <div class="bg-white rounded-xl border border-border-warm p-4">
          <p class="text-xs text-text-muted uppercase tracking-wide">Orders</p>
          <p class="text-2xl font-bold text-text-body mt-1">{{ customer.total_orders }}</p>
        </div>
        <div class="bg-white rounded-xl border border-border-warm p-4">
          <p class="text-xs text-text-muted uppercase tracking-wide">Total Spent</p>
          <p class="text-2xl font-bold text-text-body mt-1">{{ formatMoney(customer.total_spent) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-border-warm p-4">
          <p class="text-xs text-text-muted uppercase tracking-wide">Credits</p>
          <p class="text-2xl font-bold text-text-body mt-1">{{ customer.total_credits }}</p>
        </div>
      </div>

      <!-- Customer info -->
      <div class="bg-white rounded-xl border border-border-warm p-5">
        <h2 class="text-sm font-semibold text-text-body mb-4">Contact & Account</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
          <div>
            <p class="text-xs text-text-muted">Email</p>
            <p class="text-sm text-text-body">{{ customer.email ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-text-muted">Portal Access</p>
            <p class="text-sm text-text-body">{{ customer.has_portal ? 'Yes' : 'No' }}</p>
          </div>
          <div>
            <p class="text-xs text-text-muted">Phone</p>
            <p class="text-sm text-text-body">{{ customer.phone ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-text-muted">Payment Method</p>
            <p class="text-sm text-text-body">
              <span v-if="customer.stripe_pm_last4">
                {{ capitalize(customer.stripe_pm_brand ?? '') }} ···· {{ customer.stripe_pm_last4 }}
              </span>
              <span v-else>—</span>
            </p>
          </div>
          <div v-if="customer.notes" class="sm:col-span-2">
            <p class="text-xs text-text-muted">Notes</p>
            <p class="text-sm text-text-body whitespace-pre-line">{{ customer.notes }}</p>
          </div>
        </div>
      </div>

      <!-- Dogs -->
      <div>
        <h2 class="text-lg font-semibold text-text-body mb-3">Dogs</h2>
        <div v-if="dogs.length === 0" class="bg-white rounded-xl border border-border-warm px-5 py-8 text-center text-sm text-text-muted">
          No dogs on this account.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="dog in dogs"
            :key="dog.id"
            class="bg-white rounded-xl border border-border-warm p-5"
            :class="{ 'opacity-60': dog.deleted_at }"
          >
            <div class="flex items-start justify-between gap-4 mb-3">
              <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-text-body">{{ dog.name }}</p>
                <span :class="statusBadge(dog.status)" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset">
                  {{ dog.deleted_at ? 'Archived' : capitalize(dog.status) }}
                </span>
              </div>
              <div class="text-right shrink-0">
                <p class="text-sm font-bold text-text-body">{{ dog.credit_balance }} credits</p>
                <p v-if="dog.unlimited_pass_expires_at" class="text-[10px] text-indigo-600">Unlimited pass · exp {{ formatDate(dog.unlimited_pass_expires_at) }}</p>
                <p v-else-if="dog.credits_expire_at" class="text-[10px] text-text-muted">Expires {{ formatDate(dog.credits_expire_at) }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-2 text-sm">
              <div v-if="dog.breed">
                <p class="text-[10px] text-text-muted uppercase tracking-wide">Breed</p>
                <p class="text-text-body">{{ dog.breed }}</p>
              </div>
              <div v-if="dog.sex">
                <p class="text-[10px] text-text-muted uppercase tracking-wide">Sex</p>
                <p class="text-text-body">{{ capitalize(dog.sex) }}</p>
              </div>
              <div v-if="dog.dob">
                <p class="text-[10px] text-text-muted uppercase tracking-wide">Age</p>
                <p class="text-text-body">{{ dogAge(dog.dob) }}</p>
              </div>
              <div v-if="dog.last_attendance_at">
                <p class="text-[10px] text-text-muted uppercase tracking-wide">Last Visit</p>
                <p class="text-text-body">{{ formatDate(dog.last_attendance_at) }}</p>
              </div>
              <div v-if="dog.vet_name" class="sm:col-span-2">
                <p class="text-[10px] text-text-muted uppercase tracking-wide">Vet</p>
                <p class="text-text-body">{{ dog.vet_name }}<span v-if="dog.vet_phone"> · {{ dog.vet_phone }}</span></p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Balance Ledger -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-text-body">Balance Ledger</h2>
          <div class="flex items-center gap-2">
            <button
              v-if="customer.has_portal"
              :disabled="notifyLoading || notifySent"
              class="inline-flex items-center rounded-lg border border-border-warm bg-white px-3 py-1.5 text-xs font-medium text-text-body hover:bg-surface disabled:opacity-60 transition-colors"
              @click="requestPaymentUpdate"
            >
              {{ notifySent ? 'Sent ✓' : notifyLoading ? 'Sending…' : 'Request Card Update' }}
            </button>
            <button
              v-if="customer.is_owner && customer.outstanding_balance_cents > 0 && customer.has_stripe_customer && customer.stripe_pm_last4"
              :disabled="chargeLoading"
              class="inline-flex items-center rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60 transition-colors"
              @click="chargeOutstandingBalance"
            >
              {{ chargeLoading ? 'Charging…' : `Charge Now · ${formatMoney(customer.outstanding_balance_cents / 100)}` }}
            </button>
          </div>
        </div>
        <p v-if="chargeError" class="mb-2 text-xs text-red-600">{{ chargeError }}</p>
        <div v-if="orders.length === 0" class="bg-white rounded-xl border border-border-warm px-5 py-8 text-center text-sm text-text-muted">
          No orders yet.
        </div>
        <div v-else class="bg-white rounded-xl border border-border-warm overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border-warm">
                <th class="text-left px-5 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Date</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Description</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Type</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Amount</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border-warm">
              <tr v-for="order in orders" :key="order.id" class="hover:bg-surface transition-colors">
                <td class="px-5 py-3 text-text-muted whitespace-nowrap">{{ formatDate(order.created_at) }}</td>
                <td class="px-5 py-3 text-text-body font-medium">{{ order.package_name ?? '—' }}</td>
                <td class="px-5 py-3">
                  <span :class="typeBadge(order.type)" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset">
                    {{ formatType(order.type) }}
                  </span>
                </td>
                <td class="px-5 py-3 text-right text-text-body">{{ formatMoney(order.total_amount) }}</td>
                <td class="px-5 py-3">
                  <span :class="orderStatusBadge(order.status)" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset">
                    {{ capitalize(order.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot class="border-t-2 border-border-warm bg-surface">
              <tr>
                <td colspan="3" class="px-5 py-3 text-xs text-text-muted"></td>
                <td class="px-5 py-3 text-right">
                  <p class="text-[10px] text-text-muted uppercase tracking-wide">Total Paid</p>
                  <p class="text-sm font-semibold text-green-700">{{ formatMoney(totalPaid) }}</p>
                </td>
                <td class="px-5 py-3">
                  <div v-if="totalFailed > 0">
                    <p class="text-[10px] text-text-muted uppercase tracking-wide">Failed / Owed</p>
                    <p class="text-sm font-semibold text-red-600">{{ formatMoney(totalFailed) }}</p>
                  </div>
                  <div v-if="customer.outstanding_balance_cents > 0" class="mt-1">
                    <p class="text-[10px] text-text-muted uppercase tracking-wide">Outstanding</p>
                    <p class="text-sm font-bold text-red-700">{{ formatMoney(customer.outstanding_balance_cents / 100) }}</p>
                  </div>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Dog {
  id: string;
  name: string;
  breed: string | null;
  sex: string | null;
  dob: string | null;
  status: string;
  credit_balance: number;
  credits_expire_at: string | null;
  unlimited_pass_expires_at: string | null;
  vet_name: string | null;
  vet_phone: string | null;
  last_attendance_at: string | null;
  deleted_at: string | null;
}

interface Order {
  id: string;
  package_name: string | null;
  type: string | null;
  total_amount: number;
  status: string;
  created_at: string;
}

const props = defineProps<{
  customer: {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    notes: string | null;
    has_portal: boolean;
    has_stripe_customer: boolean;
    is_owner: boolean;
    stripe_pm_last4: string | null;
    stripe_pm_brand: string | null;
    outstanding_balance_cents: number;
    total_orders: number;
    total_spent: number;
    total_credits: number;
    created_at: string;
  };
  dogs: Dog[];
  orders: Order[];
}>();

const chargeLoading = ref(false);
const chargeError = ref<string | null>(null);
const notifyLoading = ref(false);
const notifySent = ref(false);

const totalPaid = computed(() =>
  props.orders.filter(o => ['paid', 'partially_refunded'].includes(o.status)).reduce((s, o) => s + o.total_amount, 0),
);

const totalFailed = computed(() =>
  props.orders.filter(o => o.status === 'failed').reduce((s, o) => s + o.total_amount, 0),
);

async function chargeOutstandingBalance() {
  chargeLoading.value = true;
  chargeError.value = null;
  try {
    await axios.post(`/api/admin/v1/customers/${props.customer.id}/charge-balance`);
    router.reload({ only: ['customer'] });
  } catch (e: any) {
    chargeError.value = e.response?.data?.message ?? e.response?.data?.error ?? 'Charge failed. Please try again.';
  } finally {
    chargeLoading.value = false;
  }
}

async function requestPaymentUpdate() {
  notifyLoading.value = true;
  try {
    await axios.post(`/api/admin/v1/customers/${props.customer.id}/request-payment-update`);
    notifySent.value = true;
  } finally {
    notifyLoading.value = false;
  }
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
}

function capitalize(str: string): string {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ');
}

function dogAge(dob: string | null): string {
  if (!dob) return '—';
  const birth = new Date(dob);
  const now = new Date();
  const months = (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());
  if (months < 12) return `${months}mo`;
  const years = Math.floor(months / 12);
  const rem = months % 12;
  return rem > 0 ? `${years}y ${rem}mo` : `${years}y`;
}

function formatType(type: string | null): string {
  if (!type) return '—';
  return type === 'one_time' ? 'One-time' : type === 'subscription' ? 'Subscription' : capitalize(type);
}

function statusBadge(status: string): string {
  if (status === 'active') return 'bg-green-50 text-green-700 ring-green-600/20';
  if (status === 'inactive') return 'bg-yellow-50 text-yellow-700 ring-yellow-600/20';
  return 'bg-gray-50 text-gray-600 ring-gray-500/20';
}

function typeBadge(type: string | null): string {
  if (type === 'subscription') return 'bg-indigo-50 text-indigo-700 ring-indigo-600/20';
  return 'bg-gray-50 text-gray-600 ring-gray-500/20';
}

function orderStatusBadge(status: string): string {
  if (status === 'paid' || status === 'completed') return 'bg-green-50 text-green-700 ring-green-600/20';
  if (status === 'pending' || status === 'authorized') return 'bg-yellow-50 text-yellow-700 ring-yellow-600/20';
  if (status === 'refunded' || status === 'partially_refunded' || status === 'failed' || status === 'disputed') return 'bg-red-50 text-red-700 ring-red-600/20';
  return 'bg-gray-50 text-gray-600 ring-gray-500/20';
}
</script>
