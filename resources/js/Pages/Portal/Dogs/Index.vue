<template>
  <PortalLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">My Dogs</h1>
        <Link
          :href="route('portal.dogs.create')"
          class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Dog
        </Link>
      </div>

      <div v-if="dogs.length === 0" class="rounded-2xl bg-white border border-dashed border-gray-300 p-12 text-center">
        <p class="text-4xl mb-3">🐾</p>
        <p class="text-gray-600 font-medium">No dogs registered yet.</p>
        <Link :href="route('portal.dogs.create')" class="mt-3 inline-block text-sm text-indigo-600 hover:underline">
          Add your first dog →
        </Link>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link
          v-for="dog in dogs"
          :key="dog.id"
          :href="route('portal.dogs.show', { dog: dog.id })"
          class="block rounded-2xl bg-white border border-gray-200 p-5 shadow-sm hover:shadow-md transition-shadow"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-gray-900">{{ dog.name }}</p>
              <p v-if="dog.breed" class="text-xs text-gray-500 mt-0.5">{{ dog.breed }}</p>
            </div>
            <span
              v-if="dog.color"
              class="h-6 w-6 rounded-full border border-gray-200 shrink-0"
              :style="{ backgroundColor: dog.color }"
            />
          </div>
          <div class="mt-4 flex items-center justify-between text-sm">
            <span class="text-gray-500">Credits</span>
            <span class="font-semibold text-gray-900">{{ dog.credit_balance }}</span>
          </div>
          <div v-if="dog.credits_expire_at" class="mt-1 text-xs text-gray-400">
            Expires {{ formatDate(dog.credits_expire_at) }}
          </div>
        </Link>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Dog } from '@/types';

defineProps<{ dogs: Dog[] }>();

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
