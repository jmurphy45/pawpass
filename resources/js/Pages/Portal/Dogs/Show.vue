<template>
  <PortalLayout>
    <div class="space-y-6">
      <!-- Hero header -->
      <div
        class="rounded-xl overflow-hidden"
        :style="dog.color
          ? { background: `linear-gradient(135deg, ${dog.color}cc 0%, ${dog.color}77 100%)` }
          : { background: `linear-gradient(135deg, ${accentColor}cc 0%, ${accentColor}77 100%)` }"
      >
        <div class="px-6 py-6 flex items-center gap-4">
          <Link :href="route('portal.dogs.index')" class="text-white/60 hover:text-white transition-colors">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
          </Link>
          <div
            class="h-14 w-14 rounded-full flex items-center justify-center text-2xl font-bold text-white border-2 shrink-0"
            style="background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3);"
          >
            {{ dog.name[0]?.toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold text-white">{{ dog.name }}</h1>
            <p v-if="dog.breed" class="text-white/70 text-sm">{{ dog.breed }}</p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-4xl font-black text-white leading-none">{{ dog.credit_balance }}</p>
            <p class="text-xs text-white/70 mt-0.5">credits</p>
          </div>
          <Link
            :href="route('portal.dogs.edit', { dog: dog.id })"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shrink-0"
            style="background: rgba(255,255,255,0.15); color: white;"
          >Edit</Link>
        </div>
      </div>

      <!-- Dog info grid -->
      <div class="card-padded grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
          <p class="text-xs font-semibold text-text-muted uppercase tracking-wide">Breed</p>
          <p class="mt-1 font-medium text-text-body">{{ dog.breed ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold text-text-muted uppercase tracking-wide">Date of Birth</p>
          <p class="mt-1 font-medium text-text-body">{{ dog.dob ? formatDate(dog.dob) : '—' }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold text-text-muted uppercase tracking-wide">Fur Color</p>
          <div class="mt-1 flex items-center gap-2">
            <span
              v-if="dog.color"
              class="h-5 w-5 rounded-full border border-border-warm shrink-0"
              :style="{ backgroundColor: dog.color }"
            />
            <p class="font-medium text-text-body">{{ dog.color ?? '—' }}</p>
          </div>
        </div>
        <div v-if="dog.credits_expire_at">
          <p class="text-xs font-semibold text-text-muted uppercase tracking-wide">Credits Expire</p>
          <p class="mt-1 font-medium text-amber-600">{{ formatDate(dog.credits_expire_at) }}</p>
        </div>
      </div>

      <!-- Credit ledger -->
      <div>
        <h2 class="text-base font-semibold text-text-body mb-3">Credit History</h2>

        <div v-if="ledger.data.length === 0" class="card p-10 text-center">
          <p class="text-sm text-text-muted">No credit transactions yet.</p>
        </div>

        <div v-else class="card overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr style="border-bottom: 1px solid #e5e0d8; background-color: #faf9f6;">
                <th class="text-left px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Type</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Amount</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Balance</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-text-muted uppercase tracking-wide">Date</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="entry in ledger.data"
                :key="entry.id"
                class="hover:bg-surface transition-colors"
                style="border-bottom: 1px solid #f0ede8;"
              >
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5">
                    <span class="text-base leading-none">{{ ledgerIcon(entry.type) }}</span>
                    <span class="capitalize text-text-body">{{ formatType(entry.type) }}</span>
                  </span>
                  <p v-if="entry.note" class="text-xs text-text-muted mt-0.5">{{ entry.note }}</p>
                </td>
                <td class="px-4 py-3 text-right font-semibold" :class="entry.amount < 0 ? 'text-red-600' : 'text-green-600'">
                  {{ entry.amount > 0 ? '+' : '' }}{{ entry.amount }}
                </td>
                <td class="px-4 py-3 text-right font-medium text-text-body">{{ entry.balance_after }}</td>
                <td class="px-4 py-3 text-right text-text-muted">{{ formatDate(entry.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="ledger.meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm">
          <p class="text-text-muted">Page {{ ledger.meta.current_page }} of {{ ledger.meta.last_page }}</p>
          <div class="flex gap-2">
            <Link
              v-if="ledger.meta.current_page > 1"
              :href="route('portal.dogs.show', { dog: dog.id, page: ledger.meta.current_page - 1 })"
              class="btn-secondary text-xs py-1.5 px-3"
            >Previous</Link>
            <Link
              v-if="ledger.meta.current_page < ledger.meta.last_page"
              :href="route('portal.dogs.show', { dog: dog.id, page: ledger.meta.current_page + 1 })"
              class="btn-secondary text-xs py-1.5 px-3"
            >Next</Link>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { CreditLedger, PaginatedResponse, PageProps } from '@/types';

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

const page = usePage<PageProps>();
const accentColor = computed(() => page.props.tenant?.primary_color ?? '#4f46e5');

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
