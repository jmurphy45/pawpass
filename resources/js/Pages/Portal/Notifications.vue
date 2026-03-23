<template>
  <PortalLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-text-body min-w-0">Notifications</h1>
        <form @submit.prevent="readAll">
          <button
            type="submit"
            :disabled="readAllForm.processing"
            class="text-sm font-medium text-indigo-600 hover:underline disabled:opacity-60"
          >Mark all read</button>
        </form>
      </div>

      <div v-if="notifications.data.length === 0" class="card p-12 text-center">
        <p class="text-4xl mb-3">🔔</p>
        <p class="font-semibold text-text-body">All caught up</p>
        <p class="text-sm text-text-muted mt-1">No notifications yet</p>
      </div>

      <div v-else class="card overflow-hidden">
        <div
          v-for="n in notifications.data"
          :key="n.id"
          class="list-row gap-4 items-start"
          :class="{ 'bg-indigo-50/40': !n.read_at }"
        >
          <!-- Unread pulse dot -->
          <span
            class="mt-1.5 h-2 w-2 rounded-full shrink-0 transition-colors"
            :class="n.read_at ? 'bg-transparent' : 'bg-indigo-500 animate-pulse'"
          />

          <span class="text-xl leading-none mt-0.5 shrink-0">{{ notifIcon(n.type) }}</span>

          <div class="flex-1 min-w-0">
            <p class="text-sm text-text-body">{{ n.data?.message ?? formatType(n.type) }}</p>
            <p class="text-xs text-text-muted mt-0.5">{{ formatDate(n.created_at) }}</p>
          </div>

          <button
            v-if="!n.read_at"
            class="text-xs text-text-muted hover:text-indigo-600 shrink-0 transition-colors"
            @click="markRead(n.id)"
          >Mark read</button>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="notifications.meta.last_page > 1" class="flex items-center justify-between text-sm">
        <p class="text-text-muted">Showing {{ notifications.data.length }} of {{ notifications.meta.total }}</p>
        <div class="flex gap-2">
          <Link
            v-if="notifications.meta.current_page > 1"
            :href="route('portal.notifications', { page: notifications.meta.current_page - 1 })"
            class="btn-secondary text-xs py-1.5 px-3"
          >Previous</Link>
          <Link
            v-if="notifications.meta.current_page < notifications.meta.last_page"
            :href="route('portal.notifications', { page: notifications.meta.current_page + 1 })"
            class="btn-secondary text-xs py-1.5 px-3"
          >Next</Link>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { Link, useForm, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Notification, PaginatedResponse } from '@/types';

defineProps<{
  notifications: PaginatedResponse<Notification>;
}>();

const readAllForm = useForm({});

function readAll() {
  readAllForm.post(route('portal.notifications.read-all'));
}

function markRead(id: string) {
  router.patch(route('portal.notifications.read', { id }), {}, { preserveState: true });
}

function notifIcon(type: string): string {
  if (type.includes('credits.low')) return '⚠️';
  if (type.includes('credits.empty')) return '🚨';
  if (type.includes('payment')) return '✅';
  if (type.includes('subscription')) return '♻️';
  if (type.includes('auth')) return '🔐';
  return '🔔';
}

function formatType(type: string) {
  return type.split('.').pop()?.replace(/_/g, ' ') ?? type;
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleString(undefined, {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}
</script>
