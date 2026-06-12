<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Parking Spots</h1>
        <button
          v-if="isOwner"
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors"
          @click="showCreateForm = !showCreateForm"
        >
          Add Parking Spot
        </button>
      </div>

      <!-- Create form -->
      <div v-if="showCreateForm && isOwner" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">New Parking Spot</h2>
        <form @submit.prevent="submitCreate" class="space-y-4">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Spot Number <span class="text-red-500">*</span></label>
              <input v-model="createForm.spot_number" type="text" placeholder="e.g. A1, B12" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
              <p v-if="createForm.errors.spot_number" class="mt-1 text-sm text-red-600">{{ createForm.errors.spot_number }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="createForm.name" type="text" placeholder="e.g. Front Row Spot A1" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
              <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
              <input v-model="createForm.location" type="text" placeholder="e.g. Front Lot, Main Entrance" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
              <input v-model="createForm.sort_order" type="number" placeholder="0" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="createForm.description" placeholder="Optional description of the parking spot" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></textarea>
          </div>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2">
              <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
              <span class="text-sm font-medium text-gray-700">Active</span>
            </label>
          </div>
          <div class="flex gap-3">
            <button type="submit" :disabled="createForm.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60 transition-colors">
              Create Parking Spot
            </button>
            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" @click="showCreateForm = false">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- Parking spot cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="spot in parkingSpots"
          :key="spot.id"
          class="bg-white rounded-xl border border-gray-200 p-6"
        >
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="font-semibold text-gray-900">{{ spot.name }}</h3>
              <p class="text-sm text-gray-600">Spot {{ spot.spot_number }}</p>
              <p v-if="spot.location" class="text-sm text-gray-500">{{ spot.location }}</p>
            </div>
            <div class="flex items-center gap-2">
              <span :class="[spot.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800', 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium']">
                {{ spot.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>

          <div v-if="spot.description" class="mb-4">
            <p class="text-sm text-gray-600">{{ spot.description }}</p>
          </div>

          <!-- QR Code section -->
          <div v-if="spot.qr_code" class="border-t border-gray-100 pt-4">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">QR Code</span>
              <span class="text-xs text-gray-500">{{ spot.qr_code.scan_count }} scans</span>
            </div>
            
            <!-- QR Code display -->
            <div v-if="qrImages[spot.id]" class="mb-3 text-center">
              <img :src="qrImages[spot.id]" alt="QR Code" class="mx-auto w-32 h-32" />
            </div>
            
            <div class="flex flex-wrap gap-2">
              <button
                v-if="!qrImages[spot.id]"
                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                @click="loadQr(spot)"
                :disabled="loadingId === spot.id"
              >
                {{ loadingId === spot.id ? 'Loading...' : 'Show QR Code' }}
              </button>
              
              <button
                v-if="qrImages[spot.id]"
                class="text-xs text-gray-600 hover:text-gray-800 font-medium"
                @click="qrImages[spot.id] = ''"
              >
                Hide QR Code
              </button>
              
              <a
                :href="route('admin.parking-spots.qr-download', { parkingSpot: spot.id })"
                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                download
              >
                Download PNG
              </a>
              
              <button
                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                @click="copyToClipboard(spot.qr_code.stable_url, spot.id)"
              >
                {{ copiedId === spot.id ? 'Copied!' : 'Copy URL' }}
              </button>
            </div>
          </div>

          <!-- Actions -->
          <div class="border-t border-gray-100 pt-4 mt-4">
            <div class="flex justify-between items-center">
              <button
                v-if="isOwner"
                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                @click="editSpot(spot)"
              >
                Edit
              </button>
              <button
                v-if="isOwner"
                class="text-sm text-red-600 hover:text-red-800 font-medium"
                @click="deleteSpot(spot)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="parkingSpots.length === 0" class="text-center py-12">
        <Squares2X2Icon class="mx-auto h-12 w-12 text-gray-400" />
        <h3 class="mt-2 text-sm font-semibold text-gray-900">No parking spots</h3>
        <p class="mt-1 text-sm text-gray-500">Get started by creating your first parking spot.</p>
        <div v-if="isOwner" class="mt-6">
          <button
            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
            @click="showCreateForm = true"
          >
            Add Parking Spot
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <AppModal v-if="editingSpot" @close="editingSpot = null">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Edit Parking Spot</h2>
        <form @submit.prevent="submitEdit" class="space-y-4">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Spot Number <span class="text-red-500">*</span></label>
              <input v-model="editForm.spot_number" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
              <p v-if="editForm.errors.spot_number" class="mt-1 text-sm text-red-600">{{ editForm.errors.spot_number }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
              <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</p>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
              <input v-model="editForm.location" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
              <input v-model="editForm.sort_order" type="number" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="editForm.description" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></textarea>
          </div>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2">
              <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
              <span class="text-sm font-medium text-gray-700">Active</span>
            </label>
          </div>
          <div class="flex gap-3 pt-4">
            <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60 transition-colors">
              Update
            </button>
            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" @click="editingSpot = null">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </AppModal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import { Squares2X2Icon } from '@heroicons/vue/24/outline';
import type { ParkingSpot } from '@/types';

interface PageProps {
  parkingSpots: ParkingSpot[];
  [key: string]: unknown;
}

const props = usePage<PageProps>().props;
const parkingSpots = ref<ParkingSpot[]>(props.parkingSpots);

const isOwner = (usePage().props.auth as any)?.user?.role === 'business_owner';

const showCreateForm = ref(false);
const loadingId = ref<string | null>(null);
const copiedId = ref<string | null>(null);
const qrImages = ref<Record<string, string>>({});
const editingSpot = ref<ParkingSpot | null>(null);

const createForm = useForm({
  spot_number: '',
  name: '',
  description: '',
  location: '',
  is_active: true,
  sort_order: 0,
});

const editForm = useForm({
  spot_number: '',
  name: '',
  description: '',
  location: '',
  is_active: true,
  sort_order: 0,
});

function submitCreate() {
  createForm.post(route('admin.parking-spots.store'), {
    onSuccess: () => {
      createForm.reset();
      showCreateForm.value = false;
    },
  });
}

function editSpot(spot: ParkingSpot) {
  editingSpot.value = spot;
  editForm.spot_number = spot.spot_number;
  editForm.name = spot.name;
  editForm.description = spot.description || '';
  editForm.location = spot.location || '';
  editForm.is_active = spot.is_active;
  editForm.sort_order = spot.sort_order;
}

function submitEdit() {
  if (!editingSpot.value) return;
  
  editForm.patch(route('admin.parking-spots.update', { parkingSpot: editingSpot.value.id }), {
    onSuccess: () => {
      editingSpot.value = null;
      editForm.reset();
    },
  });
}

function deleteSpot(spot: ParkingSpot) {
  if (confirm(`Are you sure you want to delete parking spot ${spot.spot_number}?`)) {
    useForm({}).delete(route('admin.parking-spots.destroy', { parkingSpot: spot.id }));
  }
}

async function loadQr(spot: ParkingSpot) {
  if (!spot.qr_code) return;
  
  loadingId.value = spot.id;
  try {
    const res = await fetch(route('admin.parking-spots.qr-image', { parkingSpot: spot.id }));
    const json = await res.json();
    qrImages.value[spot.id] = json.data.svg;
  } catch (error) {
    console.error('Failed to load QR code:', error);
  } finally {
    loadingId.value = null;
  }
}

async function copyToClipboard(text: string, spotId: string) {
  try {
    await navigator.clipboard.writeText(text);
    copiedId.value = spotId;
    setTimeout(() => {
      copiedId.value = null;
    }, 2000);
  } catch (error) {
    console.error('Failed to copy to clipboard:', error);
  }
}
</script>