<template>
  <PortalLayout>
    <div class="max-w-2xl space-y-8">
      <h1 class="text-2xl font-bold text-gray-900">Account Settings</h1>

      <!-- Profile section -->
      <section class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-5">Profile</h2>
        <form @submit.prevent="saveProfile" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input
              v-model="profileForm.name"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': profileForm.errors.name }"
            />
            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-600">{{ profileForm.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              v-model="profileForm.email"
              type="email"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': profileForm.errors.email }"
            />
            <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-600">{{ profileForm.errors.email }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input
              v-model="profileForm.phone"
              type="tel"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <button
            type="submit"
            :disabled="profileForm.processing"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
          >{{ profileForm.processing ? 'Saving…' : 'Save Profile' }}</button>
        </form>
      </section>

      <!-- Password section -->
      <section class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-5">Change Password</h2>
        <form @submit.prevent="savePassword" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
            <input
              v-model="passwordForm.current_password"
              type="password"
              autocomplete="current-password"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': passwordForm.errors.current_password }"
            />
            <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input
              v-model="passwordForm.password"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': passwordForm.errors.password }"
            />
            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input
              v-model="passwordForm.password_confirmation"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <button
            type="submit"
            :disabled="passwordForm.processing"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
          >{{ passwordForm.processing ? 'Updating…' : 'Update Password' }}</button>
        </form>
      </section>

      <!-- Notification preferences -->
      <section class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-5">Notification Preferences</h2>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide w-1/2">Type</th>
                <th class="text-center py-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                <th class="text-center py-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">SMS</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="type in notifTypes" :key="type.key">
                <td class="py-3">
                  <p class="font-medium text-gray-800">{{ type.label }}</p>
                  <p v-if="isCritical(type.key)" class="text-xs text-gray-400">Always on</p>
                </td>
                <td class="py-3 px-3 text-center">
                  <input
                    type="checkbox"
                    :checked="getPref(type.key, 'email')"
                    :disabled="isCritical(type.key)"
                    class="h-4 w-4 rounded text-indigo-600 disabled:opacity-40"
                    @change="(e) => setPref(type.key, 'email', (e.target as HTMLInputElement).checked)"
                  />
                </td>
                <td class="py-3 px-3 text-center">
                  <input
                    type="checkbox"
                    :checked="getPref(type.key, 'sms')"
                    :disabled="isCritical(type.key)"
                    class="h-4 w-4 rounded text-indigo-600 disabled:opacity-40"
                    @change="(e) => setPref(type.key, 'sms', (e.target as HTMLInputElement).checked)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <button
          @click="savePrefs"
          :disabled="prefsForm.processing"
          class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
        >{{ prefsForm.processing ? 'Saving…' : 'Save Preferences' }}</button>
      </section>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

interface Profile { name: string; email: string; phone: string | null; }
interface NotifPref { type: string; channel: string; is_enabled: boolean; }

const props = defineProps<{
  profile: Profile;
  notifPrefs: NotifPref[];
  criticalTypes: string[];
}>();

// Profile form
const profileForm = useForm({
  name: props.profile.name,
  email: props.profile.email,
  phone: props.profile.phone ?? '',
});

function saveProfile() {
  profileForm.patch(route('portal.account.update'));
}

// Password form
const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

function savePassword() {
  passwordForm.patch(route('portal.account.password'), {
    onFinish: () => passwordForm.reset(),
  });
}

// Notification prefs
const notifTypes = [
  { key: 'payment.confirmed', label: 'Payment Confirmed' },
  { key: 'credits.low', label: 'Credits Low' },
  { key: 'credits.empty', label: 'Credits Empty' },
  { key: 'subscription.renewed', label: 'Subscription Renewed' },
  { key: 'auth.password_reset', label: 'Password Reset' },
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
