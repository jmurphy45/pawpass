<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
  title: string;
  subtitle?: string;
  icon?: Component;
}>();
</script>

<template>
  <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
    <div v-if="icon" class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-surface-subtle">
      <component :is="icon" class="h-7 w-7 text-text-muted" />
    </div>
    <p class="text-sm font-semibold text-text-body">{{ title }}</p>
    <p v-if="subtitle" class="mt-1 text-sm text-text-muted max-w-xs">{{ subtitle }}</p>
    <slot />
  </div>
</template>
