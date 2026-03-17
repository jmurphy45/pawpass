<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Packages</h1>
        <Link :href="route('admin.packages.create')" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Add Package</Link>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="pkg in packages" :key="pkg.id" class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ pkg.name }}</p>
            <p class="text-xs text-gray-500">${{ Number(pkg.price).toFixed(2) }} · {{ pkg.credit_count }} credits</p>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="pkg.archived_at" class="text-xs text-gray-400">Archived</span>
            <Link v-else :href="route('admin.packages.edit', { package: pkg.id })" class="text-sm text-indigo-600 hover:underline">Edit</Link>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
  packages: Array<{ id: string; name: string; type: string; price: number; credit_count: number; is_active: boolean; archived_at: string | null }>;
}>();
</script>
