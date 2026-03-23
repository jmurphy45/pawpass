<template>
  <PortalLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-text-body min-w-0">Attendance</h1>
        <select
          v-model="selectedDog"
          class="input max-w-[180px]"
          @change="filterByDog"
        >
          <option value="">All dogs</option>
          <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
        </select>
      </div>

      <div v-if="attendance.data.length === 0" class="card p-12 text-center">
        <p class="text-4xl mb-3">📋</p>
        <p class="font-semibold text-text-body">No attendance records yet</p>
        <p class="text-sm text-text-muted mt-1">Records will appear here after your first check-in</p>
      </div>

      <div v-else class="card overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr style="border-bottom: 1px solid #e5e0d8; background-color: #faf9f6;">
              <th class="text-left px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Dog</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Check In</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Check Out</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="a in attendance.data"
              :key="a.id"
              class="hover:bg-surface transition-colors"
              style="border-bottom: 1px solid #f0ede8;"
            >
              <td class="px-4 py-3 font-medium text-text-body">{{ a.dog_name }}</td>
              <td class="px-4 py-3 text-text-muted">{{ formatDatetime(a.checked_in_at) }}</td>
              <td class="px-4 py-3">
                <span v-if="a.checked_out_at" class="text-text-muted">{{ formatDatetime(a.checked_out_at) }}</span>
                <span v-else class="badge badge-green animate-pulse">Here now</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="attendance.meta.last_page > 1" class="flex items-center justify-between text-sm">
        <p class="text-text-muted">Showing {{ attendance.data.length }} of {{ attendance.meta.total }}</p>
        <div class="flex gap-2">
          <Link
            v-if="attendance.meta.current_page > 1"
            :href="route('portal.attendance', { page: attendance.meta.current_page - 1, dog_id: selectedDog || undefined })"
            class="btn-secondary text-xs py-1.5 px-3"
          >Previous</Link>
          <Link
            v-if="attendance.meta.current_page < attendance.meta.last_page"
            :href="route('portal.attendance', { page: attendance.meta.current_page + 1, dog_id: selectedDog || undefined })"
            class="btn-secondary text-xs py-1.5 px-3"
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
