<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Outstanding Balances</h1>
        <span class="text-sm text-gray-500">Live snapshot</span>
      </div>

      <p class="text-sm text-gray-600">Customers with unpaid balances from failed payment attempts.</p>

      <!-- Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Customer</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Email</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Balance Owed</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Charge Pending Since</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="rows.length === 0">
                <td colspan="4" class="px-4 py-6 text-center text-gray-400">No outstanding balances.</td>
              </tr>
              <tr v-for="row in rows" :key="row.customer_id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900">{{ row.customer_name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ row.email ?? '—' }}</td>
                <td class="px-4 py-3 text-right font-semibold text-red-600">${{ fmtCents(row.outstanding_balance_cents) }}</td>
                <td class="px-4 py-3 text-gray-500">{{ row.charge_pending_at ? fmtDate(row.charge_pending_at) : '—' }}</td>
              </tr>
            </tbody>
            <tfoot v-if="rows.length > 0" class="bg-gray-50 font-semibold">
              <tr>
                <td colspan="2" class="px-4 py-3 text-gray-900">Total</td>
                <td class="px-4 py-3 text-right text-red-600">${{ fmtCents(rows.reduce((s, r) => s + r.outstanding_balance_cents, 0)) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface BalanceRow {
  customer_id: string;
  customer_name: string;
  email: string | null;
  outstanding_balance_cents: number;
  charge_pending_at: string | null;
}

defineProps<{
  rows: BalanceRow[];
}>();

function fmtCents(cents: number): string {
  return (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(dt: string): string {
  return new Date(dt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
