<template>
  <TransitionRoot :show="open" as="template" @after-leave="onAfterLeave" appear>
    <Dialog class="relative z-50" @close="emit('update:open', false)">
      <TransitionChild
        as="template"
        enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
        leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-gray-900/60 transition-opacity" />
      </TransitionChild>

      <div class="fixed inset-0 z-10 w-screen overflow-y-auto p-4 sm:p-6 md:p-20">
        <TransitionChild
          as="template"
          enter="ease-out duration-200" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100"
          leave="ease-in duration-150" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95"
        >
          <DialogPanel class="mx-auto max-w-3xl transform divide-y divide-white/10 overflow-hidden rounded-xl bg-gray-900 shadow-2xl ring-1 ring-white/10 transition-all">
            <Combobox v-slot="{ activeOption }" @update:modelValue="onSelect">
              <!-- Search input -->
              <div class="grid grid-cols-1">
                <ComboboxInput
                  ref="inputRef"
                  class="col-start-1 row-start-1 h-12 w-full bg-transparent pl-11 pr-4 text-base text-white outline-none placeholder:text-gray-500 sm:text-sm"
                  placeholder="Search customers and dogs…"
                  @change="onInputChange"
                  @blur.prevent
                />
                <MagnifyingGlassIcon
                  class="pointer-events-none col-start-1 row-start-1 ml-4 size-5 self-center text-gray-500"
                  aria-hidden="true"
                />
              </div>

              <!-- Results -->
              <ComboboxOptions
                v-if="hasResults || loading"
                class="flex transform-gpu divide-x divide-white/10"
                as="div"
                static
                hold
              >
                <!-- List panel -->
                <div :class="['max-h-96 min-w-0 flex-auto scroll-py-4 overflow-y-auto px-6 py-4', activeOption && 'sm:h-96']">
                  <!-- Loading -->
                  <div v-if="loading && !hasResults" class="py-8 text-center text-sm text-gray-500">
                    Searching…
                  </div>

                  <!-- Customers group -->
                  <div v-if="results.customers.length > 0">
                    <h2 class="mb-2 mt-1 flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                      <UsersIcon class="size-3.5" aria-hidden="true" />
                      Customers
                    </h2>
                    <div class="-mx-2 mb-3 text-sm text-gray-300">
                      <ComboboxOption
                        v-for="customer in results.customers"
                        :key="`c:${customer.id}`"
                        :value="{ type: 'customer', ...customer }"
                        as="template"
                        v-slot="{ active }"
                      >
                        <div :class="['group flex cursor-default select-none items-center rounded-md p-2', active && 'bg-white/5 text-white outline-none']">
                          <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">
                            {{ customer.name[0]?.toUpperCase() }}
                          </div>
                          <span class="ml-3 flex-auto truncate">{{ customer.name }}</span>
                          <span class="ml-2 shrink-0 text-[10px] font-medium text-indigo-400 opacity-70">Customer</span>
                          <ChevronRightIcon v-if="active" class="ml-2 size-4 shrink-0 text-gray-500" aria-hidden="true" />
                        </div>
                      </ComboboxOption>
                    </div>
                  </div>

                  <!-- Dogs group -->
                  <div v-if="results.dogs.length > 0">
                    <h2 class="mb-2 mt-1 flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                      <svg class="size-3.5" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <ellipse cx="10" cy="7" rx="2.5" ry="3" fill="currentColor" opacity="0.8"/>
                        <ellipse cx="18" cy="7" rx="2.5" ry="3" fill="currentColor" opacity="0.8"/>
                        <ellipse cx="6" cy="13" rx="2.2" ry="3" transform="rotate(-20 6 13)" fill="currentColor" opacity="0.8"/>
                        <ellipse cx="22" cy="13" rx="2.2" ry="3" transform="rotate(20 22 13)" fill="currentColor" opacity="0.8"/>
                        <ellipse cx="14" cy="18" rx="6" ry="5" fill="currentColor"/>
                      </svg>
                      Dogs
                    </h2>
                    <div class="-mx-2 text-sm text-gray-300">
                      <ComboboxOption
                        v-for="dog in results.dogs"
                        :key="`d:${dog.id}`"
                        :value="{ type: 'dog', ...dog }"
                        as="template"
                        v-slot="{ active }"
                      >
                        <div :class="['group flex cursor-default select-none items-center rounded-md p-2', active && 'bg-white/5 text-white outline-none']">
                          <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-600 text-xs font-semibold text-white">
                            {{ dog.name[0]?.toUpperCase() }}
                          </div>
                          <div class="ml-3 flex-auto min-w-0">
                            <span class="block truncate">{{ dog.name }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ dog.customer_name }}</span>
                          </div>
                          <span class="ml-2 shrink-0 text-[10px] font-medium text-amber-400 opacity-70">Dog</span>
                          <ChevronRightIcon v-if="active" class="ml-2 size-4 shrink-0 text-gray-500" aria-hidden="true" />
                        </div>
                      </ComboboxOption>
                    </div>
                  </div>
                </div>

                <!-- Preview panel -->
                <div v-if="activeOption" class="hidden h-96 w-1/2 flex-none flex-col divide-y divide-white/10 overflow-y-auto sm:flex">
                  <!-- Customer preview -->
                  <template v-if="activeOption.type === 'customer'">
                    <div class="flex-none p-6 text-center">
                      <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-indigo-600 text-2xl font-bold text-white">
                        {{ activeOption.name[0]?.toUpperCase() }}
                      </div>
                      <h2 class="mt-3 font-semibold text-white">{{ activeOption.name }}</h2>
                      <p class="text-sm text-gray-400">Customer</p>
                    </div>
                    <div class="flex flex-auto flex-col justify-between p-6">
                      <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm text-gray-300">
                        <template v-if="activeOption.email">
                          <dt class="col-end-1 font-semibold text-white">Email</dt>
                          <dd class="truncate">
                            <a :href="`mailto:${activeOption.email}`" class="text-indigo-400 underline">{{ activeOption.email }}</a>
                          </dd>
                        </template>
                        <template v-if="activeOption.phone">
                          <dt class="col-end-1 font-semibold text-white">Phone</dt>
                          <dd>{{ activeOption.phone }}</dd>
                        </template>
                      </dl>
                      <button
                        type="button"
                        @click="navigateTo(activeOption)"
                        class="mt-6 w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                      >View Customer</button>
                    </div>
                  </template>

                  <!-- Dog preview -->
                  <template v-if="activeOption.type === 'dog'">
                    <div class="flex-none p-6 text-center">
                      <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-600 text-2xl font-bold text-white">
                        {{ activeOption.name[0]?.toUpperCase() }}
                      </div>
                      <h2 class="mt-3 font-semibold text-white">{{ activeOption.name }}</h2>
                      <p class="text-sm text-gray-400">{{ activeOption.customer_name }}</p>
                    </div>
                    <div class="flex flex-auto flex-col justify-between p-6">
                      <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm text-gray-300">
                        <template v-if="activeOption.credit_balance !== undefined">
                          <dt class="col-end-1 font-semibold text-white">Credits</dt>
                          <dd>
                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', activeOption.credit_balance > 0 ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300']">
                              {{ activeOption.credit_balance }} credit{{ activeOption.credit_balance !== 1 ? 's' : '' }}
                            </span>
                          </dd>
                        </template>
                      </dl>
                      <button
                        type="button"
                        @click="navigateTo(activeOption)"
                        class="mt-6 w-full rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                      >View Dog</button>
                    </div>
                  </template>
                </div>
              </ComboboxOptions>

              <!-- Empty state -->
              <div v-if="query !== '' && !loading && !hasResults" class="px-6 py-14 text-center text-sm sm:px-14">
                <UsersIcon class="mx-auto size-6 text-gray-500" aria-hidden="true" />
                <p class="mt-4 font-semibold text-white">No results found</p>
                <p class="mt-2 text-gray-400">Nothing matched "{{ query }}". Try a different name or email.</p>
              </div>
            </Combobox>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { ChevronRightIcon, UsersIcon } from '@heroicons/vue/24/outline';
