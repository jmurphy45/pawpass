<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Package Performance</h1>
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
        <div class="flex items-end">
          <button @click="applyFilters" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">Apply</button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-gray-600">Package</th>
              <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Orders</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Revenue</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="rows.length === 0">
              <td colspan="4" class="px-4 py-6 text-center text-gray-400">No data for selected range.</td>
            </tr>
            <tr v-for="row in rows" :key="row.package_id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-900">{{ row.package_name }}</td>
              <td class="px-4 py-3 text-gray-500 capitalize">{{ row.package_type.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-right text-gray-700">{{ row.orders }}</td>
              <td class="px-4 py-3 text-right text-gray-900">${{ fmt(row.revenue) }}</td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface PackageRow {
  package_id: string;
  package_name: string;
  package_type: string;
  orders: number;
  revenue: number;
}

interface Filters {
  from: string;
  to: string;
}

const props = defineProps<{
  rows: PackageRow[];
  filters: Filters;
}>();

const localFrom = ref(props.filters.from);
const localTo   = ref(props.filters.to);

const csvUrl = computed(() =>
  `${route('admin.reports.packages')}?from=${localFrom.value}&to=${localTo.value}&format=csv`
);

function fmt(n: number): string {
  return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilters(): void {
  router.get(route('admin.reports.packages'), {
    from: localFrom.value,
    to:   localTo.value,
  }, { preserveState: false });
}
</script>
