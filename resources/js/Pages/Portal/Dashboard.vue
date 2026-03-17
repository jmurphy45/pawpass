<template>
  <PortalLayout>
    <div class="space-y-8">
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>

      <!-- Dog credit cards -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800">My Dogs</h2>
          <Link :href="route('portal.dogs.index')" class="text-sm text-indigo-600 hover:underline">Manage dogs →</Link>
        </div>

        <div v-if="dogs.length === 0" class="rounded-2xl bg-white border border-gray-200 p-8 text-center text-gray-500 text-sm">
          No dogs yet.
          <Link :href="route('portal.dogs.create')" class="text-indigo-600 hover:underline ml-1">Add your first dog →</Link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="dog in dogs"
            :key="dog.id"
            class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm"
          >
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="font-semibold text-gray-900">{{ dog.name }}</p>
                <p v-if="dog.breed" class="text-xs text-gray-500">{{ dog.breed }}</p>
              </div>
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="statusClasses(dog.credit_status)"
              >{{ statusLabel(dog.credit_status) }}</span>
            </div>

            <!-- Credit progress bar -->
            <div class="mb-2">
              <div class="flex items-center justify-between text-sm mb-1">
                <span class="text-gray-500">Credits</span>
                <span class="font-semibold">{{ dog.credit_balance }}</span>
              </div>
              <div class="h-2 w-full rounded-full bg-gray-100">
                <div
                  class="h-2 rounded-full transition-all"
                  :class="progressColor(dog.credit_status)"
                  :style="{ width: progressWidth(dog.credit_balance) }"
                />
              </div>
            </div>

            <Link
              :href="route('portal.purchase')"
              class="mt-3 block text-center text-xs font-medium text-indigo-600 hover:underline"
            >Buy Package →</Link>
          </div>
        </div>
      </section>

      <!-- Quick Actions -->
      <section>
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <QuickAction :href="route('portal.purchase')" label="Buy Credits" icon="💳" />
          <QuickAction :href="route('portal.subscribe')" label="Subscribe" icon="♻️" />
          <QuickAction :href="route('portal.attendance')" label="Attendance" icon="📋" />
          <QuickAction :href="route('portal.history')" label="Invoices" icon="🧾" />
        </div>
      </section>

      <!-- Recent Notifications -->
      <section v-if="recentNotifications.length > 0">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800">Recent Notifications</h2>
          <Link :href="route('portal.notifications')" class="text-sm text-indigo-600 hover:underline">View all →</Link>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
          <div v-for="n in recentNotifications" :key="n.id" class="flex items-start gap-3 p-4">
            <span class="text-xl leading-none mt-0.5">{{ notifIcon(n.type) }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-800">{{ n.data?.message ?? n.type }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(n.created_at) }}</p>
            </div>
            <span v-if="!n.read_at" class="mt-1.5 h-2 w-2 rounded-full bg-indigo-500 shrink-0" />
          </div>
        </div>
      </section>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { h, defineComponent } from 'vue';
import { Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

interface DogCard {
  id: string;
  name: string;
  breed: string | null;
  credit_balance: number;
  credits_expire_at: string | null;
  credit_status: 'ok' | 'low' | 'empty';
}

interface NotifCard {
  id: string;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
}

defineProps<{
  dogs: DogCard[];
  recentNotifications: NotifCard[];
}>();

function statusClasses(status: string) {
  return {
    ok: 'bg-green-100 text-green-700',
    low: 'bg-yellow-100 text-yellow-700',
    empty: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-gray-100 text-gray-600';
}

function statusLabel(status: string) {
  return { ok: 'Good', low: 'Low', empty: 'Empty' }[status] ?? status;
}

function progressColor(status: string) {
  return {
    ok: 'bg-green-500',
    low: 'bg-yellow-400',
    empty: 'bg-red-400',
  }[status] ?? 'bg-gray-300';
}

function progressWidth(balance: number) {
  const pct = Math.min(100, Math.max(0, (balance / Math.max(balance, 10)) * 100));
  return `${pct}%`;
}

function notifIcon(type: string) {
  if (type.includes('credits.low')) return '⚠️';
  if (type.includes('credits.empty')) return '🚨';
  if (type.includes('payment')) return '✅';
  if (type.includes('subscription')) return '♻️';
  return '🔔';
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

const QuickAction = defineComponent({
  props: {
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: String, required: true },
  },
  setup(props) {
    return () => h(Link, {
      href: props.href,
      class: 'flex flex-col items-center justify-center gap-2 rounded-2xl bg-white border border-gray-200 p-5 hover:bg-gray-50 transition-colors text-center shadow-sm',
    }, () => [
      h('span', { class: 'text-2xl' }, props.icon),
      h('span', { class: 'text-sm font-medium text-gray-700' }, props.label),
    ]);
  },
});
</script>
