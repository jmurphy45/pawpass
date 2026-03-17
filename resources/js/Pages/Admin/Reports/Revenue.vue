<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Revenue Summary</h1>
        <a :href="csvUrl" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Export CSV</a>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3 bg-white border border-gray-200 rounded-lg p-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
          <input v-model="localFrom" type="date" class="border border-gray-300 rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
          <input v-model="localTo" type="date" class="border border-gray-300 rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Group By</label>
          <select v-model="localGroupBy" class="border border-gray-300 rounded px-2 py-1 text-sm">
            <option value="month">Month</option>
            <option value="week">Week</option>
            <option value="day">Day</option>
          </select>
        </div>
        <div class="flex items-end">
          <button @click="applyFilters" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">Apply</button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-gray-600">Period</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Gross</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Fee</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Net</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Orders</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="rows.length === 0">
              <td colspan="5" class="px-4 py-6 text-center text-gray-400">No data for selected range.</td>
            </tr>
            <tr v-for="row in rows" :key="row.period" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-900">{{ row.period }}</td>
              <td class="px-4 py-3 text-right text-gray-900">${{ fmt(row.gross) }}</td>
              <td class="px-4 py-3 text-right text-red-600">${{ fmt(row.fee) }}</td>
              <td class="px-4 py-3 text-right text-green-700 font-medium">${{ fmt(row.net) }}</td>
              <td class="px-4 py-3 text-right text-gray-700">{{ row.orders }}</td>
            </tr>
          </tbody>
          <tfoot v-if="rows.length > 0" class="bg-gray-50 font-semibold">
            <tr>
              <td class="px-4 py-3 text-gray-900">Total</td>
              <td class="px-4 py-3 text-right">${{ fmt(totals.gross) }}</td>
              <td class="px-4 py-3 text-right text-red-600">${{ fmt(totals.fee) }}</td>
              <td class="px-4 py-3 text-right text-green-700">${{ fmt(totals.net) }}</td>
              <td class="px-4 py-3 text-right">{{ totals.orders }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface RevenueRow {
  period: string;
  gross: number;
  fee: number;
  net: number;
  orders: number;
}

interface Filters {
  from: string;
  to: string;
  groupBy: string;
}

const props = defineProps<{
  rows: RevenueRow[];
  filters: Filters;
}>();

const localFrom    = ref(props.filters.from);
const localTo      = ref(props.filters.to);
const localGroupBy = ref(props.filters.groupBy);

const csvUrl = computed(() =>
  `${route('admin.reports.revenue')}?from=${localFrom.value}&to=${localTo.value}&group_by=${localGroupBy.value}&format=csv`
);

const totals = computed(() => ({
  gross:  props.rows.reduce((s, r) => s + r.gross, 0),
  fee:    props.rows.reduce((s, r) => s + r.fee, 0),
  net:    props.rows.reduce((s, r) => s + r.net, 0),
  orders: props.rows.reduce((s, r) => s + r.orders, 0),
}));

function fmt(n: number): string {
  return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilters(): void {
  router.get(route('admin.reports.revenue'), {
    from:     localFrom.value,
    to:       localTo.value,
    group_by: localGroupBy.value,
  }, { preserveState: false });
}
</script>
