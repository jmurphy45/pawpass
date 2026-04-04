<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'danger';
  size?: 'sm' | 'md' | 'lg';
  disabled?: boolean;
  loading?: boolean;
  type?: 'button' | 'submit' | 'reset';
}>(), {
  variant: 'primary',
  size: 'md',
  disabled: false,
  loading: false,
  type: 'button',
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-1.5 font-semibold rounded-lg transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed',
      size === 'sm' ? 'px-3 py-1.5 text-xs' : size === 'lg' ? 'px-5 py-2.5 text-base' : 'px-4 py-2 text-sm',
      variant === 'primary' ? 'bg-indigo-600 text-white hover:bg-indigo-700' :
      variant === 'danger'  ? 'bg-red-600 text-white hover:bg-red-700' :
                              'bg-white text-text-body border border-border-warm hover:bg-surface-subtle',
    ]"
  >
    <svg v-if="loading" class="animate-spin -ml-0.5 h-4 w-4" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
    </svg>
    <slot />
  </button>
</template>
