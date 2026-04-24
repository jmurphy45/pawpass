<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-8"
    style="background-color: #faf9f6; background-image: radial-gradient(ellipse at top left, rgba(79,70,229,0.04) 0%, transparent 60%), radial-gradient(ellipse at bottom right, rgba(245,158,11,0.04) 0%, transparent 60%);"
  >
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div class="flex justify-center mb-3">
          <svg width="36" height="36" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="14" cy="14" r="14" fill="#4f46e5" />
            <ellipse cx="10" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9" />
            <ellipse cx="18" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9" />
            <ellipse cx="7" cy="15" rx="2.2" ry="3" transform="rotate(-20 7 15)" fill="white" opacity="0.9" />
            <ellipse cx="21" cy="15" rx="2.2" ry="3" transform="rotate(20 21 15)" fill="white" opacity="0.9" />
            <ellipse cx="14" cy="19" rx="5" ry="4" fill="white" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-text-body">{{ tenant?.name ?? 'PawPass' }}</h1>
        <p class="mt-1.5 text-sm text-text-muted">Create your customer account</p>
      </div>

      <AppCard :padded="true" style="box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.04);">
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Full Name</label>
            <input
              v-model="form.name"
              type="text"
              autocomplete="name"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              :class="{ 'border-red-500': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Email</label>
            <input
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              :class="{ 'border-red-500': form.errors.email }"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Password</label>
            <input
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              :class="{ 'border-red-500': form.errors.password }"
            />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Confirm Password</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">
              Phone <span class="text-text-muted font-normal">(optional)</span>
            </label>
            <input
              v-model="form.phone"
              type="tel"
              autocomplete="tel"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              :class="{ 'border-red-500': form.errors.phone }"
            />
            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
          </div>

          <AppButton
            type="submit"
            variant="primary"
            :disabled="form.processing"
            class="w-full justify-center py-2.5"
            :style="{ backgroundColor: accentColor }"
          >
            {{ form.processing ? 'Creating account…' : 'Create account' }}
          </AppButton>
        </form>

        <p class="mt-6 text-center text-sm text-text-muted">
          Already have an account?
          <Link :href="route('portal.login')" class="font-medium text-indigo-600 hover:underline">Sign in</Link>
        </p>
      </AppCard>
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

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
});

function submit() {
  form.post(route('portal.register.store'));
}
</script>
