<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-900">{{ dog.name }}</h1>
          <span
            class="text-xs font-semibold px-2 py-0.5 rounded-full"
            :class="{
              'bg-green-100 text-green-700': dog.status === 'active',
              'bg-amber-100 text-amber-700': dog.status === 'suspended',
              'bg-gray-100 text-gray-500':   dog.status === 'inactive',
            }"
          >{{ dog.status.charAt(0).toUpperCase() + dog.status.slice(1) }}</span>
        </div>
        <Link :href="route('admin.dogs.edit', { dog: dog.id })" class="text-sm text-indigo-600 hover:underline">Edit</Link>
      </div>
      <AppCard :padded="true" class="space-y-2">
        <p class="text-sm text-gray-600">Breed: {{ dog.breed ?? '—' }}</p>
        <p class="text-sm text-gray-600">Credits: {{ dog.credit_balance }}</p>
        <p class="text-sm text-gray-600">Owner: {{ dog.customer_name }}</p>
      </AppCard>

      <!-- Vaccinations -->
      <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Vaccinations</h2>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
          <div v-if="vaccinations.length === 0" class="px-5 py-4 text-sm text-gray-500">
            No vaccination records on file.
          </div>
          <div
            v-for="v in vaccinations"
            :key="v.id"
            class="px-5 py-3 flex items-start justify-between gap-4"
          >
            <div class="flex items-start gap-3 min-w-0">
              <span
                class="mt-0.5 h-2.5 w-2.5 rounded-full shrink-0"
                :class="v.is_valid ? 'bg-green-500' : 'bg-red-500'"
              />
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900">{{ v.vaccine_name }}</p>
                <p class="text-xs text-gray-500">
                  Given {{ formatDate(v.administered_at) }}
                  <template v-if="v.expires_at">
                    · Expires {{ formatDate(v.expires_at) }}
                  </template>
                  <template v-else>
                    · No expiry
                  </template>
                </p>
                <p v-if="v.administered_by" class="text-xs text-gray-400">By {{ v.administered_by }}</p>
                <p v-if="v.notes" class="text-xs text-gray-400 mt-0.5">{{ v.notes }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <span
                class="text-xs font-medium px-2 py-0.5 rounded-full"
                :class="v.is_valid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
              >{{ v.is_valid ? 'Valid' : 'Expired' }}</span>
              <button
                type="button"
                class="text-xs text-red-500 hover:text-red-700"
                @click="deleteVaccination(v.id)"
              >Remove</button>
            </div>
          </div>
        </div>

        <!-- Add vaccination form -->
        <form
          class="mt-3 bg-white rounded-xl border border-gray-200 p-4 space-y-3"
          @submit.prevent="submitVaccination"
        >
          <p class="text-sm font-medium text-gray-700">Add Vaccination Record</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Vaccine name <span class="text-red-500">*</span></label>
              <input v-model="form.vaccine_name" type="text" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="e.g. Rabies" required />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Date administered <span class="text-red-500">*</span></label>
              <input v-model="form.administered_at" type="date" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Expiry date</label>
              <input v-model="form.expires_at" type="date" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Administered by</label>
              <input v-model="form.administered_by" type="text" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="Vet name / clinic" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Notes</label>
            <input v-model="form.notes" type="text" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="Optional notes" />
          </div>
          <div class="flex items-center gap-3">
            <AppButton type="submit" variant="primary" :disabled="submitting">
              {{ submitting ? 'Saving…' : 'Add Record' }}
            </AppButton>
            <p v-if="errors.vaccine_name" class="text-xs text-red-600">{{ errors.vaccine_name }}</p>
          </div>
        </form>
      </div>

      <h2 class="text-lg font-semibold text-gray-900">Credit History</h2>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div v-if="ledger.length === 0" class="px-5 py-4 text-sm text-gray-500">
          No credit transactions yet.
        </div>
        <table v-else class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
              <th class="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Type</th>
              <th class="px-5 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Amount</th>
              <th class="px-5 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Balance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="entry in ledger" :key="entry.id" class="hover:bg-gray-50/50">
              <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDateTime(entry.created_at) }}</td>
              <td class="px-5 py-3">
                <div class="flex items-start gap-1.5">
                  <span class="mt-px">{{ typeIcon[entry.type] ?? '•' }}</span>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ typeLabel[entry.type] ?? entry.type }}</p>
                    <p v-if="entry.package_name" class="text-xs text-gray-400">{{ entry.package_name }}</p>
                    <p v-if="entry.note" class="text-xs text-gray-400">{{ entry.note }}</p>
                    <p v-if="entry.performed_by" class="text-xs text-gray-400">By {{ entry.performed_by }}</p>
                    <p v-if="entry.expires_at" class="text-xs text-amber-600">Expires {{ formatDate(entry.expires_at) }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3 text-right font-medium whitespace-nowrap" :class="entry.amount >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ entry.amount >= 0 ? '+' : '' }}{{ entry.amount }}
              </td>
              <td class="px-5 py-3 text-right text-sm text-gray-700 whitespace-nowrap tabular-nums">{{ entry.balance_after }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Notes & Comments -->
      <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Notes &amp; Comments</h2>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
          <div v-if="comments.length === 0" class="px-5 py-4 text-sm text-gray-500">
            No notes or comments on file.
          </div>
          <div v-for="comment in comments" :key="comment.id" class="px-5 py-3 flex items-start gap-3">
            <span
              class="mt-0.5 shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full"
              :class="{
                'bg-green-100 text-green-700': comment.type === 'daycare',
                'bg-blue-100 text-blue-700': comment.type === 'boarding',
              }"
            >{{ comment.type === 'daycare' ? 'Day Care' : 'Boarding' }}</span>
            <div class="min-w-0">
              <p class="text-sm text-gray-900">{{ comment.body }}</p>
              <p class="text-xs text-gray-400 mt-0.5">
                {{ formatDate(comment.date) }}<template v-if="comment.author"> · {{ comment.author }}</template>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
  <AppModal :open="confirmModal.open" :title="confirmModal.title" :message="confirmModal.message" @confirm="handleConfirm" @cancel="handleCancel" />
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface Vaccination {
  id: string;
  vaccine_name: string;
  administered_at: string;
  expires_at: string | null;
  administered_by: string | null;
  notes: string | null;
  is_valid: boolean;
}

