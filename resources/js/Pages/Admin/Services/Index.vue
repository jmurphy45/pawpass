<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-text-body">Add-on Services</h1>
        <button v-if="isOwner" type="button" @click="openCreate" class="btn-primary">Add Service</button>
      </div>

      <!-- Create / Edit form -->
      <div v-if="showForm" class="card p-5 space-y-4">
        <h2 class="text-base font-semibold text-text-body">{{ editingId ? 'Edit Service' : 'New Service' }}</h2>
        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Name</label>
              <input v-model="form.name" type="text" class="input w-full" placeholder="e.g. Nail Trim" required />
              <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Price ($)</label>
              <input v-model="priceDollars" type="number" min="0" step="0.01" class="input w-full" placeholder="0.00" required />
              <p v-if="form.errors.price_cents" class="mt-1 text-sm text-red-600">{{ form.errors.price_cents }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Context</label>
              <select v-model="form.context" class="input w-full">
                <option value="both">Both (Daycare &amp; Boarding)</option>
                <option value="daycare">Daycare only</option>
                <option value="boarding">Boarding only</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="input w-full" />
            </div>
            <div class="flex items-center gap-3 pt-5">
              <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              <label for="is_active" class="text-sm font-medium text-text-body">Active</label>
            </div>
          </div>
          <div class="flex gap-3">
            <button type="submit" :disabled="form.processing" class="btn-primary">
              {{ editingId ? 'Save Changes' : 'Create Service' }}
            </button>
            <button type="button" @click="cancelForm" class="btn-secondary">Cancel</button>
          </div>
        </form>
      </div>

      <!-- Services list -->
      <div class="card overflow-hidden">
        <div v-if="addonTypes.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No services configured yet.
          <span v-if="isOwner"> Click <strong>Add Service</strong> to create your first add-on.</span>
        </div>
        <ul v-else>
          <li v-for="addon in addonTypes" :key="addon.id" class="list-row gap-3">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-text-body truncate">{{ addon.name }}</p>
              <p class="text-xs text-text-muted">${{ (addon.price_cents / 100).toFixed(2) }} · sort {{ addon.sort_order }}</p>
            </div>
            <span class="badge shrink-0" :class="contextBadge(addon.context)">{{ contextLabel(addon.context) }}</span>
            <span class="badge shrink-0" :class="addon.is_active ? 'badge-green' : 'badge-gray'">
              {{ addon.is_active ? 'Active' : 'Inactive' }}
            </span>
            <div v-if="isOwner" class="flex items-center gap-2 shrink-0">
              <button @click="openEdit(addon)" class="text-sm text-indigo-600 hover:underline">Edit</button>
              <button @click="confirmDelete(addon)" class="text-sm text-red-500 hover:underline">Delete</button>
            </div>
          </li>
        </ul>
      </div>

      <!-- Delete confirmation -->
      <div v-if="deletingAddon" class="card p-5 border-red-200 bg-red-50">
        <p class="text-sm text-text-body mb-3">
          Delete <strong>{{ deletingAddon.name }}</strong>? This cannot be undone.
        </p>
        <div class="flex gap-2">
          <button @click="submitDelete" class="btn-danger text-sm">Delete</button>
          <button @click="deletingAddon = null" class="btn-secondary text-sm">Cancel</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface AddonType {
  id: string;
  name: string;
  price_cents: number;
  is_active: boolean;
  sort_order: number;
  context: 'boarding' | 'daycare' | 'both';
}

const props = defineProps<{
  addonTypes: AddonType[];
}>();

const page = usePage();
const isOwner = computed(() => (page.props.auth as any)?.user?.role === 'business_owner');

// ---- Form state ----
const showForm = ref(false);
const editingId = ref<string | null>(null);
const deletingAddon = ref<AddonType | null>(null);

const form = useForm({
  name:        '',
  price_cents: 0,
  context:     'both' as 'both' | 'boarding' | 'daycare',
  sort_order:  0,
  is_active:   true,
});

const priceDollars = computed({
  get: () => (form.price_cents / 100).toFixed(2),
  set: (v: string) => { form.price_cents = Math.round(parseFloat(v || '0') * 100); },
});

function openCreate() {
  editingId.value = null;
  form.reset();
  form.context   = 'both';
  form.is_active = true;
  showForm.value = true;
}

function openEdit(addon: AddonType) {
  editingId.value    = addon.id;
  form.name          = addon.name;
  form.price_cents   = addon.price_cents;
  form.context       = addon.context;
  form.sort_order    = addon.sort_order;
  form.is_active     = addon.is_active;
  showForm.value     = true;
  deletingAddon.value = null;
}

function cancelForm() {
  showForm.value  = false;
  editingId.value = null;
  form.reset();
}

function submitForm() {
  if (editingId.value) {
    form.patch(route('admin.services.update', editingId.value), {
      onSuccess: () => cancelForm(),
    });
  } else {
    form.post(route('admin.services.store'), {
      onSuccess: () => cancelForm(),
    });
  }
}

function confirmDelete(addon: AddonType) {
  deletingAddon.value = addon;
  showForm.value = false;
}

function submitDelete() {
  if (!deletingAddon.value) return;
  form.delete(route('admin.services.destroy', deletingAddon.value.id), {
    onSuccess: () => { deletingAddon.value = null; },
  });
}

// ---- Display helpers ----
function contextLabel(context: string) {
  return context === 'both' ? 'Both' : context === 'boarding' ? 'Boarding' : 'Daycare';
}

function contextBadge(context: string) {
  return {
    'badge-purple': context === 'both',
    'badge-blue':   context === 'boarding',
    'badge-green':  context === 'daycare',
  };
}
</script>
