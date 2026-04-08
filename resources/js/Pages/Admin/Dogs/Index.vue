<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-text-body">Dogs</h1>
          <p class="text-sm text-text-muted mt-0.5">{{ dogs.total }} total</p>
        </div>
        <Link :href="route('admin.dogs.create')"><AppButton variant="primary">Add Dog</AppButton></Link>
      </div>

      <!-- Filters -->
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
          <AppInput
            v-model="searchQuery"
            placeholder="Search by name…"
            @input="onSearchInput"
          />
        </div>
        <div class="flex gap-1 flex-wrap">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            @click="setStatus(tab.value)"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
              currentStatus === tab.value
                ? 'bg-indigo-600 text-white'
                : 'bg-surface-subtle text-text-muted hover:text-text-body',
            ]"
          >{{ tab.label }}</button>
        </div>
      </div>

      <AppCard class="overflow-hidden">
        <div v-if="dogs.data.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No dogs found.
        </div>
        <ul v-else>
          <li v-for="dog in dogs.data" :key="dog.id" class="flex items-center border-b border-border-warm px-5 py-3 transition-colors hover:bg-surface last:border-b-0 gap-3">
            <!-- Avatar initials -->
            <div class="h-9 w-9 rounded-full bg-surface-subtle flex items-center justify-center text-sm font-semibold text-text-body shrink-0">
              {{ dog.name[0]?.toUpperCase() }}
            </div>

            <!-- Dog info -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-text-body truncate">{{ dog.name }}</p>
              <p class="text-xs text-text-muted truncate">
                {{ dog.customer_name }}
                <span v-if="dog.breed"> · {{ dog.breed }}</span>
              </p>
            </div>

            <!-- Status badge (only shown for non-active) -->
            <AppBadge
              v-if="dog.status !== 'active'"
              :color="dog.status === 'suspended' ? 'yellow' : 'gray'"
              class="hidden sm:inline-flex"
            >{{ dog.status }}</AppBadge>

            <!-- Credit badge -->
            <AppBadge
              class="hidden sm:inline-flex"
              :color="dog.credit_balance <= 0 ? 'red' : dog.credit_balance <= 3 ? 'yellow' : 'green'"
            >{{ dog.credit_balance }} cr</AppBadge>

            <!-- View link with chevron -->
            <Link
              :href="route('admin.dogs.show', { dog: dog.id })"
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
        <div v-if="dogs.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-border-warm">
          <p class="text-xs text-text-muted">
            Page {{ dogs.current_page }} of {{ dogs.last_page }}
          </p>
          <div class="flex gap-2">
            <AppButton
              variant="secondary"
              size="sm"
              :disabled="!dogs.prev_page_url"
              @click="goToPage(dogs.current_page - 1)"
            >Previous</AppButton>
            <AppButton
              variant="secondary"
              size="sm"
              :disabled="!dogs.next_page_url"
              @click="goToPage(dogs.current_page + 1)"
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

interface Dog {
  id: string;
  name: string;
  breed: string | null;
  credit_balance: number;
  customer_name: string | null;
  customer_id: string;
  status: string;
}

const props = defineProps<{
  dogs: {
    data: Dog[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  filters: { search: string; status: string };
}>();

const statusTabs = [
  { value: '', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'inactive', label: 'Inactive' },
];

const searchQuery = ref(props.filters.search);
const currentStatus = ref(props.filters.status);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function navigate(page?: number) {
  const params: Record<string, string | number> = {};
  if (searchQuery.value) params.search = searchQuery.value;
  if (currentStatus.value) params.status = currentStatus.value;
  if (page && page > 1) params.page = page;
  router.get(route('admin.dogs.index'), params, { preserveState: true, replace: true });
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => navigate(), 350);
}

function setStatus(status: string) {
  currentStatus.value = status;
  navigate();
}

function goToPage(page: number) {
  navigate(page);
}
</script>
