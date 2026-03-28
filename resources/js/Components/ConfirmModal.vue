<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';

const props = withDefaults(defineProps<{
  open: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  danger?: boolean;
}>(), {
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  danger: true,
});

const emit = defineEmits<{
  confirm: [];
  cancel: [];
}>();

const confirmBtn = ref<HTMLButtonElement | null>(null);

watch(() => props.open, (val) => {
  if (val) nextTick(() => confirmBtn.value?.focus());
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="emit('cancel')"
      @keydown.esc.window="emit('cancel')"
    >
      <div
        class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6 space-y-4"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="'confirm-modal-title'"
      >
        <div class="flex items-center justify-between">
          <h3 id="confirm-modal-title" class="text-lg font-semibold text-gray-900">{{ title }}</h3>
          <button @click="emit('cancel')" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <p class="text-sm text-gray-600">{{ message }}</p>

        <div class="flex gap-3 justify-end">
          <button
            @click="emit('cancel')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >{{ cancelLabel }}</button>
          <button
            ref="confirmBtn"
            @click="emit('confirm')"
            :class="danger
              ? 'bg-red-600 hover:bg-red-700'
              : 'bg-indigo-600 hover:bg-indigo-700'"
            class="px-4 py-2 text-sm font-medium text-white rounded-lg"
          >{{ confirmLabel }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
