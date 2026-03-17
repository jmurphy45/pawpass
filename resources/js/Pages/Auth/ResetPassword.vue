<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">{{ tenant?.name ?? 'PawPass' }}</h1>
        <p class="mt-2 text-sm text-gray-600">Set a new password</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': form.errors.email }"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': form.errors.password }"
            />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div v-if="form.errors.token" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-red-700 text-sm">
            {{ form.errors.token }}
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-opacity disabled:opacity-60"
            :style="{ backgroundColor: accentColor }"
          >
            {{ form.processing ? 'Resetting…' : 'Reset password' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface Props {
  token: string;
  email: string;
}

const props = defineProps<Props>();
const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const accentColor = computed(() => tenant.value?.primary_color ?? '#4f46e5');

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

function submit() {
  form.post(route('portal.reset-password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>
