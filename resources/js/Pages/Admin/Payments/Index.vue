<template>
  <AdminLayout>
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Invoices</h1>
          <p class="text-sm text-gray-500 mt-0.5">All transactions processed through PawPass</p>
        </div>
      </div>

      <!-- Stats bar -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Revenue Collected</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatAmount(stats.total_paid_cents) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Paid</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.paid_count }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Pending Auth</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.authorized_count }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Refunded</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatAmount(stats.total_refunded_cents) }}</p>
        </div>
      </div>

      <!-- Filter tabs -->
      <div class="flex gap-1 border-b border-gray-200">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="setFilter(tab.value)"
          class="px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px"
          :class="currentStatus === tab.value
            ? 'border-indigo-600 text-indigo-600'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

        <!-- Empty state -->
        <div v-if="payments.data.length === 0" class="flex flex-col items-center justify-center px-6 py-20 text-center">
          <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
          </div>
          <p class="font-semibold text-gray-700">
            {{ currentStatus ? `No ${currentStatus} payments found` : 'No payments yet' }}
          </p>
          <p class="text-sm text-gray-500 mt-1">
            {{ currentStatus ? '' : 'Transactions will appear here once customers make purchases.' }}
          </p>
          <button
            v-if="currentStatus"
            @click="setFilter('')"
            class="mt-3 text-sm text-indigo-600 hover:text-indigo-800 font-medium"
          >Clear filter →</button>
        </div>

        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/70">
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Order</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Customer</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3 hidden sm:table-cell">Description</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3 hidden md:table-cell">Date</th>
              <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Amount</th>
              <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="payment in payments.data"
              :key="payment.id"
              class="hover:bg-indigo-50/40 transition-colors cursor-pointer"
              @click="openDrawer(payment)"
            >
              <!-- Order ref -->
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-semibold text-gray-700 bg-gray-100 px-2 py-0.5 rounded">{{ payment.short_ref }}</span>
                  <span
                    v-if="payment.type === 'boarding'"
                    class="text-xs px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 font-medium"
                  >boarding</span>
                </div>
              </td>

              <!-- Customer -->
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2.5">
                  <div
                    class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                    :style="{ background: avatarColor(payment.customer_name ?? '') }"
                  >{{ initials(payment.customer_name ?? '') }}</div>
                  <span class="font-medium text-gray-900 truncate max-w-[140px]">{{ payment.customer_name ?? '—' }}</span>
                </div>
              </td>

              <!-- Description -->
              <td class="px-5 py-3.5 text-gray-600 hidden sm:table-cell max-w-[180px] truncate">{{ payment.description }}</td>

              <!-- Date -->
              <td class="px-5 py-3.5 hidden md:table-cell">
                <p class="text-gray-700 text-sm">{{ formatDate(payment.paid_at ?? payment.created_at) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ relativeTime(payment.paid_at ?? payment.created_at) }}</p>
              </td>

              <!-- Amount -->
              <td class="px-5 py-3.5 text-right">
                <span class="font-bold text-gray-900">{{ formatAmount(payment.amount_cents) }}</span>
              </td>

              <!-- Status -->
              <td class="px-5 py-3.5">
                <span
                  class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                  :class="statusClass(payment.status)"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(payment.status)"></span>
                  {{ payment.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="payments.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
        <span>Showing {{ payments.from }}–{{ payments.to }} of {{ payments.total }}</span>
        <div class="flex gap-1">
          <Link
            v-for="link in payments.links"
            :key="link.label"
            :href="link.url ?? '#'"
            :class="[
              'px-3 py-1.5 rounded-lg border text-sm transition-colors',
              link.active
                ? 'bg-indigo-600 border-indigo-600 text-white font-medium'
                : link.url
                  ? 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                  : 'border-gray-100 bg-white text-gray-300 cursor-not-allowed pointer-events-none',
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- ── Invoice Detail Drawer ── -->
    <Teleport to="body">
      <Transition name="drawer">
        <div v-if="drawerPayment" class="fixed inset-0 z-50 flex justify-end" @click.self="closeDrawer">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-[2px]" @click="closeDrawer" />

          <div class="relative w-full max-w-md bg-white shadow-2xl flex flex-col h-full overflow-hidden">
            <!-- Drawer header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <div class="flex items-center gap-3">
                <span class="font-mono text-sm font-bold text-gray-800 bg-gray-100 px-2.5 py-1 rounded">{{ drawerPayment.short_ref }}</span>
                <span v-if="drawerPayment.type === 'boarding'" class="text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-600 font-semibold">boarding</span>
                <span
                  class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                  :class="statusClass(drawerPayment.status)"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(drawerPayment.status)"></span>
                  {{ drawerPayment.status }}
                </span>
              </div>
              <button @click="closeDrawer" class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Drawer body -->
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

              <!-- Customer info -->
              <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Customer</p>
                <div class="flex items-center gap-3">
                  <div
                    class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0"
                    :style="{ background: avatarColor(drawerPayment.customer_name ?? '') }"
                  >{{ initials(drawerPayment.customer_name ?? '') }}</div>
                  <div>
                    <p class="font-semibold text-gray-900">{{ drawerPayment.customer_name ?? '—' }}</p>
                    <p v-if="drawerPayment.customer_email" class="text-xs text-gray-500 mt-0.5">{{ drawerPayment.customer_email }}</p>
                  </div>
                </div>
              </div>

              <!-- Dogs -->
              <div v-if="drawerPayment.dogs?.length">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Dogs</p>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="dog in drawerPayment.dogs"
                    :key="dog"
                    class="text-xs px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-medium"
                  >🐾 {{ dog }}</span>
                </div>
              </div>

              <!-- Dates -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Date Paid</p>
                  <p class="text-sm text-gray-700">{{ formatDate(drawerPayment.paid_at ?? drawerPayment.created_at) }}</p>
                </div>
                <div v-if="drawerPayment.refunded_at">
                  <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Date Refunded</p>
                  <p class="text-sm text-gray-700">{{ formatDate(drawerPayment.refunded_at) }}</p>
                </div>
              </div>

              <!-- Line items -->
              <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Line Items</p>
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                  <div v-if="drawerPayment.line_items?.length" class="divide-y divide-gray-100">
                    <div
                      v-for="item in drawerPayment.line_items"
                      :key="item.id"
                      class="flex items-center justify-between px-4 py-2.5 text-sm"
                    >
                      <div>
                        <p class="font-medium text-gray-800">{{ item.description }}</p>
                        <p v-if="item.quantity > 1" class="text-xs text-gray-400 mt-0.5">{{ item.quantity }} × {{ formatAmount(item.unit_price_cents) }}</p>
                      </div>
                      <p class="font-semibold text-gray-900">{{ formatAmount(item.total_cents) }}</p>
                    </div>
                  </div>
                  <div v-else class="px-4 py-3 text-sm text-gray-400">
                    {{ drawerPayment.description }}
                  </div>

                  <!-- Totals -->
                  <div class="bg-gray-50 border-t border-gray-100 divide-y divide-gray-100">
                    <div v-if="drawerPayment.subtotal_cents != null" class="flex justify-between px-4 py-2 text-sm">
                      <p class="text-gray-500">Subtotal</p>
                      <p class="text-gray-700">{{ formatAmount(drawerPayment.subtotal_cents) }}</p>
                    </div>
                    <div v-if="drawerPayment.tax_cents" class="flex justify-between px-4 py-2 text-sm">
                      <p class="text-gray-500">Tax</p>
                      <p class="text-gray-700">{{ formatAmount(drawerPayment.tax_cents) }}</p>
                    </div>
                    <div class="flex justify-between px-4 py-2.5">
                      <p class="font-semibold text-gray-800">Total</p>
                      <p class="font-bold text-gray-900">{{ formatAmount(drawerPayment.amount_cents) }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Stripe PI reference -->
              <div v-if="drawerPayment.stripe_pi_id">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Payment Reference</p>
                <p class="font-mono text-xs text-gray-500 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200 break-all">{{ drawerPayment.stripe_pi_id }}</p>
              </div>
            </div>

            <!-- Drawer footer actions -->
            <div class="border-t border-gray-100 px-6 py-4 flex items-center gap-3">
              <a
                v-if="drawerPayment.status === 'paid' && drawerPayment.stripe_pi_id"
                :href="route('admin.orders.receipt', { order: drawerPayment.order_id })"
                target="_blank"
                class="flex-1 text-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              >View Receipt ↗</a>
              <button
                v-if="drawerPayment.status === 'paid' || drawerPayment.status === 'partially_refunded'"
                @click="openRefundModal(drawerPayment)"
                class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors"
              >Issue Refund</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Refund Modal ── -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="refundTarget"
          class="fixed inset-0 z-[60] flex items-center justify-center p-4"
          @click.self="closeRefundModal"
        >
          <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeRefundModal" />
          <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

            <!-- Modal header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h3 class="font-semibold text-gray-900">Issue Refund</h3>
              <button @click="closeRefundModal" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Order summary -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
              <div>
                <p class="font-semibold text-gray-800">{{ refundTarget.customer_name ?? 'Customer' }}</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ refundTarget.description }} · <span class="font-mono">{{ refundTarget.short_ref }}</span></p>
              </div>
              <p class="text-lg font-bold text-gray-900">{{ formatAmount(refundTarget.amount_cents) }}</p>
            </div>

            <div class="px-6 py-5 space-y-5">

              <!-- Refund type selection -->
              <div class="space-y-2">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Refund Type</label>
                <div class="space-y-2">
                  <label class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                    :class="refundType === 'full' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                  >
                    <input type="radio" v-model="refundType" value="full" class="accent-indigo-600" />
                    <div class="flex-1">
                      <p class="font-medium text-gray-900">Full refund</p>
                      <p class="text-xs text-gray-500">Reverse entire charge · remove all remaining credits</p>
                    </div>
                    <p class="font-bold text-gray-900">{{ formatAmount(refundTarget.amount_cents) }}</p>
                  </label>
                  <label class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                    :class="refundType === 'partial' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                  >
                    <input type="radio" v-model="refundType" value="partial" class="accent-indigo-600" />
                    <div class="flex-1">
                      <p class="font-medium text-gray-900">Partial refund</p>
                      <p class="text-xs text-gray-500">Specify amount or select line items · credits unchanged</p>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Partial refund options -->
              <div v-if="refundType === 'partial'" class="space-y-4">

                <!-- Sub-mode tabs -->
                <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                  <button
                    @click="partialMode = 'amount'"
                    class="flex-1 py-2 text-sm font-medium transition-colors"
                    :class="partialMode === 'amount' ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Custom Amount</button>
                  <button
                    v-if="refundTarget.line_items?.length"
                    @click="partialMode = 'items'"
                    class="flex-1 py-2 text-sm font-medium transition-colors border-l border-gray-200"
                    :class="partialMode === 'items' ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Select Line Items</button>
                </div>

                <!-- Custom amount input -->
                <div v-if="partialMode === 'amount'">
                  <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1.5">Refund Amount</label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">$</span>
                    <input
                      v-model="customAmountDollars"
                      type="number"
                      step="0.01"
                      :min="0.01"
                      :max="refundTarget.amount_cents / 100"
                      placeholder="0.00"
                      class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    />
                  </div>
                  <p class="text-xs text-gray-400 mt-1">Max: {{ formatAmount(refundTarget.amount_cents) }}</p>
                </div>

                <!-- Line items checklist -->
                <div v-if="partialMode === 'items' && refundTarget.line_items?.length">
                  <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1.5">Select Items to Refund</label>
                  <div class="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100">
                    <label
                      v-for="item in refundTarget.line_items"
                      :key="item.id"
                      class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
                    >
                      <input
                        type="checkbox"
                        :value="item.id"
                        v-model="selectedLineItems"
                        class="accent-indigo-600 w-4 h-4"
                      />
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ item.description }}</p>
                        <p v-if="item.quantity > 1" class="text-xs text-gray-400">{{ item.quantity }} × {{ formatAmount(item.unit_price_cents) }}</p>
                      </div>
                      <p class="text-sm font-semibold text-gray-900">{{ formatAmount(item.total_cents) }}</p>
                    </label>
                  </div>
                </div>

                <!-- Running total -->
                <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3 border border-gray-200">
                  <p class="text-sm font-medium text-gray-700">Refund total</p>
                  <p class="text-lg font-bold text-gray-900">{{ formatAmount(partialAmountCents) }}</p>
                </div>

                <!-- Credits warning -->
                <div class="flex gap-2.5 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
                  <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                  </svg>
                  <p class="text-xs text-amber-700">Credits are <strong>not</strong> automatically removed for partial refunds. Adjust the customer's balance manually if needed.</p>
                </div>
              </div>

              <!-- Full refund warning -->
              <div v-if="refundType === 'full'" class="flex gap-2.5 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <p class="text-xs text-red-700">This will reverse the full charge on Stripe and remove <strong>all remaining credits</strong> from the customer's balance.</p>
              </div>
            </div>

            <!-- Modal footer -->
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
              <button
                @click="closeRefundModal"
                class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              >Cancel</button>
              <button
                @click="confirmRefund"
                :disabled="refunding || !canConfirmRefund"
                class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors disabled:opacity-40"
                :class="refundType === 'full' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-gray-900 text-white hover:bg-gray-800'"
              >
                <span v-if="refunding">Processing…</span>
                <span v-else-if="refundType === 'full'">Confirm Full Refund</span>
                <span v-else>Confirm Refund — {{ formatAmount(partialAmountCents) }}</span>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

