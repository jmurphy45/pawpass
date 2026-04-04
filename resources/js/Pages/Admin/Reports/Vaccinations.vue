<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Vaccination Compliance</h1>
        <span class="text-sm text-gray-500">{{ rows.length }} dog{{ rows.length === 1 ? '' : 's' }}</span>
      </div>

      <!-- Filter bar -->
      <div class="flex flex-wrap gap-2">
        <a
          :href="route('admin.reports.vaccinations')"
          :class="filterClass(null)"
        >All</a>
        <a
          :href="route('admin.reports.vaccinations', { filter: 'non_compliant' })"
          :class="filterClass('non_compliant')"
        >Non-Compliant</a>
        <a
          :href="route('admin.reports.vaccinations', { filter: 'expiring_soon' })"
          :class="filterClass('expiring_soon')"
        >Expiring Soon (≤30d)</a>
        <a
          :href="route('admin.reports.vaccinations', { filter: 'expiring_urgent' })"
          :class="filterClass('expiring_urgent')"
        >Expiring Urgent (≤7d)</a>
      </div>

      <!-- Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Dog</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Customer</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Vaccinations</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="rows.length === 0">
                <td colspan="4" class="px-4 py-8 text-center text-gray-400">No dogs match the selected filter.</td>
              </tr>
              <tr v-for="row in rows" :key="row.dog_id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900">{{ row.dog_name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ row.customer_name }}</td>
                <td class="px-4 py-3">
                  <span :class="complianceBadge(row.is_compliant)">
                    {{ row.is_compliant ? 'Compliant' : 'Non-Compliant' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div v-if="row.vaccinations.length === 0" class="text-gray-400 italic text-xs">No requirements set</div>
                  <div v-else class="flex flex-wrap gap-1">
                    <span
                      v-for="vax in row.vaccinations"
                      :key="vax.vaccine_name"
                      :class="vaccinePill(vax)"
                      :title="vaxTitle(vax)"
                    >
                      {{ vax.vaccine_name }}
                      <span v-if="vax.expires_at" class="ml-1 opacity-75 text-xs">
                        {{ vaxExpiryLabel(vax) }}
                      </span>
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface VaccinationEntry {
  vaccine_name: string;
  status: 'valid' | 'missing' | 'expired';
  expires_at: string | null;
  days_remaining: number | null;
}

interface DogRow {
  dog_id: string;
  dog_name: string;
  customer_id: string;
  customer_name: string;
  is_compliant: boolean;
  vaccinations: VaccinationEntry[];
}

const props = defineProps<{
  rows: DogRow[];
  filter: string | null;
}>();

function filterClass(f: string | null): string {
  const active = props.filter === f;
  return [
    'px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
    active
      ? 'bg-indigo-600 text-white border-indigo-600'
      : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400 hover:text-indigo-600',
  ].join(' ');
}

function complianceBadge(compliant: boolean): string {
  return compliant
    ? 'inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800'
    : 'inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800';
}

function vaccinePill(vax: VaccinationEntry): string {
  const base = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium';
  if (vax.status === 'missing') return `${base} bg-gray-100 text-gray-600`;
  if (vax.status === 'expired') return `${base} bg-red-100 text-red-700`;
  if (vax.days_remaining !== null && vax.days_remaining <= 7) return `${base} bg-orange-100 text-orange-700`;
  if (vax.days_remaining !== null && vax.days_remaining <= 30) return `${base} bg-amber-100 text-amber-700`;
  return `${base} bg-green-100 text-green-700`;
}

function vaxTitle(vax: VaccinationEntry): string {
  if (vax.status === 'missing') return `${vax.vaccine_name}: missing`;
  if (vax.status === 'expired') return `${vax.vaccine_name}: expired on ${vax.expires_at}`;
  if (vax.days_remaining !== null) return `${vax.vaccine_name}: expires ${vax.expires_at} (${vax.days_remaining}d)`;
  return vax.vaccine_name;
}

function vaxExpiryLabel(vax: VaccinationEntry): string {
  if (vax.status === 'missing') return '';
  if (vax.status === 'expired') return '(expired)';
  if (vax.days_remaining !== null && vax.days_remaining <= 7) return `(${vax.days_remaining}d)`;
  if (vax.days_remaining !== null && vax.days_remaining <= 30) return `(${vax.days_remaining}d)`;
  return '';
}
</script>
