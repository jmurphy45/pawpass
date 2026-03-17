<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Payments</h1>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="order in orders.data" :key="order.id" class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ order.customer_name }}</p>
            <p class="text-xs text-gray-500">{{ order.package_name }} · ${{ (order.amount_cents / 100).toFixed(2) }}</p>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-xs px-2 py-0.5 rounded-full" :class="{
              'bg-green-100 text-green-700': order.status === 'paid',
              'bg-red-100 text-red-700': order.status === 'refunded',
              'bg-gray-100 text-gray-600': !['paid', 'refunded'].includes(order.status),
            }">{{ order.status }}</span>
            <form v-if="order.status === 'paid'" @submit.prevent="refund(order.id)">
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
  orders: { data: Array<{ id: string; customer_name: string | null; package_name: string | null; amount_cents: number; status: string; created_at: string }> };
  filters: { status: string };
}>();

function refund(orderId: string) {
  useForm({}).post(route('admin.payments.refund', { order: orderId }));
}
</script>
