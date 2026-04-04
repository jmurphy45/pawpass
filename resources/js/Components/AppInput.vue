<script setup lang="ts">
defineProps<{
  modelValue?: string | number | null;
  label?: string;
  type?: string;
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
    <input
      :id="id"
      :type="type ?? 'text'"
      :value="modelValue ?? ''"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      :class="[
        'w-full rounded-lg border px-3 py-2.5 text-sm bg-white text-text-body outline-none transition',
        'focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500',
        error ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' : 'border-border-warm',
        disabled ? 'opacity-60 cursor-not-allowed bg-surface-subtle' : '',
      ]"
    />
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
  </div>
</template>
