<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Payments</h1>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="payment in payments.data" :key="payment.id" class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ payment.customer_name }}</p>
            <p class="text-xs text-gray-500">{{ payment.description }} · ${{ (payment.amount_cents / 100).toFixed(2) }}</p>
            <p class="text-xs text-gray-400 font-mono mt-0.5">Ref: {{ payment.id }}</p>
            <p v-if="payment.stripe_pi_id" class="text-xs text-gray-400 font-mono">{{ payment.stripe_pi_id }}</p>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="payment.type === 'boarding'" class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">boarding</span>
            <span class="text-xs px-2 py-0.5 rounded-full" :class="{
              'bg-green-100 text-green-700': payment.status === 'paid',
              'bg-red-100 text-red-700': payment.status === 'refunded',
              'bg-yellow-100 text-yellow-700': payment.status === 'authorized',
              'bg-gray-100 text-gray-600': !['paid', 'refunded', 'authorized'].includes(payment.status),
            }">{{ payment.status }}</span>
            <a
              v-if="payment.status === 'paid' && payment.stripe_pi_id"
              :href="route('admin.orders.receipt', { order: payment.order_id })"
              class="text-xs text-blue-600 hover:underline"
            >Receipt</a>
            <form v-if="payment.status === 'paid'" @submit.prevent="refund(payment.order_id)">
              <button type="submit" class="text-xs text-red-600 hover:underline">Refund</button>
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
  payments: { data: Array<{ id: string; order_id: string; type: string; payment_type: string; stripe_pi_id: string | null; customer_name: string | null; description: string; amount_cents: number; status: string; created_at: string; refunded_at: string | null }> };
  filters: { status: string };
}>();

function refund(orderId: string) {
  useForm({}).post(route('admin.payments.refund', { order: orderId }));
}
</script>
