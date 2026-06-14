<template>
  <PortalLayout>
    <div class="max-w-lg mx-auto space-y-6">

      <!-- Header -->
      <div class="text-center space-y-1">
        <div class="text-4xl mb-2">🚗</div>
        <h1 class="text-2xl font-bold text-text-body">You've arrived!</h1>
        <p class="text-text-muted text-sm">Parking Spot <span class="font-semibold text-text-body">{{ spot.spot_number }}</span> — {{ spot.name }}</p>
      </div>

      <!-- Success state -->
      <AppCard v-if="announced" class="text-center space-y-2 py-6">
        <div class="text-3xl">✅</div>
        <p class="font-semibold text-text-body">We've been notified!</p>
        <p class="text-text-muted text-sm">We'll be right out to get {{ announcedDogName }}.</p>
      </AppCard>

      <!-- No reservations -->
      <AppCard v-else-if="reservations.length === 0" class="text-center space-y-2 py-6">
        <p class="font-semibold text-text-body">No reservations found for today</p>
        <p class="text-text-muted text-sm">Please head inside or give us a call.</p>
      </AppCard>

      <!-- Reservation picker -->
      <template v-else>
        <AppCard class="space-y-4">
          <p class="text-sm text-text-muted">Select your boarding reservation:</p>

          <div class="space-y-2">
            <label
              v-for="r in reservations"
              :key="r.id"
              class="flex items-center gap-3 p-3 rounded-lg border border-border cursor-pointer"
              :class="selected === r.id ? 'border-primary bg-primary/5' : 'hover:bg-surface-muted'"
            >
              <input type="radio" :value="r.id" v-model="selected" class="accent-primary" />
              <div>
                <p class="font-medium text-text-body">{{ r.dog?.name ?? 'Dog' }}</p>
                <p class="text-xs text-text-muted">Check-in: {{ formatDate(r.starts_at) }}</p>
              </div>
            </label>
          </div>

          <AppButton
            :disabled="!selected || form.processing"
            @click="submit"
            class="w-full"
          >
            {{ form.processing ? 'Notifying staff…' : "I'm here!" }}
          </AppButton>

          <p v-if="form.errors.parking_spot_id" class="text-sm text-danger">
            {{ form.errors.parking_spot_id }}
          </p>
        </AppCard>
      </template>

    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

interface Spot {
  id: string;
  spot_number: string;
  name: string;
}

interface ReservationItem {
  id: string;
  starts_at: string | null;
  ends_at: string | null;
  dog: { id: string; name: string } | null;
}

interface PageProps {
  spot: Spot;
  reservations: ReservationItem[];
  [key: string]: unknown;
}

const props = defineProps<PageProps>();

const selected = ref<string | null>(props.reservations.length === 1 ? props.reservations[0].id : null);
const announced = ref(false);
const announcedDogName = ref('');

const form = useForm({
  spot_number: props.spot.spot_number,
});

function submit() {
  if (!selected.value) return;
  const reservation = props.reservations.find(r => r.id === selected.value);
  form.post(route('portal.boarding.arrive', { id: selected.value }), {
    onSuccess: () => {
      announced.value = true;
      announcedDogName.value = reservation?.dog?.name ?? 'your dog';
    },
  });
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>
