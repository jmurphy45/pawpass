<template>
  <AdminLayout>
    <div class="max-w-lg">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Add Package</h1>
      <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
          <select v-model="form.type" @change="onTypeChange" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
            <option value="one_time">One Time</option>
            <option value="unlimited">Unlimited</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Number of Dogs *</label>
          <input v-model.number="form.dog_limit" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Price ($) *</label>
          <input v-model.number="form.price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div v-if="form.type === 'one_time'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Credits *</label>
          <input v-model.number="form.credit_count" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
          <p class="text-xs text-gray-500 mt-1">{{ form.credit_count * form.dog_limit }} total credits</p>
        </div>
        <div v-if="form.type === 'unlimited'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) *</label>
          <input v-model.number="form.duration_days" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60">
          Create Package
        </button>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', description: '', type: 'one_time', price: 0, credit_count: 1, dog_limit: 1, duration_days: null as number | null, is_active: true });

function onTypeChange() {
  if (form.type === 'unlimited') {
    form.credit_count = 0;
  } else {
    form.duration_days = null;
  }
}

function submit() {
  form.post(route('admin.packages.store'));
}
</script>
