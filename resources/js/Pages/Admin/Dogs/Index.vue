<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Dogs</h1>
          <p class="text-sm text-text-muted mt-0.5">{{ dogs.data.length }} total</p>
        </div>
        <Link :href="route('admin.dogs.create')" class="btn-primary">Add Dog</Link>
      </div>

      <div class="card overflow-hidden">
        <div v-if="dogs.data.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No dogs yet.
        </div>
        <ul v-else>
          <li v-for="dog in dogs.data" :key="dog.id" class="list-row gap-3">
            <!-- Avatar initials -->
            <div class="h-9 w-9 rounded-full bg-surface-subtle flex items-center justify-center text-sm font-semibold text-text-body shrink-0">
              {{ dog.name[0]?.toUpperCase() }}
            </div>

            <!-- Dog info -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-text-body truncate">{{ dog.name }}</p>
              <p class="text-xs text-text-muted truncate">{{ dog.customer_name }}</p>
            </div>

            <!-- Credit badge -->
            <span
              class="badge hidden sm:inline-flex"
              :class="{
                'badge-red': dog.credit_balance <= 0,
                'badge-yellow': dog.credit_balance > 0 && dog.credit_balance <= 3,
                'badge-green': dog.credit_balance > 3,
              }"
            >{{ dog.credit_balance }} cr</span>

            <!-- View link with chevron -->
            <Link
              :href="route('admin.dogs.show', { dog: dog.id })"
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
  dogs: { data: Array<{ id: string; name: string; breed: string | null; credit_balance: number; customer_name: string | null; customer_id: string }> };
  filters: { search: string };
}>();
</script>
