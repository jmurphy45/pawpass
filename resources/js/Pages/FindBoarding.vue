<template>
  <Head>
    <title>{{ headTitle }}</title>
    <meta name="description" :content="headDescription" />
    <meta property="og:title" :content="headTitle" />
    <meta property="og:description" :content="headDescription" />
    <!-- JSON-LD injected via onMounted to avoid v-html on script tags -->
  </Head>

  <div class="min-h-screen" style="background: #faf9f6; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;">

    <!-- ═══ HERO ═══ -->
    <section class="relative overflow-hidden" style="background: #1c1a17; min-height: 52vh; display: flex; flex-direction: column;">

      <!-- Grain texture -->
      <div class="absolute inset-0 pointer-events-none opacity-[0.04]"
        style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><filter id=%22n%22><feTurbulence type=%22fractalNoise%22 baseFrequency=%220.75%22 numOctaves=%224%22 stitchTiles=%22stitch%22/></filter><rect width=%22200%22 height=%22200%22 filter=%22url(%23n)%22 opacity=%221%22/></svg>');">
      </div>

      <!-- Ambient glow -->
      <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] pointer-events-none"
        style="background: radial-gradient(ellipse at center top, rgba(124,58,237,0.18) 0%, transparent 65%); filter: blur(60px);">
      </div>

      <!-- Nav -->
      <nav class="relative z-10 flex items-center justify-between px-6 pt-7 pb-0 max-w-5xl mx-auto w-full">
        <a href="/" class="text-white font-bold text-lg tracking-tight hover:opacity-80 transition-opacity">
          PawPass
        </a>
        <a href="/register" class="text-sm font-medium text-white/50 hover:text-white/80 transition-colors">
          List your kennel →
        </a>
      </nav>

      <!-- Hero copy -->
      <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 pt-10 pb-8">
        <span class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.22em] text-violet-400 mb-5">
          <span class="h-px w-6 bg-violet-500/60 rounded-full"></span>
          Boarding Search
          <span class="h-px w-6 bg-violet-500/60 rounded-full"></span>
        </span>

        <h1 class="text-white text-center leading-tight tracking-tight mb-3"
          style="font-size: clamp(2rem, 4.5vw, 3.2rem); max-width: 600px; font-family: 'Lora', Georgia, serif;">
          <template v-if="city && state">
            Dog Boarding in {{ city }}, {{ state }}
          </template>
          <template v-else>
            Find Dog Boarding Near You
          </template>
        </h1>

        <p class="text-white/45 text-center text-sm mb-8" style="max-width: 420px; line-height: 1.65;">
          Check real-time kennel availability for your dates. Only PawPass-powered facilities are listed.
        </p>

        <!-- Search form -->
        <form @submit.prevent="doSearch"
          class="w-full max-w-2xl rounded-2xl p-5"
          style="background: rgba(255,255,255,0.055); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.09);">

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
            <input v-model="form.city" type="text" placeholder="City"
              class="rounded-xl px-4 py-2.5 text-sm text-gray-900 bg-white/90 placeholder-gray-400 outline-none focus:ring-2 focus:ring-violet-400" />
            <select v-model="form.state" class="rounded-xl px-3 py-2.5 text-sm text-gray-900 bg-white/90 outline-none focus:ring-2 focus:ring-violet-400">
              <option value="">State</option>
              <option v-for="s in US_STATES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div>
              <label class="block text-xs text-white/40 mb-1 ml-1">Check-in date</label>
              <input v-model="form.checkin" type="date"
                class="w-full rounded-xl px-4 py-2.5 text-sm text-gray-900 bg-white/90 outline-none focus:ring-2 focus:ring-violet-400" />
            </div>
            <div>
              <label class="block text-xs text-white/40 mb-1 ml-1">Check-out date</label>
              <input v-model="form.checkout" type="date"
                class="w-full rounded-xl px-4 py-2.5 text-sm text-gray-900 bg-white/90 outline-none focus:ring-2 focus:ring-violet-400" />
            </div>
          </div>
          <button type="submit"
            class="w-full rounded-xl py-3 text-sm font-semibold bg-violet-600 text-white hover:bg-violet-500 transition-colors">
            Search Availability
          </button>
        </form>
      </div>
    </section>

    <!-- ═══ RESULTS ═══ -->
    <section class="max-w-4xl mx-auto px-4 py-10">

      <!-- Breadcrumb -->
      <div v-if="city" class="mb-6 text-sm text-gray-500">
        <a href="/find-boarding" class="hover:text-violet-600 transition-colors">All locations</a>
        <span class="mx-2 text-gray-300">/</span>
        <span class="text-gray-700">{{ city }}, {{ state }}</span>
        <span v-if="checkin && checkout" class="ml-2 text-gray-400">
          · {{ formatDate(checkin) }} – {{ formatDate(checkout) }}
        </span>
      </div>

      <!-- No search yet -->
      <div v-if="results.length === 0 && !city" class="text-center py-20">
        <div class="text-5xl mb-4">🏠</div>
        <p class="text-gray-500 text-lg">Enter a city and dates above to find available kennels.</p>
        <a href="/find-a-daycare" class="mt-4 inline-block text-violet-600 text-sm font-medium hover:underline">
          Looking for daycare instead? →
        </a>
      </div>

      <!-- No results in city -->
      <div v-else-if="results.length === 0" class="text-center py-20">
        <div class="text-5xl mb-4">🐾</div>
        <p class="text-gray-500 text-lg">No boarding kennels found in {{ city }}, {{ state }}.</p>
      </div>

      <!-- Results grid -->
      <div v-else class="grid gap-4 sm:grid-cols-2">
        <a v-for="r in results" :key="r.slug"
          :href="`https://${r.slug}.${appDomain}`" target="_blank" rel="noopener"
          class="block rounded-2xl bg-white border shadow-sm p-5 hover:border-violet-200 transition-colors group"
          :class="r.available_units === 0 && checkin ? 'opacity-50' : 'border-gray-100'">

          <div class="flex items-start gap-3 mb-3">
            <img v-if="r.logo_url" :src="r.logo_url" :alt="r.name"
              class="w-12 h-12 rounded-xl object-cover border border-gray-100 flex-shrink-0" />
            <div v-else class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center text-base font-bold text-white"
              style="background: #7c3aed;">
              {{ r.name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-gray-900 leading-tight">{{ r.name }}</div>
              <div class="text-xs text-gray-400 mt-0.5">{{ r.city }}, {{ r.state }}</div>
            </div>
            <!-- Availability badge -->
            <div v-if="checkin && checkout">
              <span v-if="r.available_units > 0"
                class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                {{ r.available_units }} open
              </span>
              <span v-else class="text-xs font-semibold text-red-500 bg-red-50 px-2 py-1 rounded-full">
                Full
              </span>
            </div>
          </div>

          <p v-if="r.description" class="text-xs text-gray-500 line-clamp-2 leading-relaxed mb-3">
            {{ r.description }}
          </p>

          <div class="flex items-center justify-between">
            <span :class="r.business_type === 'hybrid' ? 'bg-amber-100 text-amber-700' : 'bg-violet-100 text-violet-700'"
              class="text-[10px] font-semibold px-2 py-0.5 rounded-full">
              {{ r.business_type === 'hybrid' ? 'Daycare + Boarding' : 'Boarding Kennel' }}
            </span>
            <span class="text-xs font-semibold text-violet-600 group-hover:text-violet-800 transition-colors">
              View & Book →
            </span>
          </div>
        </a>
      </div>

      <!-- Cross-links -->
      <div class="mt-10 flex flex-wrap gap-4 justify-center text-sm">
        <a href="/find-a-daycare" class="text-gray-400 hover:text-indigo-600 transition-colors">Find a daycare →</a>
        <a href="/leaderboard" class="text-gray-400 hover:text-indigo-600 transition-colors">View leaderboard →</a>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const appDomain = import.meta.env.VITE_APP_DOMAIN ?? 'pawpass.com';

interface BoardingResult {
  name: string;
  slug: string;
  logo_url: string | null;
  business_type: string;
  city: string;
  state: string;
  zip: string | null;
  phone: string | null;
  description: string | null;
  address: string | null;
  available_units?: number;
}

const props = defineProps<{
  results: BoardingResult[];
  city: string;
  state: string;
  checkin: string;
  checkout: string;
  headTitle: string;
  headDescription: string;
}>();

const form = ref({
  city:     props.city,
  state:    props.state,
  checkin:  props.checkin,
  checkout: props.checkout,
});

const US_STATES = [
  { value: 'AL', label: 'Alabama' }, { value: 'AK', label: 'Alaska' },
  { value: 'AZ', label: 'Arizona' }, { value: 'AR', label: 'Arkansas' },
  { value: 'CA', label: 'California' }, { value: 'CO', label: 'Colorado' },
  { value: 'CT', label: 'Connecticut' }, { value: 'DE', label: 'Delaware' },
  { value: 'FL', label: 'Florida' }, { value: 'GA', label: 'Georgia' },
  { value: 'HI', label: 'Hawaii' }, { value: 'ID', label: 'Idaho' },
  { value: 'IL', label: 'Illinois' }, { value: 'IN', label: 'Indiana' },
  { value: 'IA', label: 'Iowa' }, { value: 'KS', label: 'Kansas' },
  { value: 'KY', label: 'Kentucky' }, { value: 'LA', label: 'Louisiana' },
  { value: 'ME', label: 'Maine' }, { value: 'MD', label: 'Maryland' },
  { value: 'MA', label: 'Massachusetts' }, { value: 'MI', label: 'Michigan' },
  { value: 'MN', label: 'Minnesota' }, { value: 'MS', label: 'Mississippi' },
  { value: 'MO', label: 'Missouri' }, { value: 'MT', label: 'Montana' },
  { value: 'NE', label: 'Nebraska' }, { value: 'NV', label: 'Nevada' },
  { value: 'NH', label: 'New Hampshire' }, { value: 'NJ', label: 'New Jersey' },
  { value: 'NM', label: 'New Mexico' }, { value: 'NY', label: 'New York' },
  { value: 'NC', label: 'North Carolina' }, { value: 'ND', label: 'North Dakota' },
  { value: 'OH', label: 'Ohio' }, { value: 'OK', label: 'Oklahoma' },
  { value: 'OR', label: 'Oregon' }, { value: 'PA', label: 'Pennsylvania' },
  { value: 'RI', label: 'Rhode Island' }, { value: 'SC', label: 'South Carolina' },
  { value: 'SD', label: 'South Dakota' }, { value: 'TN', label: 'Tennessee' },
  { value: 'TX', label: 'Texas' }, { value: 'UT', label: 'Utah' },
  { value: 'VT', label: 'Vermont' }, { value: 'VA', label: 'Virginia' },
  { value: 'WA', label: 'Washington' }, { value: 'WV', label: 'West Virginia' },
  { value: 'WI', label: 'Wisconsin' }, { value: 'WY', label: 'Wyoming' },
];

function doSearch() {
  const params: Record<string, string> = {};
  if (form.value.checkin)  params.checkin  = form.value.checkin;
  if (form.value.checkout) params.checkout = form.value.checkout;

  if (form.value.city && form.value.state) {
    const citySlug = form.value.city.trim().toLowerCase().replace(/\s+/g, '-');
    router.visit(`/find-boarding/${form.value.state.toLowerCase()}/${citySlug}`, { data: params });
  } else {
    router.visit('/find-boarding', { data: params });
  }
}

function formatDate(d: string): string {
  if (!d) return '';
  const [y, m, day] = d.split('-');
  return `${m}/${day}/${y}`;
}

const boardingSchema = computed(() => {
  if (props.results.length === 0) return null;

  const items = props.results.map((r, i) => ({
    '@type': 'ListItem',
    'position': i + 1,
    'item': {
      '@type': 'LodgingBusiness',
      'name': r.name,
      'url': `https://${r.slug}.${appDomain}`,
      ...(r.description && { description: r.description }),
      ...(r.phone       && { telephone: r.phone }),
      'address': {
        '@type': 'PostalAddress',
        ...(r.address && { streetAddress: r.address }),
        'addressLocality': r.city,
        'addressRegion': r.state,
        ...(r.zip && { postalCode: r.zip }),
        'addressCountry': 'US',
      },
    },
  }));

  const listName = props.city
    ? `Dog Boarding in ${props.city}, ${props.state}`
    : 'Dog Boarding Kennels';

  return JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    'name': listName,
    'numberOfItems': items.length,
    'itemListElement': items,
  });
});

onMounted(() => {
  if (!boardingSchema.value) return;
  const el = document.createElement('script');
  el.type = 'application/ld+json';
  el.textContent = boardingSchema.value;
  document.head.appendChild(el);
});
</script>
