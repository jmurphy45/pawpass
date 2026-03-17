<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Dogs</h1>
        <Link :href="route('admin.dogs.create')" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Add Dog</Link>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="dog in dogs.data" :key="dog.id" class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ dog.name }}</p>
            <p class="text-xs text-gray-500">{{ dog.customer_name }} · {{ dog.credit_balance }} credits</p>
          </div>
          <Link :href="route('admin.dogs.show', { dog: dog.id })" class="text-sm text-indigo-600 hover:underline">View</Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
  dogs: { data: Array<{ id: string; name: string; breed: string | null; credit_balance: number; customer_name: string | null; customer_id: string }> };
  filters: { search: string };
}>();
</script>
