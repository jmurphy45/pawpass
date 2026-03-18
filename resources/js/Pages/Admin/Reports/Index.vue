<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Reports</h1>

      <!-- Operational Reports (Starter+) -->
      <section>
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Operational</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <template v-if="hasBasicReporting">
            <a
              :href="route('admin.reports.attendance')"
              class="block rounded-lg border border-gray-200 bg-white p-5 hover:shadow-md transition-shadow"
            >
              <h3 class="font-semibold text-gray-900 text-sm">Attendance Summary</h3>
              <p class="mt-1 text-xs text-gray-500">Check-in counts and unique dogs over time.</p>
            </a>
            <a
              :href="route('admin.reports.credit-status')"
              class="block rounded-lg border border-gray-200 bg-white p-5 hover:shadow-md transition-shadow"
            >
              <h3 class="font-semibold text-gray-900 text-sm">Credit Status</h3>
              <p class="mt-1 text-xs text-gray-500">Dogs with zero or low credits.</p>
            </a>
            <a
              v-if="isOwner"
              :href="route('admin.reports.packages')"
              class="block rounded-lg border border-gray-200 bg-white p-5 hover:shadow-md transition-shadow"
            >
              <h3 class="font-semibold text-gray-900 text-sm">Package Performance</h3>
              <p class="mt-1 text-xs text-gray-500">Revenue and orders per package.</p>
            </a>
          </template>
          <div v-else class="col-span-full text-sm text-gray-500 italic">
            Upgrade to Starter or higher to access operational reports.
          </div>
        </div>
      </section>

      <!-- Financial Reports (Pro+) -->
      <section>
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Financial</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <template v-if="hasFinancialReports && isOwner">
            <a
              :href="route('admin.reports.revenue')"
              class="block rounded-lg border border-gray-200 bg-white p-5 hover:shadow-md transition-shadow"
            >
              <h3 class="font-semibold text-gray-900 text-sm">Revenue Summary</h3>
              <p class="mt-1 text-xs text-gray-500">Gross revenue, platform fees, and net payout by period.</p>
            </a>
            <a
              :href="route('admin.reports.credits')"
              class="block rounded-lg border border-gray-200 bg-white p-5 hover:shadow-md transition-shadow"
            >
              <h3 class="font-semibold text-gray-900 text-sm">Credit Issuance &amp; Usage</h3>
              <p class="mt-1 text-xs text-gray-500">Credit ledger activity grouped by type.</p>
            </a>
            <a
              :href="route('admin.reports.customers')"
              class="block rounded-lg border border-gray-200 bg-white p-5 hover:shadow-md transition-shadow"
            >
              <h3 class="font-semibold text-gray-900 text-sm">Customer Lifetime Value</h3>
              <p class="mt-1 text-xs text-gray-500">Total spend and order count per customer.</p>
            </a>
          </template>
          <div v-else-if="!hasFinancialReports" class="col-span-full">
            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center">
              <p class="text-sm text-gray-500">Financial reports are available on the Pro plan and above.</p>
              <a :href="route('admin.billing.index')" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                Upgrade now →
              </a>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const auth = computed(() => page.props.auth);
const isOwner = computed(() => auth.value.user?.role === 'business_owner');
const tenantPlan = computed(() => page.props.tenantPlan);
const hasBasicReporting = computed(() => ['starter', 'pro', 'business'].includes(tenantPlan.value ?? ''));
const hasFinancialReports = computed(() => ['pro', 'business'].includes(tenantPlan.value ?? ''));
</script>
