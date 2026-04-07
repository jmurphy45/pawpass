<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Kennel Units</h1>
        <AppButton v-if="isOwner" variant="primary" type="button" @click="openCreate">Add Unit</AppButton>
      </div>

      <!-- Add / Edit form -->
      <AppCard v-if="showForm" :padded="true">
        <h2 class="text-base font-semibold text-gray-900 mb-4">{{ editingId ? 'Edit Unit' : 'New Unit' }}</h2>
        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input v-model="form.name" type="text" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" placeholder="e.g. Suite 1" />
              <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
              <select v-model="form.type" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                <option value="standard">Standard</option>
                <option value="suite">Suite</option>
                <option value="large">Large</option>
                <option value="run">Run</option>
              </select>
              <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Capacity (dogs)</label>
              <input v-model.number="form.capacity" type="number" min="1" max="100" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" />
              <p v-if="form.errors.capacity" class="mt-1 text-sm text-red-600">{{ form.errors.capacity }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nightly Rate ($)</label>
              <input v-model="nightlyRateDollars" type="number" min="0" step="0.01" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" placeholder="0.00" />
              <p v-if="form.errors.nightly_rate_cents" class="mt-1 text-sm text-red-600">{{ form.errors.nightly_rate_cents }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" />
            </div>
            <div class="flex items-center gap-3 pt-6">
              <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" placeholder="Optional notes about this unit" />
          </div>
          <div class="flex gap-3">
            <AppButton type="submit" variant="primary" :disabled="form.processing">
              {{ editingId ? 'Save Changes' : 'Create Unit' }}
            </AppButton>
            <AppButton type="button" variant="secondary" @click="cancelForm">Cancel</AppButton>
          </div>
        </form>
      </AppCard>

      <!-- Units list -->
      <AppCard class="overflow-hidden">
        <div v-if="units.length === 0" class="px-6 py-12 text-center text-sm text-gray-500">
          No kennel units yet. Add your first unit to get started.
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="unit in units" :key="unit.id" class="flex items-center gap-4 px-6 py-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-medium text-gray-900">{{ unit.name }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize" :class="typeClass(unit.type)">{{ unit.type }}</span>
                <span v-if="!unit.is_active" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">Inactive</span>
              </div>
              <p class="text-xs text-gray-500 mt-0.5">
                Capacity: {{ unit.capacity }} {{ unit.capacity === 1 ? 'dog' : 'dogs' }}
                <template v-if="unit.nightly_rate_cents != null">
                  &nbsp;·&nbsp; ${{ (unit.nightly_rate_cents / 100).toFixed(2) }}/night
                </template>
                <template v-if="unit.description">
                  &nbsp;·&nbsp; {{ unit.description }}
                </template>
              </p>
            </div>
            <div v-if="isOwner" class="flex items-center gap-3 shrink-0">
              <button type="button" @click="openEdit(unit)" class="text-sm text-indigo-600 hover:underline">Edit</button>
              <button type="button" @click="deleteUnit(unit.id)" class="text-sm text-red-500 hover:underline">Delete</button>
            </div>
          </li>
        </ul>
      </AppCard>
    </div>
  </AdminLayout>
  <AppModal :open="confirmModal.open" :title="confirmModal.title" :message="confirmModal.message" @confirm="handleConfirm" @cancel="handleCancel" />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface KennelUnit {
  id: string;
  name: string;
  type: string;
  capacity: number;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  nightly_rate_cents: number | null;
}

const props = defineProps<{ units: KennelUnit[] }>();

const page = usePage<PageProps>();
const isOwner = computed(() => page.props.auth.user?.role === 'business_owner');

const showForm = ref(false);
const editingId = ref<string | null>(null);

const form = useForm({
  name: '',
  type: 'standard',
  capacity: 1,
  description: '',
  is_active: true,
  sort_order: 0,
  nightly_rate_cents: null as number | null,
});

const nightlyRateDollars = computed({
  get: () => form.nightly_rate_cents != null ? (form.nightly_rate_cents / 100).toFixed(2) : '',
  set: (val: string) => {
    const parsed = parseFloat(val);
    form.nightly_rate_cents = isNaN(parsed) || val === '' ? null : Math.round(parsed * 100);
  },
});

function openCreate() {
  editingId.value = null;
  form.reset();
  form.defaults({ name: '', type: 'standard', capacity: 1, description: '', is_active: true, sort_order: 0, nightly_rate_cents: null });
  showForm.value = true;
}

function openEdit(unit: KennelUnit) {
  editingId.value = unit.id;
  form.name = unit.name;
  form.type = unit.type;
  form.capacity = unit.capacity;
  form.description = unit.description ?? '';
  form.is_active = unit.is_active;
  form.sort_order = unit.sort_order;
  form.nightly_rate_cents = unit.nightly_rate_cents;
  form.clearErrors();
  showForm.value = true;
}

function cancelForm() {
  showForm.value = false;
  editingId.value = null;
  form.reset();
}

function submitForm() {
  if (editingId.value) {
    form.patch(route('admin.boarding.units.update', { kennelUnit: editingId.value }), {
      onSuccess: () => cancelForm(),
    });
  } else {
    form.post(route('admin.boarding.units.store'), {
      onSuccess: () => cancelForm(),
    });
  }
}

const confirmModal = ref<{ open: boolean; title: string; message: string; onConfirm: (() => void) | null }>
  ({ open: false, title: '', message: '', onConfirm: null });

function askConfirm(title: string, message: string, onConfirm: () => void) {
  confirmModal.value = { open: true, title, message, onConfirm };
}
function handleConfirm() { confirmModal.value.onConfirm?.(); confirmModal.value.open = false; }
function handleCancel() { confirmModal.value.open = false; }

function deleteUnit(id: string) {
  askConfirm(
    'Delete Kennel Unit',
    'This kennel unit will be permanently deleted. This cannot be undone.',
    () => router.delete(route('admin.boarding.units.destroy', { kennelUnit: id })),
  );
}

function typeClass(type: string): string {
  const map: Record<string, string> = {
    standard: 'bg-blue-50 text-blue-700',
    suite:    'bg-purple-50 text-purple-700',
    large:    'bg-amber-50 text-amber-700',
    run:      'bg-green-50 text-green-700',
  };
  return map[type] ?? 'bg-gray-100 text-gray-600';
}
</script>
