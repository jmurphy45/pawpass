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

        <div class="border-t border-gray-200 pt-4">
          <h2 class="text-sm font-semibold text-gray-900 mb-3">Auto-Replenish</h2>
          <label class="flex items-center gap-3 cursor-pointer">
            <input v-model="form.auto_replenish_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
            <span class="text-sm text-gray-700">Auto-charge customer when credits run out</span>
          </label>

          <div v-if="form.auto_replenish_enabled" class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Package to charge</label>
            <select v-model="form.auto_replenish_package_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
              <option value="">— select a package —</option>
              <option v-for="pkg in eligiblePackages" :key="pkg.id" :value="pkg.id">
                {{ pkg.name }} — {{ pkg.credit_count }} credits (${{ pkg.price }})
              </option>
            </select>
            <p class="mt-1.5 text-xs text-gray-500">
              Customer will be charged automatically if check-in blocking is disabled and credits reach zero.
            </p>
          </div>
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

interface EligiblePackage {
  id: string;
  name: string;
  price: string;
  credit_count: number;
}

const props = defineProps<{
  dog: {
    id: string;
    name: string;
    breed: string | null;
    dob: string | null;
    sex: string | null;
    vet_name: string | null;
    vet_phone: string | null;
    auto_replenish_enabled: boolean;
    auto_replenish_package_id: string | null;
  };
  eligiblePackages: EligiblePackage[];
}>();

const form = useForm({
  name: props.dog.name,
  breed: props.dog.breed ?? '',
  dob: props.dog.dob ?? '',
  sex: props.dog.sex ?? '',
  vet_name: props.dog.vet_name ?? '',
  vet_phone: props.dog.vet_phone ?? '',
  auto_replenish_enabled: props.dog.auto_replenish_enabled,
  auto_replenish_package_id: props.dog.auto_replenish_package_id ?? '',
});

function submit() {
  form.patch(route('admin.dogs.update', { dog: props.dog.id }));
}
</script>
