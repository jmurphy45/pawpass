<script setup lang="ts">
defineProps<{
  padded?: boolean;
}>();
</script>

<template>
  <div
    :class="[
      'bg-white border border-border-warm rounded-xl shadow-card',
      padded ? 'p-5' : '',
    ]"
  >
    <slot />
  </div>
</template>