interface Comment {
  id: string | number;
  type: 'daycare' | 'boarding';
  body: string;
  author: string | null;
  date: string;
  created_at: string;
}

const props = defineProps<{
  dog: { id: string; name: string; breed: string | null; dob: string | null; sex: string | null; credit_balance: number; vet_name: string | null; vet_phone: string | null; customer_id: string; customer_name: string | null; status: 'active' | 'inactive' | 'suspended' };
  ledger: Array<{
    id: string;
    type: string;
    amount: number;
    balance_after: number;
    note: string | null;
    created_at: string;
    expires_at: string | null;
    order_id: string | null;
    package_name: string | null;
    performed_by: string | null;
  }>;
  attendance: Array<{ id: string; checked_in_at: string; checked_out_at: string | null }>;
  vaccinations: Vaccination[];
  comments: Comment[];
}>();

const page = usePage<PageProps>();
const form = ref({ vaccine_name: '', administered_at: '', expires_at: '', administered_by: '', notes: '' });
const submitting = ref(false);
const errors = ref<Record<string, string>>({});

const typeLabel: Record<string, string> = {
  purchase: 'Purchase',
  subscription: 'Subscription',
  deduction: 'Day Care',
  refund: 'Refund',
  goodwill: 'Goodwill',
  correction_add: 'Correction (+)',
  correction_remove: 'Correction (−)',
  expiry_removal: 'Expired',
  transfer_in: 'Transfer In',
  transfer_out: 'Transfer Out',
};

const typeIcon: Record<string, string> = {
  purchase: '💳',
  subscription: '🔄',
  deduction: '🐾',
  refund: '↩️',
  goodwill: '🎁',
  correction_add: '✚',
  correction_remove: '✖',
  expiry_removal: '⏳',
  transfer_in: '⬇️',
  transfer_out: '⬆️',
};

function formatDate(iso: string) {
  const tz = page.props.tenant?.timezone ?? 'UTC';
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: tz });
}

function formatDateTime(iso: string) {
  const tz = page.props.tenant?.timezone ?? 'UTC';
  return new Date(iso).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: 'numeric', minute: '2-digit', timeZone: tz,
  });
}

function submitVaccination() {
  submitting.value = true;
  errors.value = {};
  router.post(
    route('admin.dogs.vaccinations.store', { dog: props.dog.id }),
    { ...form.value },
    {
      onSuccess: () => {
        form.value = { vaccine_name: '', administered_at: '', expires_at: '', administered_by: '', notes: '' };
      },
      onError: (e) => { errors.value = e; },
      onFinish: () => { submitting.value = false; },
    },
  );
}

const confirmModal = ref<{ open: boolean; title: string; message: string; onConfirm: (() => void) | null }>
  ({ open: false, title: '', message: '', onConfirm: null });

function askConfirm(title: string, message: string, onConfirm: () => void) {
  confirmModal.value = { open: true, title, message, onConfirm };
}
function handleConfirm() { confirmModal.value.onConfirm?.(); confirmModal.value.open = false; }
function handleCancel() { confirmModal.value.open = false; }

function deleteVaccination(vaccinationId: string) {
  askConfirm(
    'Remove Vaccination',
    'This vaccination record will be permanently deleted.',
    () => router.delete(route('admin.dogs.vaccinations.destroy', { dog: props.dog.id, vaccination: vaccinationId })),
  );
}
</script>
