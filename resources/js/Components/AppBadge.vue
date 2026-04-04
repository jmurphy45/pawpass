<script setup lang="ts">
defineProps<{
  color?: 'green' | 'yellow' | 'red' | 'blue' | 'gray' | 'amber';
}>();
</script>

<template>
  <span
    :class="[
      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
      color === 'green'  ? 'bg-green-100 text-green-700' :
      color === 'yellow' ? 'bg-yellow-100 text-yellow-700' :
      color === 'red'    ? 'bg-red-100 text-red-600' :
      color === 'blue'   ? 'bg-blue-100 text-blue-700' :
      color === 'amber'  ? 'bg-amber-100 text-amber-700' :
                           'bg-gray-100 text-gray-600',
    ]"
  >
    <slot />
  </span>
</template>
