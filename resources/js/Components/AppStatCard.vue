<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
  label: string;
  value: string | number;
  delta?: string | null;
  deltaPositive?: boolean;
  icon?: Component;
}>();
</script>

<template>
  <div class="bg-white border border-border-warm rounded-xl shadow-card p-5 flex flex-col gap-3">
    <div class="flex items-center justify-between">
      <span class="text-sm font-medium text-text-muted">{{ label }}</span>
      <component v-if="icon" :is="icon" class="h-5 w-5 text-text-muted" />
    </div>
    <div class="flex items-end justify-between gap-2">
      <span class="text-2xl font-semibold text-text-body">{{ value }}</span>
      <span
        v-if="delta"
        :class="deltaPositive ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50'"
        class="text-xs font-medium px-2 py-0.5 rounded-full"
      >{{ delta }}</span>
    </div>
    <slot />
  </div>
</template>
