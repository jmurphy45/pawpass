<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-4 py-4">
      <div class="max-w-5xl mx-auto flex items-center justify-between">
        <a href="/" class="text-xl font-bold text-indigo-600">PawPass</a>
        <a href="/register" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">List your business →</a>
      </div>
    </header>

    <!-- Hero + Search -->
    <div class="bg-white border-b border-gray-100 py-12 px-4">
      <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Find a Dog Daycare Near You</h1>
        <p class="text-gray-500 mb-8">Search local doggy daycares and boarding kennels on PawPass.</p>

        <!-- Search mode toggle -->
        <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 mb-5">
          <button
            type="button"
            @click="searchMode = 'city'"
            class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
            :class="searchMode === 'city' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
          >City &amp; State</button>
          <button
            type="button"
            @click="searchMode = 'zip'"
            class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
            :class="searchMode === 'zip' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
          >ZIP Code</button>
        </div>

        <form @submit.prevent="doSearch" class="space-y-3">
          <div v-if="searchMode === 'city'" class="flex gap-2 justify-center">
            <input
              v-model="form.city"
              type="text"
              placeholder="City (e.g. Austin)"
              class="w-56 rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
            />
            <input
              v-model="form.state"
              type="text"
              placeholder="State (e.g. TX)"
              maxlength="2"
              class="w-24 rounded-lg border border-gray-300 px-3 py-2.5 text-sm uppercase"
            />
          </div>
          <div v-else class="flex justify-center">
            <input
              v-model="form.zip"
              type="text"
              placeholder="ZIP Code (e.g. 78701)"
              class="w-48 rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
            />
          </div>

          <!-- Optional boarding dates -->
          <div class="flex gap-2 items-center justify-center">
            <span class="text-xs text-gray-400">Check boarding availability:</span>
            <input
              v-model="form.date_from"
              type="date"
              class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
            />
            <span class="text-xs text-gray-400">to</span>
            <input
              v-model="form.date_to"
              type="date"
              class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
            />
          </div>

          <div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition-colors">
              Search
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Results -->
    <main class="max-w-5xl mx-auto px-4 py-10">
      <template v-if="props.searched">
        <p v-if="props.results.length === 0" class="text-center text-gray-500 py-12">
          No daycares found for your search. Try a different city or ZIP code.
        </p>

        <div v-else>
          <p class="text-sm text-gray-500 mb-5">{{ props.results.length }} result{{ props.results.length !== 1 ? 's' : '' }} found</p>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="daycare in props.results"
              :key="daycare.slug"
              class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-3 hover:border-indigo-300 transition-colors"
            >
              <!-- Logo / placeholder -->
              <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0 overflow-hidden">
                  <img v-if="daycare.logo_url" :src="daycare.logo_url" :alt="daycare.name" class="w-full h-full object-contain p-1" />
                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 10.5a6 6 0 1111.086-3.666M9 7.5V3m6 4.5V3M9 12l2 2 4-4" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="font-semibold text-gray-900 text-sm leading-tight">{{ daycare.name }}</h3>
                  <p class="text-xs text-gray-500 mt-0.5">{{ daycare.city }}, {{ daycare.state }}</p>
                </div>
              </div>

              <!-- Badges -->
              <div class="flex flex-wrap gap-1.5">
                <span :class="typeClass(daycare.business_type)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  {{ typeLabel(daycare.business_type) }}
                </span>
                <span v-if="'boarding_available' in daycare" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="daycare.boarding_available ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                  {{ daycare.boarding_available ? 'Boarding Available' : 'Boarding Full' }}
                </span>
                <span v-else-if="daycare.has_boarding" class="bg-blue-50 text-blue-700 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  Boarding
                </span>
              </div>

              <!-- Description -->
              <p v-if="daycare.description" class="text-xs text-gray-600 line-clamp-2">{{ daycare.description }}</p>

              <!-- Phone -->
              <p v-if="daycare.phone" class="text-xs text-gray-500">{{ daycare.phone }}</p>

              <a
                :href="`https://${daycare.slug}.pawpass.com`"
                target="_blank"
                class="mt-auto inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
              >
                View Daycare →
              </a>
            </div>
          </div>
        </div>
      </template>

      <template v-else>
        <p class="text-center text-gray-400 py-16 text-sm">Enter a city, state, or ZIP code above to find daycares near you.</p>
      </template>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

interface DaycareResult {
  name: string;
  slug: string;
  logo_url: string | null;
  business_type: string;
  city: string;
  state: string;
  zip: string;
  phone: string | null;
  description: string | null;
  has_boarding: boolean;
  boarding_available?: boolean;
  [key: string]: unknown;
}

const props = defineProps<{
  results: DaycareResult[];
  search: { city: string; state: string; zip: string; date_from: string; date_to: string };
  searched: boolean;
}>();

const searchMode = ref(props.search.zip ? 'zip' : 'city');

const form = ref({
  city:      props.search.city,
  state:     props.search.state,
  zip:       props.search.zip,
  date_from: props.search.date_from,
  date_to:   props.search.date_to,
});

function doSearch() {
  const params: Record<string, string> = {};

  if (searchMode.value === 'zip') {
    if (form.value.zip) params.zip = form.value.zip;
  } else {
    if (form.value.city)  params.city  = form.value.city;
    if (form.value.state) params.state = form.value.state.toUpperCase();
  }

  if (form.value.date_from) params.date_from = form.value.date_from;
  if (form.value.date_to)   params.date_to   = form.value.date_to;

  router.get('/find-a-daycare', params, { preserveScroll: false });
}

function typeLabel(type: string): string {
  if (type === 'kennel') return 'Boarding Kennel';
  if (type === 'hybrid') return 'Daycare + Boarding';
  return 'Daycare';
}

function typeClass(type: string): string {
  if (type === 'kennel') return 'bg-purple-100 text-purple-700';
  if (type === 'hybrid') return 'bg-amber-100 text-amber-700';
  return 'bg-indigo-100 text-indigo-700';
}
</script>
