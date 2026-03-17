<template>
  <PortalLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Invoices</h1>

      <div v-if="orders.data.length === 0" class="rounded-2xl bg-white border border-dashed border-gray-200 p-12 text-center text-gray-500 text-sm">
        No orders yet.
        <Link :href="route('portal.purchase')" class="text-indigo-600 hover:underline ml-1">Buy your first package →</Link>
      </div>

      <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
          <span>Package</span>
          <span>Amount</span>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="order in orders.data" :key="order.id" class="px-4 py-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-gray-900">{{ order.package_name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  {{ order.dog_names.join(', ') }} · {{ formatDate(order.created_at) }}
                </p>
              </div>
              <div class="text-right">
                <p class="font-semibold text-gray-900">${{ (order.amount_cents / 100).toFixed(2) }}</p>
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium mt-1"
                  :class="statusClasses(order.status)"
                >{{ order.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="orders.meta.last_page > 1" class="flex items-center justify-between text-sm">
        <p class="text-gray-500">
          Showing {{ orders.data.length }} of {{ orders.meta.total }}
        </p>
        <div class="flex gap-2">
          <Link
            v-if="orders.meta.current_page > 1"
            :href="route('portal.history', { page: orders.meta.current_page - 1 })"
            class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 hover:bg-gray-50"
          >Previous</Link>
          <Link
            v-if="orders.meta.current_page < orders.meta.last_page"
            :href="route('portal.history', { page: orders.meta.current_page + 1 })"
            class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 hover:bg-gray-50"
          >Next</Link>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Order, PaginatedResponse } from '@/types';

defineProps<{ orders: PaginatedResponse<Order> }>();

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function statusClasses(status: string) {
  return {
    paid: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    refunded: 'bg-gray-100 text-gray-600',
    failed: 'bg-red-100 text-red-600',
  }[status] ?? 'bg-gray-100 text-gray-600';
}
</script>
