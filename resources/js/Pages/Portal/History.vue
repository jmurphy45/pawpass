<template>
  <PortalLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-text-body">Invoices</h1>

      <div v-if="orders.data.length === 0" class="card p-12 text-center">
        <div class="mx-auto h-14 w-14 rounded-full bg-surface-subtle flex items-center justify-center mb-3">
          <svg class="h-7 w-7 text-text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
          </svg>
        </div>
        <p class="font-semibold text-text-body">No orders yet</p>
        <p class="text-sm text-text-muted mt-1">Your purchase history will appear here</p>
        <Link :href="route('portal.purchase')" class="btn-primary mt-4 inline-flex">Buy your first package →</Link>
      </div>

      <div v-else class="card overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide bg-surface-subtle border-b border-border">
          <span>Package</span>
          <span>Amount</span>
        </div>
        <ul>
          <li v-for="order in orders.data" :key="order.id" class="list-row gap-4">
            <!-- Status indicator -->
            <div
              class="h-8 w-8 rounded-full flex items-center justify-center shrink-0"
              :class="order.status === 'paid' ? 'bg-green-100' : order.status === 'refunded' ? 'bg-gray-100' : order.status === 'failed' ? 'bg-red-100' : 'bg-yellow-100'"
            >
              <svg
                class="h-4 w-4"
                :class="order.status === 'paid' ? 'text-green-600' : order.status === 'refunded' ? 'text-gray-500' : order.status === 'failed' ? 'text-red-500' : 'text-yellow-600'"
                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
              >
                <path v-if="order.status === 'paid'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                <path v-else-if="order.status === 'refunded'" stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
              </svg>
            </div>

            <div class="flex-1 min-w-0">
              <p class="font-semibold text-text-body truncate">{{ order.package_name }}</p>
              <p class="text-xs text-text-muted mt-0.5">
                {{ order.dog_names.join(', ') }} · {{ formatDate(order.created_at) }}
              </p>
              <p class="text-xs text-text-muted mt-0.5 font-mono">Ref: {{ order.id }}</p>
            </div>

            <div class="text-right shrink-0">
              <p class="font-semibold text-text-body">${{ (order.amount_cents / 100).toFixed(2) }}</p>
              <span class="badge mt-1" :class="statusClasses(order.status)">{{ order.status }}</span>
              <a
                v-if="order.has_receipt"
                :href="route('portal.orders.receipt', { order: order.id })"
                class="text-xs text-link mt-1 inline-block"
              >Download receipt</a>
            </div>
          </li>
        </ul>
      </div>

      <!-- Pagination -->
      <div v-if="orders.meta.last_page > 1" class="flex items-center justify-between text-sm">
        <p class="text-text-muted">Showing {{ orders.data.length }} of {{ orders.meta.total }}</p>
        <div class="flex gap-2">
          <Link
            v-if="orders.meta.current_page > 1"
            :href="route('portal.history', { page: orders.meta.current_page - 1 })"
            class="btn-secondary text-xs py-1.5 px-3"
          >Previous</Link>
          <Link
            v-if="orders.meta.current_page < orders.meta.last_page"
            :href="route('portal.history', { page: orders.meta.current_page + 1 })"
            class="btn-secondary text-xs py-1.5 px-3"
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
    paid:     'badge-green',
    pending:  'badge-yellow',
    refunded: 'badge-gray',
    failed:   'badge-red',
  }[status] ?? 'badge-gray';
}
</script>
