<template>
  <AdminLayout>
    <div class="max-w-lg">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit {{ dog.name }}</h1>
      <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Breed</label>
          <input v-model="form.breed" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
        </div>
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

const props = defineProps<{ dog: { id: string; name: string; breed: string | null; dob: string | null; sex: string | null; vet_name: string | null; vet_phone: string | null } }>();

const form = useForm({
  name: props.dog.name,
  breed: props.dog.breed ?? '',
  dob: props.dog.dob ?? '',
  sex: props.dog.sex ?? '',
  vet_name: props.dog.vet_name ?? '',
  vet_phone: props.dog.vet_phone ?? '',
});

function submit() {
  form.patch(route('admin.dogs.update', { dog: props.dog.id }));
}
</script>
