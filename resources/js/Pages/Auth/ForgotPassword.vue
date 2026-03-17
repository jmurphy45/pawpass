<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">{{ tenant?.name ?? 'PawPass' }}</h1>
        <p class="mt-2 text-sm text-gray-600">Reset your password</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <div
          v-if="flash.success"
          class="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm"
        >
          {{ flash.success }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
            <input
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': form.errors.email }"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-opacity disabled:opacity-60"
            :style="{ backgroundColor: accentColor }"
          >
            {{ form.processing ? 'Sending…' : 'Send reset link' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
          <Link :href="route('portal.login')" class="font-medium text-indigo-600 hover:underline">Back to login</Link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const accentColor = computed(() => tenant.value?.primary_color ?? '#4f46e5');
const flash = computed(() => page.props.flash ?? { success: null, error: null });

const form = useForm({ email: '' });

function submit() {
  form.post(route('portal.forgot-password.store'));
}
</script>
