<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">{{ customer.name }}</h1>
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2">
        <p class="text-sm text-gray-600">Email: {{ customer.email ?? '—' }}</p>
        <p class="text-sm text-gray-600">Phone: {{ customer.phone ?? '—' }}</p>
      </div>
      <h2 class="text-lg font-semibold text-gray-900">Dogs</h2>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="dog in dogs" :key="dog.id" class="px-5 py-3 flex items-center justify-between">
          <p class="text-sm font-medium text-gray-900">{{ dog.name }}</p>
          <span class="text-sm text-gray-500">{{ dog.credit_balance }} credits</span>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps<{
  customer: { id: string; name: string; email: string | null; phone: string | null; notes: string | null };
  dogs: Array<{ id: string; name: string; breed: string | null; credit_balance: number }>;
  orders: { data: Array<{ id: string; package_name: string | null; amount_cents: number; status: string }> };
}>();
</script>
