<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Zero & Low Credit Dogs</h1>

      <!-- Zero Credits -->
      <section>
        <h2 class="text-base font-semibold text-red-700 mb-2">
          Zero Credits
          <span class="ml-2 text-sm font-normal text-gray-500">({{ data.zero.length }} dogs)</span>
        </h2>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Dog</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Owner</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Balance</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="data.zero.length === 0">
                <td colspan="3" class="px-4 py-6 text-center text-gray-400">No dogs with zero credits.</td>
              </tr>
              <tr v-for="dog in data.zero" :key="dog.id" class="hover:bg-red-50">
                <td class="px-4 py-3 text-gray-900">{{ dog.dog_name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ dog.customer_name }}</td>
                <td class="px-4 py-3 text-right font-bold text-red-700">{{ dog.credit_balance }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Low Credits (1–3) -->
      <section>
        <h2 class="text-base font-semibold text-amber-700 mb-2">
          Low Credits (1–3)
          <span class="ml-2 text-sm font-normal text-gray-500">({{ data.low.length }} dogs)</span>
        </h2>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Dog</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Owner</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Balance</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="data.low.length === 0">
                <td colspan="3" class="px-4 py-6 text-center text-gray-400">No dogs with low credits.</td>
              </tr>
              <tr v-for="dog in data.low" :key="dog.id" class="hover:bg-amber-50">
                <td class="px-4 py-3 text-gray-900">{{ dog.dog_name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ dog.customer_name }}</td>
                <td class="px-4 py-3 text-right font-bold text-amber-700">{{ dog.credit_balance }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface DogRow {
  id: string;
  dog_name: string;
  customer_name: string;
  credit_balance: number;
}

interface CreditStatusData {
  zero: DogRow[];
  low: DogRow[];
}

defineProps<{
  data: CreditStatusData;
}>();
</script>
