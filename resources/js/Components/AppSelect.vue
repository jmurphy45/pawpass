<script setup lang="ts">
defineProps<{
  modelValue?: string | number | null;
  label?: string;
  options: { value: string | number; label: string }[];
  placeholder?: string;
  error?: string | null;
  required?: boolean;
  disabled?: boolean;
  id?: string;
}>();

defineEmits<{
  'update:modelValue': [value: string];
}>();
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="block text-sm font-medium text-text-body mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    <select
      :id="id"
      :value="modelValue ?? ''"
      :required="required"
      :disabled="disabled"
      @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      :class="[
        'w-full rounded-lg border px-3 py-2.5 text-sm bg-white text-text-body outline-none transition',
        'focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500',
        error ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' : 'border-border-warm',
        disabled ? 'opacity-60 cursor-not-allowed bg-surface-subtle' : '',
      ]"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
  </div>
</template>
