<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-2xl font-bold text-text-body">Customers</h1>
          <p class="text-sm text-text-muted mt-0.5">{{ customers.data.length }} total</p>
        </div>
        <Link :href="route('admin.customers.create')" class="btn-primary shrink-0">Add Customer</Link>
      </div>

      <div class="card overflow-hidden">
        <div v-if="customers.data.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No customers yet.
        </div>
        <ul v-else>
          <li v-for="customer in customers.data" :key="customer.id" class="list-row gap-3">
            <!-- Avatar initials -->
            <div class="h-9 w-9 rounded-full bg-surface-subtle flex items-center justify-center text-sm font-semibold text-text-body shrink-0">
              {{ customer.name[0]?.toUpperCase() }}
            </div>

            <!-- Customer info -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-text-body truncate">{{ customer.name }}</p>
              <p class="text-xs text-text-muted truncate">{{ customer.email ?? 'No email' }} · {{ customer.dogs_count }} dog(s)</p>
            </div>

            <!-- View link with chevron -->
            <Link
              :href="route('admin.customers.show', { customer: customer.id })"
              class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 shrink-0"
            >
              View
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
              </svg>
            </Link>
          </li>
        </ul>
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
