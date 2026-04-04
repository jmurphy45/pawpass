<template>
  <PortalLayout>
    <div class="max-w-lg">
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('portal.dogs.index')" class="text-text-muted hover:text-text-body transition-colors">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
          </svg>
        </Link>
        <h1 class="text-2xl font-bold text-text-body">Add a Dog</h1>
      </div>

      <AppCard :padded="true">
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Dog's Name <span class="text-red-500">*</span></label>
            <input
              v-model="form.name"
              type="text"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              :class="{ 'border-red-500': form.errors.name }"
              placeholder="e.g. Buddy"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Breed</label>
            <input
              v-model="form.breed"
              type="text"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
              placeholder="e.g. Golden Retriever"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Fur Color</label>
            <div class="flex items-center gap-3">
              <input
                v-model="form.color"
                type="color"
                class="h-10 w-16 rounded-lg border border-border-warm cursor-pointer p-1 bg-white"
              />
              <span class="text-sm text-text-muted">{{ form.color || 'Not set' }}</span>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-body mb-1.5">Date of Birth</label>
            <input
              v-model="form.dob"
              type="date"
              class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
            />
          </div>

          <div class="flex gap-3 pt-2">
            <Link :href="route('portal.dogs.index')" class="flex-1">
              <AppButton variant="secondary" class="w-full justify-center">Cancel</AppButton>
            </Link>
            <AppButton
              type="submit"
              variant="primary"
              :disabled="form.processing"
              class="flex-1 justify-center"
            >
              {{ form.processing ? 'Saving…' : 'Add Dog' }}
            </AppButton>
          </div>
        </form>
      </AppCard>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const form = useForm({
  name: '',
  breed: '',
  color: '',
  dob: '',
});

function submit() {
  form.post(route('portal.dogs.store'));
}
</script>
