<template>
  <PortalLayout>
    <div class="max-w-2xl space-y-6">
      <h1 class="text-2xl font-bold text-text-body">Account Settings</h1>

      <!-- Profile section -->
      <AppCard class="overflow-hidden">
        <div class="ac-section-head">
          <div class="ac-section-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
          </div>
          <span class="ac-section-title">Profile</span>
        </div>
        <form @submit.prevent="saveProfile" class="p-5 space-y-4">
          <div>
            <label class="ac-label">Full Name</label>
            <input
              v-model="profileForm.name"
              type="text"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              :class="{ 'border-red-500': profileForm.errors.name }"
            />
            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-600">{{ profileForm.errors.name }}</p>
          </div>

          <div>
            <label class="ac-label">Email</label>
            <input
              v-model="profileForm.email"
              type="email"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              :class="{ 'border-red-500': profileForm.errors.email }"
            />
            <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-600">{{ profileForm.errors.email }}</p>
          </div>

          <div>
            <label class="ac-label">Phone</label>
            <input
              v-model="profileForm.phone"
              type="tel"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
            />
          </div>

          <AppButton
            type="submit"
            variant="primary"
            :disabled="profileForm.processing"
            class="text-sm disabled:opacity-60"
          >{{ profileForm.processing ? 'Saving…' : 'Save Profile' }}</AppButton>
        </form>
      </AppCard>

      <!-- Notification preferences -->
      <AppCard class="overflow-hidden">
        <div class="ac-section-head">
          <div class="ac-section-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
          </div>
          <span class="ac-section-title">Notification Preferences</span>
        </div>

        <div class="p-5">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border">
                  <th class="text-left py-2 text-xs font-semibold text-text-muted uppercase tracking-wide w-1/2">Type</th>
                  <th class="text-center py-2 px-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Email</th>
                  <th class="text-center py-2 px-3 text-xs font-semibold text-text-muted uppercase tracking-wide">SMS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-subtle">
                <tr v-for="type in notifTypes" :key="type.key">
                  <td class="py-3">
                    <p class="font-medium text-text-body">{{ type.label }}</p>
                    <p v-if="isCritical(type.key)" class="text-xs text-text-muted">Always on</p>
                  </td>
                  <td class="py-3 px-3 text-center">
                    <input
                      type="checkbox"
                      :checked="getPref(type.key, 'email')"
                      :disabled="isCritical(type.key)"
                      class="h-4 w-4 rounded disabled:opacity-40"
                      @change="(e) => setPref(type.key, 'email', (e.target as HTMLInputElement).checked)"
                    />
                  </td>
                  <td class="py-3 px-3 text-center">
                    <input
                      type="checkbox"
                      :checked="getPref(type.key, 'sms')"
                      :disabled="isCritical(type.key)"
                      class="h-4 w-4 rounded disabled:opacity-40"
                      @change="(e) => setPref(type.key, 'sms', (e.target as HTMLInputElement).checked)"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <AppButton
            variant="primary"
            @click="savePrefs"
            :disabled="prefsForm.processing"
            class="text-sm mt-4 disabled:opacity-60"
          >{{ prefsForm.processing ? 'Saving…' : 'Save Preferences' }}</AppButton>
        </div>
      </AppCard>

      <!-- Browser push notifications -->
      <AppCard class="overflow-hidden">
        <div class="ac-section-head">
          <div class="ac-section-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3" />
            </svg>
          </div>
          <span class="ac-section-title">Browser Notifications</span>
        </div>
        <div class="p-5">
          <div v-if="!push.isSupported.value" class="text-sm text-text-muted">
            Browser notifications are not supported in this browser.
          </div>
          <div v-else-if="push.permission.value === 'denied'" class="text-sm text-text-muted">
            Browser notifications are blocked. Please update your browser permissions to enable them.
          </div>
          <div v-else class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-medium text-text-body">Push notifications</p>
              <p class="text-xs text-text-muted mt-0.5">Receive alerts even when the app is closed.</p>
            </div>
            <button
              v-if="!push.isSubscribed.value"
              class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
              :disabled="!vapidPublicKey"
              @click="push.subscribe(vapidPublicKey!, 'portal').then(() => push.checkSubscription())"
            >Enable</button>
            <button
              v-else
              class="shrink-0 rounded-md border border-border px-3 py-1.5 text-sm font-medium text-text-body hover:bg-surface-subtle"
              @click="push.unsubscribe('portal').then(() => push.checkSubscription())"
            >Disable</button>
          </div>
        </div>
      </AppCard>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { usePushNotifications } from '@/composables/usePushNotifications';

interface Profile { name: string; email: string; phone: string | null; }
interface NotifPref { type: string; channel: string; is_enabled: boolean; }

const props = defineProps<{
  profile: Profile;
  notifPrefs: NotifPref[];
  criticalTypes: string[];
  vapidPublicKey?: string | null;
}>();

const push = usePushNotifications();
onMounted(() => push.checkSubscription());

// Profile form
const profileForm = useForm({
  name: props.profile.name,
  email: props.profile.email,
  phone: props.profile.phone ?? '',
});

function saveProfile() {
  profileForm.patch(route('portal.account.update'));
}

// Notification prefs
const notifTypes = [
  { key: 'payment.confirmed', label: 'Payment Confirmed' },
  { key: 'credits.low', label: 'Credits Low' },
  { key: 'credits.empty', label: 'Credits Empty' },
  { key: 'subscription.renewed', label: 'Subscription Renewed' },
];

const localPrefs = ref<NotifPref[]>(JSON.parse(JSON.stringify(props.notifPrefs)));

function getPref(type: string, channel: string): boolean {
  return localPrefs.value.find(p => p.type === type && p.channel === channel)?.is_enabled ?? true;
}

function setPref(type: string, channel: string, value: boolean) {
  const existing = localPrefs.value.find(p => p.type === type && p.channel === channel);
  if (existing) {
    existing.is_enabled = value;
  } else {
    localPrefs.value.push({ type, channel, is_enabled: value });
  }
}

function isCritical(type: string) {
  return props.criticalTypes.includes(type);
}

const prefsForm = useForm({ prefs: [] as NotifPref[] });

function savePrefs() {
  prefsForm.prefs = localPrefs.value;
  prefsForm.put(route('portal.account.notification-prefs'));
}
</script>

<style scoped>
.ac-section-head {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid #f0ede8;
}

.ac-section-icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 0.375rem;
  background: #f0ede8;
  color: #6b6560;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ac-section-icon svg {
  width: 1rem;
  height: 1rem;
}

.ac-section-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #2a2522;
}

.ac-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #2a2522;
  margin-bottom: 0.375rem;
}
</style>
