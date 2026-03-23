<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <h1 class="text-2xl font-bold text-text-body">Occupancy</h1>
        <a :href="route('admin.boarding.reservations')" class="btn-secondary text-sm self-start sm:self-auto">List View</a>
      </div>

      <!-- Date range selector -->
      <div class="card p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs text-text-muted mb-1">From</label>
          <input v-model="rangeForm.from" type="date" class="input text-sm py-1.5" />
        </div>
        <div>
          <label class="block text-xs text-text-muted mb-1">To</label>
          <input v-model="rangeForm.to" type="date" class="input text-sm py-1.5" />
        </div>
        <button @click="applyRange" class="btn-primary text-sm py-1.5 px-4">Apply</button>
      </div>

      <!-- Grid -->
      <div class="card overflow-x-auto">
        <div v-if="units.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No active kennel units.
        </div>
        <table v-else class="text-xs min-w-full">
          <thead class="bg-surface-subtle border-b border-border">
            <tr>
              <th class="px-3 py-2 text-left font-medium text-text-muted sticky left-0 bg-surface-subtle z-10 min-w-[120px]">Unit</th>
              <th v-for="day in days" :key="day" class="px-2 py-2 font-medium text-text-muted text-center min-w-[80px]">
                {{ formatDay(day) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="unit in units" :key="unit.id" class="border-b border-border last:border-0">
              <td class="px-3 py-2 font-medium text-text-body sticky left-0 bg-white z-10">
                {{ unit.name }}
              </td>
              <td v-for="day in days" :key="day" class="px-2 py-2 text-center">
                <span v-if="occupiedBy(unit, day)" class="inline-block bg-primary/10 text-primary rounded px-1.5 py-0.5 truncate max-w-[72px]">
                  {{ occupiedBy(unit, day) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Reservation {
  id: string;
  dog_name: string | null;
  starts_at: string;
  ends_at: string;
}
interface Unit {
  id: string;
  name: string;
  reservations: Reservation[];
}

const props = defineProps<{
  units: Unit[];
  from: string;
  to: string;
}>();

const rangeForm = reactive({ from: props.from, to: props.to });

function applyRange() {
  router.get(route('admin.boarding.occupancy'), { from: rangeForm.from, to: rangeForm.to }, {
    preserveState: true, replace: true,
  });
}

const days = computed(() => {
  const result: string[] = [];
  const start = new Date(props.from);
  const end   = new Date(props.to);
  for (const d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    result.push(d.toISOString().slice(0, 10));
  }
  return result;
});

function formatDay(iso: string) {
  const d = new Date(iso);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function occupiedBy(unit: Unit, day: string): string | null {
  const res = unit.reservations.find(r => r.starts_at.slice(0, 10) <= day && r.ends_at.slice(0, 10) > day);
  return res?.dog_name ?? null;
}
</script>
