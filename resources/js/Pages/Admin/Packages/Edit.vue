<template>
  <AdminLayout>
    <div class="max-w-lg">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Package</h1>
      <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Number of Dogs *</label>
          <input v-model.number="form.dog_limit" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Price ($) *</label>
          <input v-model.number="form.price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div v-if="props.package.type !== 'unlimited'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Credits *</label>
          <input v-model.number="form.credit_count" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div v-if="props.package.type === 'unlimited'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) *</label>
          <input v-model.number="form.duration_days" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <!-- Recurring billing toggle -->
        <div class="flex items-center gap-3">
          <input
            id="is_recurring_enabled"
            v-model="form.is_recurring_enabled"
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300"
          />
          <label for="is_recurring_enabled" class="text-sm font-medium text-gray-700">Allow recurring billing</label>
        </div>

        <!-- Billing interval — only for one_time packages -->
        <div v-if="form.is_recurring_enabled && props.package.type === 'one_time'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Billing interval (days) *</label>
          <input v-model.number="form.recurring_interval_days" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>

        <p v-if="form.is_recurring_enabled && props.package.type === 'unlimited'" class="text-xs text-gray-500">
          Billing interval will use the package duration ({{ props.package.duration_days ?? 30 }} days).
        </p>

        <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60">
          Save Changes
        </button>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
  package: { id: string; name: string; description: string | null; type: string; price: number; credit_count: number; dog_limit: number; duration_days: number | null; is_active: boolean; is_recurring_enabled: boolean; recurring_interval_days: number | null };
}>();

const form = useForm({
  name: props.package.name,
  description: props.package.description ?? '',
  price: props.package.price,
  credit_count: props.package.credit_count,
  dog_limit: props.package.dog_limit,
  duration_days: props.package.duration_days,
  is_active: props.package.is_active,
  is_recurring_enabled: props.package.is_recurring_enabled,
  recurring_interval_days: props.package.recurring_interval_days,
});

function submit() {
  form.patch(route('admin.packages.update', { package: props.package.id }));
}
</script>
