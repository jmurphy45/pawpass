<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Back link -->
      <Link :href="route('admin.payments.index')" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Back to Payments
      </Link>

      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-gray-900">New Invoice</h1>
        <p class="text-sm text-gray-500 mt-0.5">Create a new invoice for a customer.</p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-6">

        <!-- Customer search -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Client <span class="text-red-500">*</span>
          </label>
          <div class="relative" ref="customerFieldRef">
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z" />
              </svg>
              <input
                v-if="!selectedCustomer"
                v-model="customerSearch"
                @input="onCustomerSearch"
                @focus="showDropdown = true"
                @keydown.escape="showDropdown = false"
                placeholder="Search clients…"
                autocomplete="off"
                class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                :class="{ 'border-red-300': form.errors.customer_id }"
              />
              <div
                v-else
                class="flex items-center justify-between pl-9 pr-3 py-2.5 border border-indigo-300 bg-indigo-50 rounded-xl"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0" :style="{ background: avatarColor(selectedCustomer.name) }">
                    {{ initials(selectedCustomer.name) }}
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ selectedCustomer.name }}</p>
                    <p v-if="selectedCustomer.email" class="text-xs text-gray-500">{{ selectedCustomer.email }}</p>
                  </div>
                </div>
                <button @click="clearCustomer" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Dropdown -->
            <div
              v-if="showDropdown && !selectedCustomer && customerResults.length"
              class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
            >
              <button
                v-for="c in customerResults"
                :key="c.id"
                @click="selectCustomer(c)"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-indigo-50 transition-colors"
              >
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0" :style="{ background: avatarColor(c.name) }">
                  {{ initials(c.name) }}
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ c.name }}</p>
                  <p v-if="c.email" class="text-xs text-gray-400 truncate">{{ c.email }}</p>
                </div>
              </button>
            </div>

            <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-600">{{ form.errors.customer_id }}</p>
          </div>
        </div>

        <!-- Line Items -->
        <div>
          <h3 class="text-sm font-medium text-gray-700 mb-3">Line Items</h3>

          <!-- Add row -->
          <div class="flex gap-2 mb-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
            <select
              v-model="draft.catalogId"
              @change="onCatalogChange"
              class="flex-shrink-0 w-44 px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-gray-600"
            >
              <option value="">Select a service…</option>
              <optgroup v-if="packages.length" label="Packages">
                <option v-for="p in packages" :key="p.id" :value="'pkg:' + p.id">
                  {{ p.name }}
                </option>
              </optgroup>
              <optgroup v-if="addonTypes.length" label="Add-ons">
                <option v-for="a in addonTypes" :key="a.id" :value="'addon:' + a.id">
                  {{ a.name }}
                </option>
              </optgroup>
            </select>
            <input
              v-model="draft.description"
              placeholder="Description"
              class="flex-1 min-w-0 px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <input
              v-model.number="draft.quantity"
              type="number"
              min="1"
              class="w-14 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-center"
            />
            <div class="relative w-28">
              <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input
                v-model="draft.unitPriceDollars"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                class="w-full pl-6 pr-2 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-right"
              />
            </div>
            <button
              @click="addLineItem"
              :disabled="!canAddDraft"
              class="flex items-center gap-1 px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              Add
            </button>
          </div>

          <!-- Items table -->
          <div v-if="form.line_items.length" class="border border-gray-200 rounded-xl overflow-hidden">
            <!-- Table header -->
            <div class="grid grid-cols-12 gap-2 px-4 py-2.5 bg-gray-50 border-b border-gray-200">
              <div class="col-span-5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</div>
              <div class="col-span-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-center">Qty</div>
              <div class="col-span-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Unit Price</div>
              <div class="col-span-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Total</div>
              <div class="col-span-1"></div>
            </div>

            <!-- Item rows -->
            <div
              v-for="(item, idx) in form.line_items"
              :key="idx"
              class="grid grid-cols-12 gap-2 px-4 py-3 items-center border-b border-gray-100 last:border-b-0"
            >
              <div class="col-span-5 text-sm text-gray-900">{{ item.description }}</div>
              <div class="col-span-2 text-sm text-gray-600 text-center">{{ item.quantity }}</div>
              <div class="col-span-2 text-sm text-gray-600 text-right">{{ formatDollars(item.unit_price_cents / 100) }}</div>
              <div class="col-span-2 text-sm font-medium text-gray-900 text-right">{{ formatDollars(item.quantity * item.unit_price_cents / 100) }}</div>
              <div class="col-span-1 flex justify-end">
                <button @click="removeLineItem(idx)" class="text-gray-300 hover:text-red-400 transition-colors p-1 rounded">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Summary -->
            <div class="px-4 py-4 bg-gray-50 border-t border-gray-200 space-y-2">
              <div class="flex justify-between text-sm text-gray-600">
                <span>Subtotal</span>
                <span>{{ formatDollars(subtotalCents / 100) }}</span>
              </div>
              <div class="flex justify-between text-base font-bold text-gray-900 pt-2 border-t border-gray-200">
                <span>Total</span>
                <span>{{ formatDollars(subtotalCents / 100) }}</span>
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else class="border-2 border-dashed border-gray-200 rounded-xl py-10 text-center">
            <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.085 1.586m5.336-.001c.376.023.75.05 1.124.08C18.09 4.01 19 4.973 19 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125Z" />
            </svg>
            <p class="text-sm text-gray-400">No items added yet. Use the row above to add line items.</p>
          </div>

          <p v-if="form.errors.line_items" class="mt-1.5 text-xs text-red-600">{{ form.errors.line_items }}</p>
        </div>

        <!-- Due Date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Due Date</label>
          <input
            v-model="form.due_date"
            type="date"
            class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-full sm:w-48"
          />
        </div>

      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 pb-8">
        <button
          @click="submit"
          :disabled="form.processing || !canSubmit"
          class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-sm"
        >
          {{ form.processing ? 'Creating…' : 'Create Invoice' }}
        </button>
        <Link :href="route('admin.payments.index')" class="px-6 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
          Cancel
        </Link>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

