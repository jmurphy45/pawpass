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
            <circle cx="14" cy="14" r="14" fill="#4f46e5"/>
            <ellipse cx="10" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
            <ellipse cx="18" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
            <ellipse cx="7" cy="15" rx="2.2" ry="3" transform="rotate(-20 7 15)" fill="white" opacity="0.9"/>
            <ellipse cx="21" cy="15" rx="2.2" ry="3" transform="rotate(20 21 15)" fill="white" opacity="0.9"/>
            <ellipse cx="14" cy="19" rx="5" ry="4" fill="white"/>
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-text-body">{{ tenant?.name ?? 'PawPass' }}</h1>
        <p class="mt-1.5 text-sm text-text-muted">Staff portal — sign in to your account</p>
      </div>

      <div class="card-padded" style="box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.04);">

        <!-- Sent state -->
        <div v-if="sent" class="text-center py-2">
          <div class="text-4xl mb-4">&#9993;</div>
          <p class="text-sm font-medium text-text-body mb-1">Check your inbox</p>
          <p class="text-sm text-text-muted">We sent a sign-in link to <strong>{{ email }}</strong>. It expires in 15 minutes.</p>
          <button class="mt-5 text-xs text-indigo-600 hover:underline" @click="sent = false">Use a different email</button>
        </div>

        <!-- Request form -->
        <form v-else @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Email</label>
            <input
              v-model="email"
              type="email"
              autocomplete="email"
              class="input"
              :class="{ 'input-error': error }"
              required
            />
            <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full justify-center py-2.5"
          >
            {{ loading ? 'Sending…' : 'Send sign-in link' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);

const email = ref('');
const loading = ref(false);
const sent = ref(false);
const error = ref('');

function collectFingerprint(): string {
  const components = {
    ua: navigator.userAgent ?? '',
    screen: `${screen.width}x${screen.height}`,
    lang: navigator.language ?? '',
    tz: Intl.DateTimeFormat().resolvedOptions().timeZone ?? '',
    platform: navigator.platform ?? '',
  };
  return btoa(JSON.stringify(components)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

async function submit() {
  error.value = '';
  loading.value = true;

  try {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    await fetch('/auth/magic-link/request', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({
        email: email.value,
        fp_components: JSON.parse(atob(collectFingerprint().replace(/-/g, '+').replace(/_/g, '/'))),
      }),
    });

    sent.value = true;
  } catch {
    error.value = 'Something went wrong. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>
