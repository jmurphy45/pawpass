<template>
  <AdminLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Customer Intelligence</h1>
        <p class="mt-1 text-sm text-gray-500">Identify at-risk customers, price-sensitive buyers, and package mismatches.</p>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              activeTab === tab.key
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
              'whitespace-nowrap border-b-2 pb-3 text-sm font-medium flex items-center gap-2',
            ]"
          >
            {{ tab.label }}
            <span
              :class="[
                activeTab === tab.key ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500',
                'rounded-full px-2 py-0.5 text-xs font-semibold',
              ]"
            >{{ tab.count }}</span>
          </button>
        </nav>
      </div>

      <!-- Churn Risk Tab -->
      <div v-if="activeTab === 'churn'">
        <div v-if="churnRisk.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
          No at-risk customers right now. All customers have visited within the last 30 days.
        </div>
        <div v-else class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left font-medium text-gray-600">Customer</th>
                  <th class="px-4 py-3 text-left font-medium text-gray-600">Last Visit</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Days Since</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Last 30d</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Prior 30d</th>
                  <th class="px-4 py-3 text-center font-medium text-gray-600">Trend</th>
                  <th class="px-4 py-3 text-center font-medium text-gray-600">Risk</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in churnRisk" :key="row.customer_id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ row.customer_name }}</div>
                    <div v-if="row.email" class="text-xs text-gray-400">{{ row.email }}</div>
                  </td>
                  <td class="px-4 py-3 text-gray-500">{{ row.last_visit_at ? fmtDate(row.last_visit_at) : '—' }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.days_since_last_visit }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.visits_last_30 }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.visits_prior_30 }}</td>
                  <td class="px-4 py-3 text-center">
                    <span v-if="row.freq_delta < 0" class="text-red-600 font-medium">▼ {{ Math.abs(row.freq_delta) }}</span>
                    <span v-else-if="row.freq_delta > 0" class="text-green-600 font-medium">▲ {{ row.freq_delta }}</span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span
                      :class="[
                        row.risk_level === 'red'
                          ? 'bg-red-100 text-red-700'
                          : 'bg-yellow-100 text-yellow-700',
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                      ]"
                    >{{ row.risk_level === 'red' ? 'High Risk' : 'At Risk' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Price Sensitivity Tab -->
      <div v-if="activeTab === 'price'">
        <div v-if="priceSensitivity.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
          No price-sensitive customers detected. No customer has used promotions on 50%+ of their orders.
        </div>
        <div v-else class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left font-medium text-gray-600">Customer</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Total Orders</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Promo Orders</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Promo %</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Avg Discount</th>
                  <th class="px-4 py-3 text-center font-medium text-gray-600">Never Full Price</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in priceSensitivity" :key="row.customer_id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ row.customer_name }}</div>
                    <div v-if="row.email" class="text-xs text-gray-400">{{ row.email }}</div>
                  </td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.total_paid_orders }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.promo_orders }}</td>
                  <td class="px-4 py-3 text-right">
                    <span class="font-semibold text-orange-600">{{ row.promo_pct }}%</span>
                  </td>
                  <td class="px-4 py-3 text-right text-gray-700">${{ fmtCents(row.avg_discount_cents) }}</td>
                  <td class="px-4 py-3 text-center">
                    <span v-if="row.never_paid_full" class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">Yes</span>
                    <span v-else class="text-gray-400 text-xs">No</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Package Fit Tab -->
      <div v-if="activeTab === 'fit'">
        <p class="text-xs text-gray-500 mb-3">Customers whose visit frequency suggests a larger package would be a better value. Based on attendance over the last 90 days.</p>
        <div v-if="packageFit.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
          No package mismatches found. All active customers appear to be on an appropriate package tier.
        </div>
        <div v-else class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left font-medium text-gray-600">Customer</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Visits / Month</th>
                  <th class="px-4 py-3 text-left font-medium text-gray-600">Current Package</th>
                  <th class="px-4 py-3 text-left font-medium text-gray-600">Suggested Package</th>
                  <th class="px-4 py-3 text-right font-medium text-gray-600">Price</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in packageFit" :key="row.customer_id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ row.customer_name }}</div>
                    <div v-if="row.email" class="text-xs text-gray-400">{{ row.email }}</div>
                  </td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.visits_per_month }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ row.current_package_name ?? '—' }}</td>
                  <td class="px-4 py-3">
                    <span class="font-medium text-indigo-700">{{ row.suggested_package_name }}</span>
                    <span class="ml-1 text-xs text-gray-400">({{ row.suggested_credit_count }} credits)</span>
                  </td>
                  <td class="px-4 py-3 text-right text-gray-700">${{ fmtPrice(row.suggested_price) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface ChurnRow {
  customer_id: string;
  customer_name: string;
  email: string | null;
  last_visit_at: string | null;
  days_since_last_visit: number;
  visits_last_30: number;
  visits_prior_30: number;
  freq_delta: number;
  has_zero_credit_dog: boolean;
  recent_orders: number;
  risk_level: 'red' | 'amber';
}

interface PriceSensitivityRow {
  customer_id: string;
  customer_name: string;
  email: string | null;
  total_paid_orders: number;
  promo_orders: number;
  promo_pct: number;
  total_discount_cents: number;
  avg_discount_cents: number;
  never_paid_full: boolean;
}

interface PackageFitRow {
  customer_id: string;
  customer_name: string;
  email: string | null;
  visits_90_days: number;
  visits_per_month: number;
  current_package_name: string | null;
  current_credit_count: number | null;
  suggested_package_id: string;
  suggested_package_name: string;
  suggested_credit_count: number;
  suggested_price: number;
}

const props = defineProps<{
  churnRisk: ChurnRow[];
  priceSensitivity: PriceSensitivityRow[];
  packageFit: PackageFitRow[];
}>();

type TabKey = 'churn' | 'price' | 'fit';
const activeTab = ref<TabKey>('churn');

const tabs = computed(() => [
  { key: 'churn' as TabKey, label: 'Churn Risk', count: props.churnRisk.length },
  { key: 'price' as TabKey, label: 'Price Sensitive', count: props.priceSensitivity.length },
  { key: 'fit' as TabKey, label: 'Package Fit', count: props.packageFit.length },
]);

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function fmtCents(cents: number): string {
  return (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtPrice(price: number): string {
  return price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