type Customer = { id: string; name: string; email: string | null };
type CatalogPackage = { id: string; name: string; price: string };
type CatalogAddon = { id: string; name: string; price_cents: number };
type LineItem = { description: string; quantity: number; unit_price_cents: number; item_type: string; item_id: string };

const props = defineProps<{
  packages: CatalogPackage[];
  addonTypes: CatalogAddon[];
}>();

// Customer search state
const customerSearch = ref('');
const customerResults = ref<Customer[]>([]);
const selectedCustomer = ref<Customer | null>(null);
const showDropdown = ref(false);
const customerFieldRef = ref<HTMLElement | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let searchAbort: AbortController | null = null;

// Draft (add-row) state
const draft = ref({ catalogId: '', description: '', quantity: 1, unitPriceDollars: '', itemType: '', itemId: '' });

// Main form
const form = useForm({
  customer_id: '',
  due_date: '',
  line_items: [] as LineItem[],
});

const subtotalCents = computed(() =>
  form.line_items.reduce((sum, li) => sum + li.quantity * li.unit_price_cents, 0)
);

const canAddDraft = computed(() =>
  draft.value.description.trim() !== '' &&
  draft.value.quantity >= 1 &&
  draft.value.unitPriceDollars !== '' &&
  parseFloat(draft.value.unitPriceDollars) >= 0
);

const canSubmit = computed(() =>
  form.customer_id !== '' &&
  form.line_items.length > 0
);

// Customer search — debounced 300ms, cancels in-flight requests
function onCustomerSearch() {
  if (searchTimer) clearTimeout(searchTimer);
  if (searchAbort) { searchAbort.abort(); searchAbort = null; }
  const q = customerSearch.value.trim();
  if (!q) { customerResults.value = []; showDropdown.value = false; return; }
  searchTimer = setTimeout(fetchCustomers, 300);
}

async function fetchCustomers() {
  const q = customerSearch.value.trim();
  if (!q) return;
  searchAbort = new AbortController();
  try {
    const res = await fetch(`/admin/customers/search?search=${encodeURIComponent(q)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      signal: searchAbort.signal,
    });
    if (res.ok) {
      const json = await res.json();
      customerResults.value = json.data ?? [];
      showDropdown.value = customerResults.value.length > 0;
    }
  } catch (e) {
    if ((e as Error).name !== 'AbortError') customerResults.value = [];
  }
}

function selectCustomer(c: Customer) {
  selectedCustomer.value = c;
  form.customer_id = c.id;
  customerSearch.value = '';
  customerResults.value = [];
  showDropdown.value = false;
}

function clearCustomer() {
  selectedCustomer.value = null;
  form.customer_id = '';
  customerSearch.value = '';
  customerResults.value = [];
}

// Close dropdown on outside click
function onDocumentClick(e: MouseEvent) {
  if (customerFieldRef.value && !customerFieldRef.value.contains(e.target as Node)) {
    showDropdown.value = false;
  }
}
onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick);
  if (searchAbort) searchAbort.abort();
});

// Catalog picker
function onCatalogChange() {
  const val = draft.value.catalogId;
  if (!val) { draft.value.itemType = ''; draft.value.itemId = ''; return; }

  if (val.startsWith('pkg:')) {
    const id = val.slice(4);
    const pkg = props.packages.find(p => p.id === id);
    if (pkg) {
      draft.value.description = pkg.name;
      draft.value.unitPriceDollars = Number(pkg.price).toFixed(2);
      draft.value.itemType = 'package';
      draft.value.itemId = pkg.id;
    }
  } else if (val.startsWith('addon:')) {
    const id = val.slice(6);
    const addon = props.addonTypes.find(a => a.id === id);
    if (addon) {
      draft.value.description = addon.name;
      draft.value.unitPriceDollars = (addon.price_cents / 100).toFixed(2);
      draft.value.itemType = 'service';
      draft.value.itemId = addon.id;
    }
  }
}

// Line item management
function addLineItem() {
  if (!canAddDraft.value) return;
  form.line_items.push({
    description: draft.value.description.trim(),
    quantity: draft.value.quantity,
    unit_price_cents: Math.round(parseFloat(draft.value.unitPriceDollars) * 100),
    item_type: draft.value.itemType,
    item_id: draft.value.itemId,
  });
  draft.value = { catalogId: '', description: '', quantity: 1, unitPriceDollars: '', itemType: '', itemId: '' };
}

function removeLineItem(idx: number) {
  form.line_items.splice(idx, 1);
}

// Submit
function submit() {
  if (!canSubmit.value) return;
  form.post(route('admin.invoices.store'));
}

// Helpers
function formatDollars(amount: number): string {
  return '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

const AVATAR_COLORS = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6'];
function avatarColor(name: string): string {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xffff;
  return AVATAR_COLORS[h % AVATAR_COLORS.length];
}
function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}
</script>
