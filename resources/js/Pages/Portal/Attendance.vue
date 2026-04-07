<template>
  <PortalLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-text-body">Attendance</h1>
        <select
          v-model="selectedDog"
          class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition max-w-[180px]"
          @change="filterByDog"
        >
          <option value="">All dogs</option>
          <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
        </select>
      </div>

      <AppCard v-if="attendance.data.length === 0" class="p-12 text-center">
        <div class="mx-auto h-14 w-14 rounded-full bg-surface-subtle flex items-center justify-center mb-3">
          <svg class="h-7 w-7 text-text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
          </svg>
        </div>
        <p class="font-semibold text-text-body">No attendance records yet</p>
        <p class="text-sm text-text-muted mt-1">Records will appear here after your first check-in</p>
      </AppCard>

      <AppCard v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-surface-subtle border-b border-border">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Dog</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Check In</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Check Out</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="a in attendance.data"
              :key="a.id"
              class="border-b border-border last:border-0 hover:bg-surface-subtle/50 transition-colors"
            >
              <td class="px-4 py-3 font-medium text-text-body">{{ a.dog_name }}</td>
              <td class="px-4 py-3 text-text-muted">{{ formatDatetime(a.checked_in_at) }}</td>
              <td class="px-4 py-3">
                <span v-if="a.checked_out_at" class="text-text-muted">{{ formatDatetime(a.checked_out_at) }}</span>
                <AppBadge v-else color="green" class="animate-pulse">Here now</AppBadge>
              </td>
            </tr>
          </tbody>
        </table>
      </AppCard>

      <!-- Pagination -->
      <div v-if="attendance.meta.last_page > 1" class="flex items-center justify-between text-sm">
        <p class="text-text-muted">Showing {{ attendance.data.length }} of {{ attendance.meta.total }}</p>
        <div class="flex gap-2">
          <Link
            v-if="attendance.meta.current_page > 1"
            :href="route('portal.attendance', { page: attendance.meta.current_page - 1, dog_id: selectedDog || undefined })"
          ><AppButton variant="secondary" class="text-xs py-1.5 px-3">Previous</AppButton></Link>
          <Link
            v-if="attendance.meta.current_page < attendance.meta.last_page"
            :href="route('portal.attendance', { page: attendance.meta.current_page + 1, dog_id: selectedDog || undefined })"
          ><AppButton variant="secondary" class="text-xs py-1.5 px-3">Next</AppButton></Link>
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
