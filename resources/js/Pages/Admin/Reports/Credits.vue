<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Credit Issuance & Usage</h1>
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
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Total Delta</th>
              <th class="px-4 py-3 text-right font-medium text-gray-600">Entries</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="rows.length === 0">
              <td colspan="3" class="px-4 py-6 text-center text-gray-400">No data for selected range.</td>
            </tr>
            <tr v-for="row in rows" :key="row.type" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-900 capitalize">{{ row.type.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-right" :class="row.total_delta >= 0 ? 'text-green-700' : 'text-red-600'">
                {{ row.total_delta >= 0 ? '+' : '' }}{{ row.total_delta }}
              </td>
              <td class="px-4 py-3 text-right text-gray-700">{{ row.entries }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface CreditsRow {
  type: string;
  total_delta: number;
  entries: number;
}

interface Filters {
  from: string;
  to: string;
}

const props = defineProps<{
  rows: CreditsRow[];
  filters: Filters;
}>();

const localFrom = ref(props.filters.from);
const localTo   = ref(props.filters.to);

const csvUrl = computed(() =>
  `${route('admin.reports.credits')}?from=${localFrom.value}&to=${localTo.value}&format=csv`
);

function applyFilters(): void {
  router.get(route('admin.reports.credits'), {
    from: localFrom.value,
    to:   localTo.value,
  }, { preserveState: false });
}
</script>
