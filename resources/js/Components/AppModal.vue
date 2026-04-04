<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue';

const props = withDefaults(defineProps<{
  open: boolean;
  title: string;
  message?: string;
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
  <TransitionRoot as="template" :show="open">
    <Dialog class="relative z-50" @close="emit('cancel')">
      <TransitionChild
        as="template"
        enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
        leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black/50 transition-opacity" />
      </TransitionChild>

      <div class="fixed inset-0 z-10 flex items-center justify-center p-4">
        <TransitionChild
          as="template"
          enter="ease-out duration-200" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100"
          leave="ease-in duration-150" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95"
        >
          <DialogPanel class="w-full max-w-md bg-white rounded-xl shadow-xl p-6 space-y-4">
            <div class="flex items-center justify-between">
              <DialogTitle class="text-lg font-semibold text-gray-900">{{ title }}</DialogTitle>
              <button @click="emit('cancel')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <p v-if="message" class="text-sm text-gray-600">{{ message }}</p>
            <slot />

            <div class="flex gap-3 justify-end pt-1">
              <button
                @click="emit('cancel')"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              >{{ cancelLabel }}</button>
              <button
                ref="confirmBtn"
                @click="emit('confirm')"
                :class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors"
              >{{ confirmLabel }}</button>
            </div>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
