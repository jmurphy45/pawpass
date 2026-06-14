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

      <!-- No reservations or appointments -->
      <AppCard v-else-if="items.length === 0" class="text-center space-y-2 py-6">
        <p class="font-semibold text-text-body">No visits found for today</p>
        <p class="text-text-muted text-sm">Please head inside or give us a call.</p>
      </AppCard>

      <!-- Picker -->
      <template v-else>
        <AppCard class="space-y-4">
          <p class="text-sm text-text-muted">Select your visit:</p>

          <div class="space-y-2">
            <label
              v-for="item in items"
              :key="item.id"
              class="flex items-center gap-3 p-3 rounded-lg border border-border cursor-pointer"
              :class="selected === item.id ? 'border-primary bg-primary/5' : 'hover:bg-surface-muted'"
            >
              <input type="radio" :value="item.id" v-model="selected" class="accent-primary" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="font-medium text-text-body">{{ item.dog?.name ?? 'Dog' }}</p>
                  <span
                    class="text-xs font-medium px-1.5 py-0.5 rounded"
                    :class="item.type === 'daycare' ? 'bg-green-100 text-green-700' : 'bg-indigo-100 text-indigo-700'"
                  >{{ item.type === 'daycare' ? 'Daycare' : 'Boarding' }}</span>
                </div>
                <p class="text-xs text-text-muted">{{ formatDate(item.starts_at) }}</p>
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

          <p v-if="form.errors.spot_number" class="text-sm text-danger">
            {{ form.errors.spot_number }}
          </p>
        </AppCard>
      </template>

    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

interface Spot {
  id: string;
  spot_number: string;
  name: string;
}

interface VisitItem {
  id: string;
  type: 'boarding' | 'daycare';
  starts_at: string | null;
  ends_at: string | null;
  dog: { id: string; name: string } | null;
}

interface ReservationItem {
  id: string;
  starts_at: string | null;
  ends_at: string | null;
  dog: { id: string; name: string } | null;
}

interface AppointmentItem {
  id: string;
  starts_at: string | null;
  ends_at: string | null;
  dog: { id: string; name: string } | null;
}

interface PageProps {
  spot: Spot;
  reservations: ReservationItem[];
  appointments: AppointmentItem[];
  [key: string]: unknown;
}

const props = defineProps<PageProps>();

const items = computed<VisitItem[]>(() => [
  ...props.reservations.map((r) => ({ ...r, type: 'boarding' as const })),
  ...props.appointments.map((a) => ({ ...a, type: 'daycare' as const })),
]);

const selected = ref<string | null>(items.value.length === 1 ? items.value[0].id : null);
const announced = ref(false);
const announcedDogName = ref('');

const form = useForm({
  spot_number: props.spot.spot_number,
});

function submit() {
  if (!selected.value) return;
  const item = items.value.find((i) => i.id === selected.value);
  if (!item) return;

  const routeName = item.type === 'daycare' ? 'portal.daycare.arrive' : 'portal.boarding.arrive';

  form.post(route(routeName, { id: selected.value }), {
    onSuccess: () => {
      announced.value = true;
      announcedDogName.value = item.dog?.name ?? 'your dog';
    },
  });
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>
