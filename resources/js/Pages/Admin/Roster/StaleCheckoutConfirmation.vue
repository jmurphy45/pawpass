<template>
  <AdminLayout>
    <div class="max-w-lg mx-auto py-12 text-center">
      <div class="mb-6 flex justify-center">
        <div class="rounded-full bg-green-100 p-4">
          <svg class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
      </div>

      <h1 class="text-2xl font-bold text-gray-900 mb-2">Check-Out Complete</h1>

      <p class="text-gray-600 mb-8">
        <template v-if="checked_out_count > 0">
          {{ checked_out_count }} dog{{ checked_out_count === 1 ? '' : 's' }} {{ checked_out_count === 1 ? 'has' : 'have' }} been checked out.
        </template>
        <template v-else>
          No dogs were still checked in — everything looks good!
        </template>
      </p>

      <Link :href="route('admin.roster.index')" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
        Go to Roster
      </Link>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
  checked_out_count: number;
}>();
</script>