import {
  Combobox,
  ComboboxInput,
  ComboboxOptions,
  ComboboxOption,
  Dialog,
  DialogPanel,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue';

interface CustomerResult {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
}

interface DogResult {
  id: string;
  name: string;
  customer_name: string;
  credit_balance: number;
}

type SearchResult = ({ type: 'customer' } & CustomerResult) | ({ type: 'dog' } & DogResult);

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const inputRef = ref<{ $el?: HTMLInputElement } | null>(null);
const query = ref('');
const loading = ref(false);
const results = ref<{ customers: CustomerResult[]; dogs: DogResult[] }>({ customers: [], dogs: [] });
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const hasResults = computed(() => results.value.customers.length > 0 || results.value.dogs.length > 0);

watch(() => props.open, (val) => {
  if (val) nextTick(() => (inputRef.value?.$el ?? (inputRef.value as unknown as HTMLInputElement))?.focus());
  if (!val) {
    results.value = { customers: [], dogs: [] };
    query.value = '';
  }
});

function onInputChange(e: Event) {
  query.value = (e.target as HTMLInputElement).value;
  if (debounceTimer) clearTimeout(debounceTimer);
  if (!query.value.trim()) {
    results.value = { customers: [], dogs: [] };
    loading.value = false;
    return;
  }
  loading.value = true;
  debounceTimer = setTimeout(() => doSearch(query.value), 300);
}

async function doSearch(q: string) {
  const params = new URLSearchParams({ search: q });
  try {
    const [custRes, dogRes] = await Promise.all([
      fetch(`/admin/customers/search?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
      fetch(`/admin/dogs/search?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
    ]);
    const [custData, dogData] = await Promise.all([custRes.json(), dogRes.json()]);
    results.value = {
      customers: custData.data ?? [],
      dogs: dogData.data ?? [],
    };
  } catch {
    results.value = { customers: [], dogs: [] };
  } finally {
    loading.value = false;
  }
}

function onSelect(item: SearchResult | null) {
  if (!item) return;
  navigateTo(item);
}

function navigateTo(item: SearchResult) {
  emit('update:open', false);
  if (item.type === 'customer') {
    router.visit(route('admin.customers.show', { customer: item.id }));
  } else {
    router.visit(route('admin.dogs.show', { dog: item.id }));
  }
}

function onAfterLeave() {
  query.value = '';
  results.value = { customers: [], dogs: [] };
}

function onKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault();
    emit('update:open', !props.open);
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
  if (debounceTimer) clearTimeout(debounceTimer);
});
</script>
