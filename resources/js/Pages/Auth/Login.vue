<template>
  <div
    class="min-h-screen flex items-center justify-center px-4"
    style="background-color: #faf9f6; background-image: radial-gradient(ellipse at top left, rgba(79,70,229,0.04) 0%, transparent 60%), radial-gradient(ellipse at bottom right, rgba(245,158,11,0.04) 0%, transparent 60%);"
  >
    <div class="w-full max-w-sm">
      <!-- Logo + tenant name -->
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
        <p class="mt-1.5 text-sm text-text-muted">Sign in to your account</p>
      </div>

      <AppCard :padded="true" style="box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.04);">
        <!-- Method tabs -->
        <div class="flex rounded-lg bg-gray-100 p-0.5 mb-6">
          <button
            type="button"
            class="flex-1 text-sm py-1.5 rounded-md font-medium transition-all duration-200"
            :class="method === 'link' ? 'bg-white text-text-body shadow-sm' : 'text-text-muted hover:text-text-body'"
            @click="method = 'link'"
          >
            Email link
          </button>
          <button
            type="button"
            class="flex-1 text-sm py-1.5 rounded-md font-medium transition-all duration-200"
            :class="method === 'password' ? 'bg-white text-text-body shadow-sm' : 'text-text-muted hover:text-text-body'"
            @click="method = 'password'"
          >
            Password
          </button>
        </div>

        <!-- Magic link panel -->
        <template v-if="method === 'link'">
          <div v-if="sent" class="text-center py-2">
            <div class="text-4xl mb-4">&#9993;</div>
            <p class="text-sm font-medium text-text-body mb-1">Check your inbox</p>
            <p class="text-sm text-text-muted">
              We sent a sign-in link to <strong>{{ linkEmail }}</strong>. It expires in 15 minutes.
            </p>
            <button class="mt-5 text-xs text-indigo-600 hover:underline" @click="sent = false">
              Use a different email
            </button>
          </div>

          <form v-else @submit.prevent="submitLink" class="space-y-5">
            <div>
              <label class="block text-sm font-medium text-text-body mb-1.5">Email</label>
              <input
                v-model="linkEmail"
                type="email"
                autocomplete="email"
                class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                :class="{ 'border-red-500': linkError }"
                required
              />
              <p v-if="linkError" class="mt-1 text-xs text-red-600">{{ linkError }}</p>
            </div>

            <AppButton
              type="submit"
              variant="primary"
              :disabled="linkLoading"
              class="w-full justify-center py-2.5"
              :style="{ backgroundColor: accentColor }"
            >
              {{ linkLoading ? 'Sending…' : 'Send sign-in link' }}
            </AppButton>
          </form>
        </template>

        <!-- Password panel -->
        <template v-else>
          <form @submit.prevent="submitPassword" class="space-y-5">
            <div>
              <label class="block text-sm font-medium text-text-body mb-1.5">Email</label>
              <input
                v-model="passwordForm.email"
                type="email"
                autocomplete="email"
                class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                :class="{ 'border-red-500': passwordForm.errors.email }"
                required
              />
              <p v-if="passwordForm.errors.email" class="mt-1 text-xs text-red-600">
                {{ passwordForm.errors.email }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-body mb-1.5">Password</label>
              <input
                v-model="passwordForm.password"
                type="password"
                autocomplete="current-password"
                class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                :class="{ 'border-red-500': passwordForm.errors.password }"
                required
              />
              <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">
                {{ passwordForm.errors.password }}
              </p>
            </div>

            <AppButton
              type="submit"
              variant="primary"
              :disabled="passwordForm.processing"
              class="w-full justify-center py-2.5"
              :style="{ backgroundColor: accentColor }"
            >
              {{ passwordForm.processing ? 'Signing in…' : 'Sign in' }}
            </AppButton>
          </form>
        </template>

        <p class="mt-6 text-center text-sm text-text-muted">
          Don't have an account?
          <Link :href="route('portal.register')" class="font-medium text-indigo-600 hover:underline">
            Register
          </Link>
        </p>
      </AppCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const accentColor = computed(() => tenant.value?.primary_color ?? '#4f46e5');

const method = ref<'link' | 'password'>('link');

// Magic link state
const linkEmail = ref('');
const linkLoading = ref(false);
const linkError = ref('');
const sent = ref(false);

function collectFingerprint(): Record<string, string> {
  return {
    ua: navigator.userAgent ?? '',
    screen: `${screen.width}x${screen.height}`,
    lang: navigator.language ?? '',
    tz: Intl.DateTimeFormat().resolvedOptions().timeZone ?? '',
    platform: navigator.platform ?? '',
  };
}

async function submitLink() {
  linkError.value = '';
  linkLoading.value = true;
  try {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    await fetch('/auth/magic-link/request', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ email: linkEmail.value, fp_components: collectFingerprint() }),
    });
    sent.value = true;
  } catch {
    linkError.value = 'Something went wrong. Please try again.';
  } finally {
    linkLoading.value = false;
  }
}

// Password form
const passwordForm = useForm({ email: '', password: '' });

function submitPassword() {
  passwordForm.post(route('portal.password.login'));
}
</script>