type LineItem = {
  id: string;
  description: string;
  quantity: number;
  unit_price_cents: number;
  total_cents: number;
};

type Payment = {
  id: string;
  order_id: string;
  short_ref: string;
  type: string;
  payment_type: string;
  stripe_pi_id: string | null;
  customer_name: string | null;
  customer_email: string | null;
  description: string;
  amount_cents: number;
  subtotal_cents: number | null;
  tax_cents: number | null;
  status: string;
  paid_at: string | null;
  created_at: string;
  refunded_at: string | null;
  dogs: string[];
  line_items: LineItem[];
};

type PaginatedPayments = {
  data: Payment[];
  from: number;
  to: number;
  total: number;
  last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
};

type Stats = {
  total_paid_cents: number;
  total_refunded_cents: number;
  authorized_count: number;
  paid_count: number;
};

const props = defineProps<{
  payments: PaginatedPayments;
  filters: { status: string };
  stats: Stats;
}>();

const tabs = [
  { label: 'All', value: '' },
  { label: 'Paid', value: 'paid' },
  { label: 'Authorized', value: 'authorized' },
  { label: 'Refunded', value: 'refunded' },
];

const currentStatus = computed(() => props.filters.status ?? '');

function setFilter(status: string) {
  router.get(route('admin.payments.index'), { status: status || undefined }, { preserveState: true, replace: true });
}

