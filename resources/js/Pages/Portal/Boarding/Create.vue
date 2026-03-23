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
            <input v-model="form.starts_at" type="date" class="input w-full" :min="today" @change="onDatesChange" />
          </div>
          <div>
            <label class="label">Check-out date</label>
            <input v-model="form.ends_at" type="date" class="input w-full" :min="form.starts_at || today" @change="onDatesChange" />
          </div>
        </div>
      </div>

      <!-- Step 2: Dog -->
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

      <!-- Step 3: Unit (shown after dates selected) -->
      <div v-if="form.starts_at && form.ends_at" class="card p-5 space-y-3">
        <h2 class="font-semibold text-text-body">Select a Unit <span class="text-text-muted text-sm font-normal">(optional)</span></h2>
        <p v-if="loadingUnits" class="text-sm text-text-muted">Loading availability…</p>
        <p v-else-if="availableUnits.length === 0" class="text-sm text-text-muted">No units available for the selected dates.</p>
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

      <!-- Error -->
      <div v-if="error" class="rounded-lg p-3 text-sm bg-red-50 text-red-700 border border-red-200">{{ error }}</div>

      <div class="flex gap-3">
        <button @click="submit" :disabled="submitting || !form.dog_id || !form.starts_at || !form.ends_at" class="btn-primary">
          {{ submitting ? 'Requesting…' : 'Request Reservation' }}
        </button>
        <Link :href="route('portal.boarding.index')" class="btn-secondary">Cancel</Link>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import axios from 'axios';

const props = defineProps<{
  dogs: Array<{ id: string; name: string }>;
}>();

const today = new Date().toISOString().split('T')[0];

const form = reactive({
  dog_id: '',
  kennel_unit_id: '',
  starts_at: '',
  ends_at: '',
  notes: '',
  feeding_schedule: '',
  medication_notes: '',
  behavioral_notes: '',
  emergency_contact: '',
});

const availableUnits = ref<Array<{ id: string; name: string; type: string; description: string | null; nightly_rate_cents: number | null }>>([]);
const loadingUnits = ref(false);
const submitting = ref(false);
const error = ref('');

async function onDatesChange() {
  if (!form.starts_at || !form.ends_at) return;
  if (form.ends_at <= form.starts_at) return;
  loadingUnits.value = true;
  form.kennel_unit_id = '';
  try {
    const { data } = await axios.get('/api/portal/v1/kennel-units/available', {
      params: { starts_at: form.starts_at, ends_at: form.ends_at },
    });
    availableUnits.value = data.data;
  } catch {
    availableUnits.value = [];
  } finally {
    loadingUnits.value = false;
  }
}

async function submit() {
  error.value = '';
  submitting.value = true;
  try {
    const { data } = await axios.post('/api/portal/v1/reservations', {
      dog_id: form.dog_id,
      kennel_unit_id: form.kennel_unit_id || undefined,
      starts_at: form.starts_at,
      ends_at: form.ends_at,
      notes: form.notes || undefined,
      feeding_schedule: form.feeding_schedule || undefined,
      medication_notes: form.medication_notes || undefined,
      behavioral_notes: form.behavioral_notes || undefined,
      emergency_contact: form.emergency_contact || undefined,
    });
    router.visit(route('portal.boarding.show', data.data.id));
  } catch (err: any) {
    const msg = err.response?.data?.error;
    if (msg === 'UNIT_NOT_AVAILABLE') {
      error.value = 'That unit is no longer available for those dates. Please choose another.';
    } else if (msg === 'DOG_VACCINATION_INCOMPLETE') {
      const violations = err.response?.data?.violations ?? [];
      error.value = `Missing vaccinations: ${violations.join(', ')}. Please update your dog's records before booking.`;
    } else {
      error.value = 'Something went wrong. Please try again.';
    }
  } finally {
    submitting.value = false;
  }
}
</script>
