<template>
  <PortalLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Attendance</h1>

        <select
          v-model="selectedDog"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          @change="filterByDog"
        >
          <option value="">All dogs</option>
          <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
        </select>
      </div>

      <div v-if="attendance.data.length === 0" class="rounded-2xl bg-white border border-dashed border-gray-200 p-12 text-center text-gray-500 text-sm">
        No attendance records yet.
      </div>

      <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Dog</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check In</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check Out</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="a in attendance.data" :key="a.id" class="hover:bg-gray-50/50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ a.dog_name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ formatDatetime(a.checked_in_at) }}</td>
              <td class="px-4 py-3 text-gray-600">
                <span v-if="a.checked_out_at">{{ formatDatetime(a.checked_out_at) }}</span>
                <span v-else class="inline-flex items-center rounded-full bg-green-100 text-green-700 px-2 py-0.5 text-xs">Here now</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="attendance.meta.last_page > 1" class="flex items-center justify-between text-sm">
        <p class="text-gray-500">
          Showing {{ attendance.data.length }} of {{ attendance.meta.total }}
        </p>
        <div class="flex gap-2">
          <Link
            v-if="attendance.meta.current_page > 1"
            :href="route('portal.attendance', { page: attendance.meta.current_page - 1, dog_id: selectedDog || undefined })"
            class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 hover:bg-gray-50"
          >Previous</Link>
          <Link
            v-if="attendance.meta.current_page < attendance.meta.last_page"
            :href="route('portal.attendance', { page: attendance.meta.current_page + 1, dog_id: selectedDog || undefined })"
            class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 hover:bg-gray-50"
          >Next</Link>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Attendance, PaginatedResponse } from '@/types';

interface DogOption { id: string; name: string; }

const props = defineProps<{
  attendance: PaginatedResponse<Attendance>;
  dogs: DogOption[];
  selected_dog: string | null;
}>();

const selectedDog = ref(props.selected_dog ?? '');

function filterByDog() {
  router.get(route('portal.attendance'), selectedDog.value ? { dog_id: selectedDog.value } : {}, {
    preserveState: true,
    replace: true,
  });
}

function formatDatetime(iso: string) {
  return new Date(iso).toLocaleString(undefined, {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}
</script>
