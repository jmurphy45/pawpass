<template>
  <PortalLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold text-text-body">Notifications</h1>
          <AppBadge v-if="unreadCount > 0" color="yellow">{{ unreadCount }}</AppBadge>
        </div>
        <form @submit.prevent="readAll">
          <AppButton
            type="submit"
            variant="secondary"
            :disabled="readAllForm.processing"
            class="text-xs py-1.5 px-3 disabled:opacity-60"
          >Mark all read</AppButton>
        </form>
      </div>

      <!-- Empty state -->
      <AppCard v-if="notifications.data.length === 0" class="p-12 text-center">
        <div class="mx-auto mb-4 w-14 h-14 rounded-full bg-surface-subtle flex items-center justify-center">
          <svg class="h-7 w-7 text-text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
          </svg>
        </div>
        <p class="font-semibold text-text-body">All caught up</p>
        <p class="text-sm text-text-muted mt-1">No notifications yet</p>
      </AppCard>

      <!-- Notification list -->
      <AppCard v-else class="overflow-hidden">
        <div
          v-for="n in notifications.data"
          :key="n.id"
          class="notif-row"
          :class="{ 'notif-row--unread': !n.read_at }"
        >
          <!-- Left color strip for unread -->
          <div
            class="notif-strip"
            :style="{ background: !n.read_at ? notifAccent(n.type ?? '') : 'transparent' }"
          />

          <!-- Icon circle -->
          <div class="notif-icon" :style="{ background: notifBg(n.type ?? '') }">
            <svg v-if="n.type?.includes('credits.empty')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
            <svg v-else-if="n.type?.includes('credits.low')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <svg v-else-if="n.type?.includes('payment')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            <svg v-else-if="n.type?.includes('subscription')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            <svg v-else-if="n.type?.includes('auth')" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
            <svg v-else fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
          </div>

          <!-- Content -->
          <div class="notif-body">
            <p class="notif-message" :class="{ 'font-semibold': !n.read_at }">
              {{ n.message ?? formatType(n.type) }}
            </p>
            <div class="notif-meta">
              <span>{{ formatDate(n.created_at) }}</span>
              <button
                v-if="!n.read_at"
                class="notif-mark-read"
                @click="markRead(n.id)"
              >Mark read</button>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Pagination -->
      <div v-if="notifications.meta.last_page > 1" class="flex items-center justify-between text-sm">
        <p class="text-text-muted">Showing {{ notifications.data.length }} of {{ notifications.meta.total }}</p>
        <div class="flex gap-2">
          <Link
            v-if="notifications.meta.current_page > 1"
            :href="route('portal.notifications', { page: notifications.meta.current_page - 1 })"
          ><AppButton variant="secondary" class="text-xs py-1.5 px-3">Previous</AppButton></Link>
          <Link
            v-if="notifications.meta.current_page < notifications.meta.last_page"
            :href="route('portal.notifications', { page: notifications.meta.current_page + 1 })"
          ><AppButton variant="secondary" class="text-xs py-1.5 px-3">Next</AppButton></Link>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Notification, PaginatedResponse } from '@/types';

const props = defineProps<{
  notifications: PaginatedResponse<Notification>;
}>();

const unreadCount = computed(() =>
  props.notifications.data.filter(n => !n.read_at).length,
);

const readAllForm = useForm({});

function readAll() {
  readAllForm.post(route('portal.notifications.read-all'));
}

function markRead(id: string) {
  router.patch(route('portal.notifications.read', { id }), {}, { preserveState: true });
}

function notifBg(type: string): string {
  if (type.includes('credits.empty')) return '#fee2e2';
  if (type.includes('credits.low')) return '#fef3c7';
  if (type.includes('payment')) return '#dcfce7';
  if (type.includes('subscription')) return '#dbeafe';
  if (type.includes('auth')) return '#f3f4f6';
  return '#e0e7ff';
}

function notifAccent(type: string): string {
  if (type.includes('credits.empty')) return '#ef4444';
  if (type.includes('credits.low')) return '#f59e0b';
  if (type.includes('payment')) return '#16a34a';
  if (type.includes('subscription')) return '#3b82f6';
  return '#6366f1';
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

<style scoped>
.notif-row {
  display: flex;
  align-items: flex-start;
  gap: 0.875rem;
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid #f0ede8;
  transition: background 140ms ease;
}

.notif-row:last-child {
  border-bottom: none;
}

.notif-row:hover {
  background: #faf9f6;
}

.notif-row--unread {
  background: #faf9f6;
}

.notif-strip {
  width: 3px;
  align-self: stretch;
  border-radius: 2px;
  flex-shrink: 0;
  margin: -0.875rem 0;
}

.notif-icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.notif-icon svg {
  width: 1rem;
  height: 1rem;
  color: #374151;
}

.notif-body {
  flex: 1;
  min-width: 0;
}

.notif-message {
  font-size: 0.875rem;
  color: #2a2522;
  line-height: 1.4;
}

.notif-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.25rem;
}

.notif-meta > span {
  font-size: 0.75rem;
  color: #9ca3af;
}

.notif-mark-read {
  font-size: 0.6875rem;
  font-weight: 500;
  color: #9ca3af;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: color 150ms ease;
}

.notif-mark-read:hover {
  color: #6b6560;
}
</style>
