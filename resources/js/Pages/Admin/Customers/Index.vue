<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Customers</h1>
        <Link :href="route('admin.customers.create')" class="btn-primary">Add Customer</Link>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="customer in customers.data" :key="customer.id" class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ customer.name }}</p>
            <p class="text-xs text-gray-500">{{ customer.email }} · {{ customer.dogs_count }} dog(s)</p>
          </div>
          <Link :href="route('admin.customers.show', { customer: customer.id })" class="text-sm text-indigo-600 hover:underline">View</Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
  customers: { data: Array<{ id: string; name: string; email: string | null; dogs_count: number }> };
  filters: { search: string };
}>();
</script>
