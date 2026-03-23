<template>
  <PortalLayout>
    <div class="space-y-6 max-w-2xl">
      <div class="flex items-center gap-3">
        <Link :href="route('portal.boarding.index')" class="text-text-muted hover:text-text-body text-sm">← Back</Link>
        <h1 class="text-2xl font-bold text-text-body">Book a Boarding Stay</h1>
      </div>

      <!-- Step 1: Dates -->
      <div class="card p-5 space-y-4">
        <h2 class="font-semibold text-text-body">Stay Dates</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Check-in date</label>
            <input v-model="localDates.starts_at" type="date" class="input w-full" :min="today" @change="onDatesChange" />
          </div>
          <div>
            <label class="label">Check-out date</label>
            <input v-model="localDates.ends_at" type="date" class="input w-full" :min="localDates.starts_at || today" @change="onDatesChange" />
          </div>
        </div>
      </div>

      <!-- Step 2: Available units (shown once dates are loaded server-side) -->
      <div v-if="localDates.starts_at && localDates.ends_at" class="card p-5 space-y-3">
        <h2 class="font-semibold text-text-body">Select a Unit <span class="text-text-muted text-sm font-normal">(optional)</span></h2>
        <p v-if="availableUnits.length === 0" class="text-sm text-text-muted">No units available for the selected dates.</p>
        <div v-else class="space-y-2">
          <label
            v-for="unit in availableUnits"
            :key="unit.id"
            class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
            :class="form.kennel_unit_id === unit.id ? 'border-primary bg-primary/5' : 'border-border hover:bg-surface-subtle'"
          >
            <input type="radio" :value="unit.id" v-model="form.kennel_unit_id" class="mt-0.5" />
            <div class="flex-1">
              <p class="font-medium text-text-body text-sm">{{ unit.name }}</p>
              <p class="text-text-muted text-xs capitalize">{{ unit.type }}<span v-if="unit.description"> · {{ unit.description }}</span></p>
            </div>
            <p v-if="unit.nightly_rate_cents" class="text-sm font-semibold text-text-body whitespace-nowrap">
              ${{ (unit.nightly_rate_cents / 100).toFixed(2) }}/night
            </p>
          </label>
        </div>
      </div>

      <!-- Step 3: Dog -->
      <div class="card p-5 space-y-3">
        <h2 class="font-semibold text-text-body">Which dog?</h2>
        <div v-if="dogs.length === 0" class="text-sm text-text-muted">
          No dogs on your account yet. <Link :href="route('portal.dogs.create')" class="text-primary hover:underline">Add a dog first.</Link>
        </div>
        <select v-else v-model="form.dog_id" class="input w-full">
          <option value="">Select a dog</option>
          <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
        </select>
      </div>

      <!-- Step 4: Care instructions -->
      <div class="card p-5 space-y-4">
        <h2 class="font-semibold text-text-body">Care Instructions <span class="text-text-muted text-sm font-normal">(optional)</span></h2>
        <div>
          <label class="label">Feeding schedule</label>
          <textarea v-model="form.feeding_schedule" class="input w-full h-20 resize-none" placeholder="e.g. 1 cup morning, 1 cup evening" />
        </div>
        <div>
          <label class="label">Medication notes</label>
          <textarea v-model="form.medication_notes" class="input w-full h-20 resize-none" placeholder="Any medications or supplements" />
        </div>
        <div>
          <label class="label">Behavioral notes</label>
          <textarea v-model="form.behavioral_notes" class="input w-full h-20 resize-none" placeholder="e.g. Anxious around other large dogs" />
        </div>
        <div>
          <label class="label">Emergency contact</label>
          <input v-model="form.emergency_contact" type="text" class="input w-full" placeholder="Name and phone number" />
        </div>
        <div>
          <label class="label">Additional notes</label>
          <textarea v-model="form.notes" class="input w-full h-20 resize-none" placeholder="Anything else we should know?" />
        </div>
      </div>

      <!-- Errors -->
      <div v-if="form.errors.kennel_unit_id || form.errors.dog_id" class="rounded-lg p-3 text-sm bg-red-50 text-red-700 border border-red-200">
        {{ form.errors.kennel_unit_id || form.errors.dog_id }}
      </div>

      <div class="flex gap-3">
        <button
          @click="submit"
          :disabled="form.processing || !form.dog_id || !localDates.starts_at || !localDates.ends_at"
          class="btn-primary"
        >
          {{ form.processing ? 'Requesting…' : 'Request Reservation' }}
        </button>
        <Link :href="route('portal.boarding.index')" class="btn-secondary">Cancel</Link>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps<{
  dogs: Array<{ id: string; name: string }>;
  availableUnits: Array<{
    id: string;
    name: string;
    type: string;
    description: string | null;
    nightly_rate_cents: number | null;
  }>;
  selectedDates: { starts_at: string; ends_at: string };
}>();

const today = new Date().toISOString().split('T')[0];

const localDates = reactive({
  starts_at: props.selectedDates.starts_at,
  ends_at: props.selectedDates.ends_at,
});

const form = useForm({
  dog_id: '',
  kennel_unit_id: '',
  starts_at: props.selectedDates.starts_at,
  ends_at: props.selectedDates.ends_at,
  notes: '',
  feeding_schedule: '',
  medication_notes: '',
  behavioral_notes: '',
  emergency_contact: '',
});

function onDatesChange() {
  if (!localDates.starts_at || !localDates.ends_at) return;
  if (localDates.ends_at <= localDates.starts_at) return;
  form.kennel_unit_id = '';
  form.starts_at = localDates.starts_at;
  form.ends_at = localDates.ends_at;
  router.get(
    route('portal.boarding.create'),
    { starts_at: localDates.starts_at, ends_at: localDates.ends_at },
    { preserveState: true, preserveScroll: true },
  );
}

function submit() {
  form.starts_at = localDates.starts_at;
  form.ends_at = localDates.ends_at;
  form.post(route('portal.boarding.store'));
}
</script>
