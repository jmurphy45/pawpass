<template>
  <div
    class="min-h-screen flex items-center justify-center px-4"
    style="background-color: #faf9f6; background-image: radial-gradient(ellipse at top left, rgba(79,70,229,0.04) 0%, transparent 60%), radial-gradient(ellipse at bottom right, rgba(245,158,11,0.04) 0%, transparent 60%);"
  >
    <div class="w-full max-w-sm">
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
        <p class="mt-1.5 text-sm text-text-muted">You've been invited to the staff portal</p>
      </div>

      <AppCard :padded="true" style="box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.04);">
        <p class="text-sm text-text-muted mb-5">Click below to activate your account and sign in. You'll use a magic link to sign in going forward.</p>
        <form @submit.prevent="submit">
          <AppButton
            type="submit"
            variant="primary"
            :disabled="form.processing"
            class="w-full justify-center py-2.5"
          >
            {{ form.processing ? 'Activating…' : 'Accept invitation & sign in' }}
          </AppButton>
        </form>
      </AppCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{ token: string }>();

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);

const form = useForm({});

function submit() {
  form.post(route('admin.invite.store', { token: props.token }));
}
</script>