function formatAmount(cents: number): string {
  return '$' + (cents / 100).toFixed(2);
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function relativeTime(iso: string | null): string {
  if (!iso) return '';
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  const h = Math.floor(m / 60);
  const d = Math.floor(h / 24);
  if (m < 2) return 'just now';
  if (m < 60) return `${m}m ago`;
  if (h < 24) return `${h}h ago`;
  if (d < 7) return `${d}d ago`;
  return `${d} days ago`;
}

function statusClass(status: string): string {
  const map: Record<string, string> = {
    paid: 'bg-emerald-50 text-emerald-700',
    refunded: 'bg-gray-100 text-gray-500',
    partially_refunded: 'bg-amber-50 text-amber-700',
    authorized: 'bg-amber-50 text-amber-700',
    pending: 'bg-gray-100 text-gray-500',
  };
  return map[status] ?? 'bg-gray-100 text-gray-500';
}

function statusDotClass(status: string): string {
  const map: Record<string, string> = {
    paid: 'bg-emerald-500',
    refunded: 'bg-gray-400',
    partially_refunded: 'bg-amber-500',
    authorized: 'bg-amber-500',
    pending: 'bg-gray-400',
  };
  return map[status] ?? 'bg-gray-400';
}

// Avatar helpers
const AVATAR_COLORS = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6'];
function avatarColor(name: string): string {
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}
function initials(name: string): string {
  if (!name) return '?';
  const parts = name.trim().split(' ');
  return parts.length >= 2
    ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    : name[0].toUpperCase();
}

// ── Drawer ──
const drawerPayment = ref<Payment | null>(null);
function openDrawer(payment: Payment) { drawerPayment.value = payment; }
function closeDrawer() { drawerPayment.value = null; }

// ── Refund Modal ──
const refundTarget = ref<Payment | null>(null);
const refunding = ref(false);
const refundType = ref<'full' | 'partial'>('full');
const partialMode = ref<'amount' | 'items'>('amount');
const customAmountDollars = ref<string>('');
const selectedLineItems = ref<string[]>([]);

function openRefundModal(payment: Payment) {
  refundTarget.value = payment;
  refundType.value = 'full';
  partialMode.value = 'amount';
  customAmountDollars.value = '';
  selectedLineItems.value = [];
}

function closeRefundModal() {
  if (!refunding.value) refundTarget.value = null;
}

const partialAmountCents = computed((): number => {
  if (!refundTarget.value) return 0;
  if (partialMode.value === 'items') {
    return (refundTarget.value.line_items ?? [])
      .filter(li => selectedLineItems.value.includes(li.id))
      .reduce((sum, li) => sum + li.total_cents, 0);
  }
  const dollars = parseFloat(customAmountDollars.value);
  if (isNaN(dollars) || dollars <= 0) return 0;
  return Math.round(dollars * 100);
});

const canConfirmRefund = computed((): boolean => {
  if (refundType.value === 'full') return true;
  return partialAmountCents.value > 0;
});

function confirmRefund() {
  if (!refundTarget.value) return;
  refunding.value = true;

  const data: Record<string, unknown> = { refund_type: refundType.value };
  if (refundType.value === 'partial') {
    if (partialMode.value === 'items') {
      data.line_item_ids = selectedLineItems.value;
    } else {
      data.amount_cents = partialAmountCents.value;
    }
  }

  useForm(data).post(route('admin.payments.refund', { order: refundTarget.value.order_id }), {
    onFinish: () => {
      refunding.value = false;
      refundTarget.value = null;
      drawerPayment.value = null;
    },
  });
}
</script>

<style scoped>
/* Drawer slide-in from right */
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-enter-active > div:last-child,
.drawer-leave-active > div:last-child {
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.drawer-enter-from > div:last-child,
.drawer-leave-to > div:last-child {
  transform: translateX(100%);
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}

/* Modal fade */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
