<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Vaccination Requirements</h1>
          <p class="mt-1 text-sm text-gray-500">These vaccines are checked for compliance on boarding reservations.</p>
        </div>
      </div>

      <!-- Current requirements -->
      <AppCard class="divide-y divide-gray-100">
        <div v-if="requirements.length === 0" class="px-5 py-6 text-sm text-gray-500 text-center">
          No vaccination requirements configured. Dogs will not be checked for compliance.
        </div>
        <div
          v-for="req in requirements"
          :key="req.id"
          class="px-5 py-3 flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <svg class="h-4 w-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
            </svg>
            <span class="text-sm font-medium text-gray-900">{{ req.vaccine_name }}</span>
          </div>
          <button
            type="button"
            class="text-xs text-red-500 hover:text-red-700"
            @click="deleteRequirement(req.id)"
          >Remove</button>
        </div>
      </AppCard>

      <!-- Add requirement form -->
      <form class="bg-white rounded-xl border border-border-warm p-4" @submit.prevent="submitRequirement">
        <p class="text-sm font-medium text-gray-700 mb-3">Add Requirement</p>
        <div class="flex items-start gap-3">
          <div class="flex-1">
            <input
              v-model="form.vaccine_name"
              type="text"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              placeholder="e.g. Rabies, Bordetella, DHPP"
              required
            />
            <p v-if="errors.vaccine_name" class="text-xs text-red-600 mt-1">{{ errors.vaccine_name }}</p>
          </div>
          <AppButton type="submit" variant="primary" class="shrink-0" :disabled="submitting">
            {{ submitting ? 'Adding…' : 'Add' }}
          </AppButton>
        </div>
      </form>
    </div>
  </AdminLayout>
  <AppModal :open="confirmModal.open" :title="confirmModal.title" :message="confirmModal.message" @confirm="handleConfirm" @cancel="handleCancel" />
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';

defineProps<{
  requirements: Array<{ id: string; vaccine_name: string }>;
}>();

const form = ref({ vaccine_name: '' });
const submitting = ref(false);
const errors = ref<Record<string, string>>({});

function submitRequirement() {
  submitting.value = true;
  errors.value = {};
  router.post(
    route('admin.vaccination-requirements.store'),
    { ...form.value },
    {
      onSuccess: () => { form.value = { vaccine_name: '' }; },
      onError: (e) => { errors.value = e; },
      onFinish: () => { submitting.value = false; },
    },
  );
}

const confirmModal = ref<{ open: boolean; title: string; message: string; onConfirm: (() => void) | null }>
  ({ open: false, title: '', message: '', onConfirm: null });

function askConfirm(title: string, message: string, onConfirm: () => void) {
  confirmModal.value = { open: true, title, message, onConfirm };
}
function handleConfirm() { confirmModal.value.onConfirm?.(); confirmModal.value.open = false; }
function handleCancel() { confirmModal.value.open = false; }

function deleteRequirement(id: string) {
  askConfirm(
    'Remove Requirement',
    'This vaccination requirement will be removed.',
    () => router.delete(route('admin.vaccination-requirements.destroy', { vaccinationRequirement: id })),
  );
}
</script>
