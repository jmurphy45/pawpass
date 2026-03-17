<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ dog.name }}</h1>
        <Link :href="route('admin.dogs.edit', { dog: dog.id })" class="text-sm text-indigo-600 hover:underline">Edit</Link>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2">
        <p class="text-sm text-gray-600">Breed: {{ dog.breed ?? '—' }}</p>
        <p class="text-sm text-gray-600">Credits: {{ dog.credit_balance }}</p>
        <p class="text-sm text-gray-600">Owner: {{ dog.customer_name }}</p>
      </div>
      <h2 class="text-lg font-semibold text-gray-900">Credit History</h2>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="entry in ledger" :key="entry.id" class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ entry.type }}</p>
            <p class="text-xs text-gray-500">{{ entry.note }}</p>
          </div>
          <span class="text-sm" :class="entry.amount >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ entry.amount >= 0 ? '+' : '' }}{{ entry.amount }}
          </span>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
  dog: { id: string; name: string; breed: string | null; dob: string | null; sex: string | null; credit_balance: number; vet_name: string | null; vet_phone: string | null; customer_id: string; customer_name: string | null };
  ledger: Array<{ id: string; type: string; amount: number; balance_after: number; note: string | null; created_at: string }>;
  attendance: Array<{ id: string; checked_in_at: string; checked_out_at: string | null }>;
}>();
</script>
