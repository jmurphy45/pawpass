<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Customers</h1>
          <p class="text-sm text-text-muted mt-0.5">{{ customers.total }} total</p>
        </div>
        <Link :href="route('admin.customers.create')"><AppButton variant="primary">Add Customer</AppButton></Link>
      </div>

      <!-- Search -->
      <AppInput
        v-model="searchQuery"
        placeholder="Search by name or email…"
        @input="onSearchInput"
      />

      <AppCard class="overflow-hidden">
        <div v-if="customers.data.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No customers found.
        </div>
        <ul v-else>
          <li v-for="customer in customers.data" :key="customer.id" class="flex items-center border-b border-border-warm px-5 py-3 transition-colors hover:bg-surface last:border-b-0 gap-3">
            <!-- Avatar initials -->
            <div class="h-9 w-9 rounded-full bg-surface-subtle flex items-center justify-center text-sm font-semibold text-text-body shrink-0">
              {{ customer.name[0]?.toUpperCase() }}
            </div>

            <!-- Customer info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5 min-w-0">
                <p class="text-sm font-medium text-text-body truncate">{{ customer.name }}</p>
                <span
                  v-if="customer.has_portal"
                  class="shrink-0 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-600 ring-1 ring-inset ring-indigo-500/20"
                  title="Has portal access"
                >Portal</span>
              </div>
              <p class="text-xs text-text-muted truncate">
                {{ customer.email ?? 'No email' }}
                <template v-if="customer.phone"> · {{ customer.phone }}</template>
                · {{ customer.dogs_count }} dog{{ customer.dogs_count !== 1 ? 's' : '' }}
                · {{ customer.orders_count }} order{{ customer.orders_count !== 1 ? 's' : '' }}
                <template v-if="customer.total_spent > 0"> · {{ formatMoney(customer.total_spent) }}</template>
              </p>
            </div>

            <!-- View link with chevron -->
            <Link
              :href="route('admin.customers.show', { customer: customer.id })"
              class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 shrink-0"
            >
              View
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
              </svg>
            </Link>
          </li>
        </ul>

        <!-- Pagination -->
        <div v-if="customers.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-border-warm">
          <p class="text-xs text-text-muted">
            Page {{ customers.current_page }} of {{ customers.last_page }}
          </p>
          <div class="flex gap-2">
            <AppButton
              variant="secondary"
              size="sm"
              :disabled="!customers.prev_page_url"
              @click="goToPage(customers.current_page - 1)"
            >Previous</AppButton>
            <AppButton
              variant="secondary"
              size="sm"
              :disabled="!customers.next_page_url"
              @click="goToPage(customers.current_page + 1)"
            >Next</AppButton>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';

interface Customer {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  dogs_count: number;
  orders_count: number;
  total_spent: number;
  has_portal: boolean;
  created_at: string;
}

const props = defineProps<{
  customers: {
    data: Customer[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  filters: { search: string };
}>();

const searchQuery = ref(props.filters.search);

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
}
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function navigate(page?: number) {
  const params: Record<string, string | number> = {};
  if (searchQuery.value) params.search = searchQuery.value;
  if (page && page > 1) params.page = page;
  router.get(route('admin.customers.index'), params, { preserveState: true, replace: true });
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => navigate(), 350);
}

function goToPage(page: number) {
  navigate(page);
}
</script>
