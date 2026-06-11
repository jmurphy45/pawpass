<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">QR Codes</h1>
        <button
          v-if="isOwner"
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors"
          @click="showCreateForm = !showCreateForm"
        >
          Add QR Code
        </button>
      </div>

      <!-- Create form -->
      <div v-if="showCreateForm && isOwner" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">New QR Code</h2>
        <form @submit.prevent="submitCreate" class="space-y-4">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Key <span class="text-gray-400 font-normal">(unique identifier)</span></label>
              <input v-model="createForm.key" type="text" placeholder="e.g. checkin" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
              <p v-if="createForm.errors.key" class="mt-1 text-sm text-red-600">{{ createForm.errors.key }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
              <input v-model="createForm.label" type="text" placeholder="e.g. Dog Check-In" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Target URL</label>
            <input v-model="createForm.target_url" type="text" placeholder="/my or https://..." class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
            <p class="mt-1 text-xs text-gray-500">Use a relative path (e.g. /my) to always use the current subdomain, or an absolute URL.</p>
            <p v-if="createForm.errors.target_url" class="mt-1 text-sm text-red-600">{{ createForm.errors.target_url }}</p>
          </div>
          <div class="flex gap-3">
            <button type="submit" :disabled="createForm.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60 transition-colors">
              Create
            </button>
            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" @click="showCreateForm = false">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- QR code cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="qr in qrCodes"
          :key="qr.id"
          class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col gap-4"
          :class="{ 'opacity-60': !qr.is_active }"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-semibold text-gray-900">{{ qr.label ?? qr.key }}</p>
              <p class="text-xs text-gray-500 mt-0.5">Key: <code class="bg-gray-100 px-1 rounded">{{ qr.key }}</code></p>
            </div>
            <span v-if="!qr.is_active" class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
          </div>

          <!-- Stable URL -->
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Stable URL (never changes)</label>
            <div class="flex items-center gap-2">
              <input
                :value="qr.stable_url"
                readonly
                class="flex-1 min-w-0 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 font-mono"
              />
              <button
                class="shrink-0 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                @click="copyUrl(qr.stable_url)"
              >
                {{ copiedId === qr.id ? 'Copied!' : 'Copy' }}
              </button>
            </div>
          </div>

          <!-- Target URL (editable for owner) -->
          <div v-if="editingId === qr.id && isOwner">
            <label class="block text-xs font-medium text-gray-500 mb-1">Redirect Target</label>
            <input v-model="editTarget" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
            <div class="flex gap-2 mt-2">
              <button
                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-60 transition-colors"
                :disabled="updateForm.processing"
                @click="saveEdit(qr)"
              >Save</button>
              <button
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                @click="editingId = null"
              >Cancel</button>
            </div>
          </div>
          <div v-else>
            <label class="block text-xs font-medium text-gray-500 mb-1">Redirects to</label>
            <p class="text-sm text-gray-700 truncate font-mono">{{ qr.target_url }}</p>
          </div>

          <!-- QR image -->
          <div>
            <div v-if="qrImages[qr.id]" class="flex items-end gap-3">
              <img :src="qrImages[qr.id]" alt="QR code" class="w-32 h-32 border border-gray-200 rounded-lg" />
              <a
                :href="route('admin.qr-codes.download', { qrCode: qr.id })"
                download
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              >
                Download PNG
              </a>
            </div>
            <button
              v-else
              :disabled="loadingId === qr.id"
              class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60 transition-colors"
              @click="loadQr(qr)"
            >
              {{ loadingId === qr.id ? 'Loading…' : 'Show QR Code' }}
            </button>
          </div>

          <!-- Scan count + actions -->
          <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <p class="text-xs text-gray-500">{{ qr.scan_count }} scan{{ qr.scan_count !== 1 ? 's' : '' }}</p>
            <div v-if="isOwner" class="flex gap-2">
              <button
                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                @click="startEdit(qr)"
              >Edit</button>
              <button
                v-if="qr.is_active"
                class="text-xs text-red-500 hover:text-red-700 font-medium"
                @click="deactivate(qr)"
              >Deactivate</button>
            </div>
          </div>
        </div>
      </div>

      <p v-if="qrCodes.length === 0" class="text-sm text-gray-500">No QR codes yet.</p>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface QrCodeItem {
  id: string;
  key: string;
  label: string | null;
  target_url: string;
  is_active: boolean;
  scan_count: number;
  stable_url: string;
}

interface PageProps {
  qrCodes: QrCodeItem[];
  [key: string]: unknown;
}

const props = usePage<PageProps>().props;
const qrCodes = ref<QrCodeItem[]>(props.qrCodes);

const isOwner = (usePage().props.auth as any)?.user?.role === 'business_owner';

const showCreateForm = ref(false);
const editingId = ref<string | null>(null);
const editTarget = ref('');
const loadingId = ref<string | null>(null);
const copiedId = ref<string | null>(null);
const qrImages = ref<Record<string, string>>({});

const createForm = useForm({
  key: '',
  label: '',
  target_url: '',
});

const updateForm = useForm({
  target_url: '',
});

function submitCreate() {
  createForm.post(route('admin.qr-codes.store'), {
    onSuccess: () => {
      createForm.reset();
      showCreateForm.value = false;
    },
  });
}

function startEdit(qr: QrCodeItem) {
  editingId.value = qr.id;
  editTarget.value = qr.target_url;
}

function saveEdit(qr: QrCodeItem) {
  updateForm.target_url = editTarget.value;
  updateForm.patch(route('admin.qr-codes.update', { qrCode: qr.id }), {
    onSuccess: () => { editingId.value = null; },
  });
}

function deactivate(qr: QrCodeItem) {
  useForm({}).delete(route('admin.qr-codes.destroy', { qrCode: qr.id }));
}

async function loadQr(qr: QrCodeItem) {
  loadingId.value = qr.id;
  try {
    const res = await fetch(route('admin.qr-codes.image', { qrCode: qr.id }));
    const json = await res.json();
    qrImages.value[qr.id] = json.data.svg;
  } finally {
    loadingId.value = null;
  }
}

async function copyUrl(url: string) {
  await navigator.clipboard.writeText(url);
  // find qr by stable_url to set copiedId
  const qr = qrCodes.value.find(q => q.stable_url === url);
  if (qr) {
    copiedId.value = qr.id;
    setTimeout(() => { copiedId.value = null; }, 2000);
  }
}
</script>
