<template>
  <AdminLayout>
    <div class="max-w-lg">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Add Dog</h1>
      <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Customer *</label>
          <select v-model="form.customer_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
            <option value="">Select customer…</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Breed</label>
          <input v-model="form.breed" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60">
          Create Dog
        </button>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineProps<{ customers: Array<{ id: string; name: string }> }>();

const form = useForm({ customer_id: '', name: '', breed: '', dob: '', sex: '', vet_name: '', vet_phone: '' });

function submit() {
  form.post(route('admin.dogs.store'));
}
</script>
