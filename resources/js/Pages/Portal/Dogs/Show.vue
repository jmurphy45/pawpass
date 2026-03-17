<template>
  <PortalLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link :href="route('portal.dogs.index')" class="text-gray-400 hover:text-gray-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
          </Link>
          <h1 class="text-2xl font-bold text-gray-900">{{ dog.name }}</h1>
        </div>
        <Link
          :href="route('portal.dogs.edit', { dog: dog.id })"
          class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >Edit</Link>
      </div>

      <!-- Dog info card -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Breed</p>
          <p class="mt-1 font-medium text-gray-900">{{ dog.breed ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Date of Birth</p>
          <p class="mt-1 font-medium text-gray-900">{{ dog.dob ? formatDate(dog.dob) : '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Credits</p>
          <p class="mt-1 font-bold text-2xl text-gray-900">{{ dog.credit_balance }}</p>
        </div>
        <div v-if="dog.credits_expire_at">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Expires</p>
          <p class="mt-1 font-medium text-gray-900">{{ formatDate(dog.credits_expire_at) }}</p>
        </div>
      </div>

      <!-- Credit ledger -->
      <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Credit History</h2>

        <div v-if="ledger.data.length === 0" class="rounded-2xl bg-white border border-dashed border-gray-200 p-8 text-center text-gray-500 text-sm">
          No credit transactions yet.
        </div>

        <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50">
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Balance</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="entry in ledger.data" :key="entry.id" class="hover:bg-gray-50/50">
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5">
                    <span>{{ ledgerIcon(entry.type) }}</span>
                    <span class="capitalize text-gray-700">{{ formatType(entry.type) }}</span>
                  </span>
                  <p v-if="entry.note" class="text-xs text-gray-400 mt-0.5">{{ entry.note }}</p>
                </td>
                <td class="px-4 py-3 text-right" :class="entry.amount < 0 ? 'text-red-600' : 'text-green-600'">
                  {{ entry.amount > 0 ? '+' : '' }}{{ entry.amount }}
                </td>
                <td class="px-4 py-3 text-right font-medium text-gray-900">{{ entry.balance_after }}</td>
                <td class="px-4 py-3 text-right text-gray-500">{{ formatDate(entry.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="ledger.meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm">
          <p class="text-gray-500">
            Page {{ ledger.meta.current_page }} of {{ ledger.meta.last_page }}
          </p>
          <div class="flex gap-2">
            <Link
              v-if="ledger.meta.current_page > 1"
              :href="route('portal.dogs.show', { dog: dog.id, page: ledger.meta.current_page - 1 })"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 hover:bg-gray-50"
            >Previous</Link>
            <Link
              v-if="ledger.meta.current_page < ledger.meta.last_page"
              :href="route('portal.dogs.show', { dog: dog.id, page: ledger.meta.current_page + 1 })"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 hover:bg-gray-50"
            >Next</Link>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { CreditLedger, PaginatedResponse } from '@/types';

interface DogDetail {
  id: string;
  name: string;
  breed: string | null;
  color: string | null;
  dob: string | null;
  credit_balance: number;
  credits_expire_at: string | null;
}

defineProps<{
  dog: DogDetail;
  ledger: PaginatedResponse<CreditLedger>;
}>();

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatType(type: string) {
  return type.replace(/_/g, ' ');
}

function ledgerIcon(type: string): string {
  const icons: Record<string, string> = {
    purchase: '💳',
    subscription: '🔄',
    deduction: '➖',
    refund: '↩️',
    goodwill: '🎁',
    correction_add: '✚',
    correction_remove: '✖',
    expiry_removal: '⏳',
    transfer_in: '⬇️',
    transfer_out: '⬆️',
  };
  return icons[type] ?? '•';
}
</script>
