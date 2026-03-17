<template>
  <PortalLayout>
    <div class="max-w-lg">
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('portal.dogs.show', { dog: dog.id })" class="text-gray-400 hover:text-gray-600">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
          </svg>
        </Link>
        <h1 class="text-2xl font-bold text-gray-900">Edit {{ dog.name }}</h1>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dog's Name <span class="text-red-500">*</span></label>
            <input
              v-model="form.name"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-500': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Breed</label>
            <input
              v-model="form.breed"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fur Color</label>
            <div class="flex items-center gap-3">
              <input
                v-model="form.color"
                type="color"
                class="h-10 w-16 rounded-lg border border-gray-300 cursor-pointer p-1"
              />
              <span class="text-sm text-gray-500">{{ form.color || 'Not set' }}</span>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
            <input
              v-model="form.dob"
              type="date"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div class="flex gap-3 pt-2">
            <Link
              :href="route('portal.dogs.show', { dog: dog.id })"
              class="flex-1 text-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >Cancel</Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
            >
              {{ form.processing ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

interface DogEditProps {
  id: string;
  name: string;
  breed: string | null;
  color: string | null;
  dob: string | null;
}

const props = defineProps<{ dog: DogEditProps }>();

const form = useForm({
  name: props.dog.name,
  breed: props.dog.breed ?? '',
  color: props.dog.color ?? '',
  dob: props.dog.dob ?? '',
});

function submit() {
  form.patch(route('portal.dogs.update', { dog: props.dog.id }));
}
</script>
