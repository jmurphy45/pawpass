<template>
  <Head>
    <title>{{ headTitle }}</title>
    <meta name="description" :content="headDescription" />
    <meta property="og:title" :content="headTitle" />
    <meta property="og:description" :content="headDescription" />
  </Head>

  <div class="min-h-screen" style="background: #faf9f6; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;">

    <!-- ═══ HERO ═══ -->
    <section class="relative overflow-hidden" style="background: #1c1a17; min-height: 44vh; display: flex; flex-direction: column;">

      <!-- Grain texture -->
      <div class="absolute inset-0 pointer-events-none opacity-[0.04]"
        style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><filter id=%22n%22><feTurbulence type=%22fractalNoise%22 baseFrequency=%220.75%22 numOctaves=%224%22 stitchTiles=%22stitch%22/></filter><rect width=%22200%22 height=%22200%22 filter=%22url(%23n)%22 opacity=%221%22/></svg>');">
      </div>

      <!-- Ambient glow -->
      <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] pointer-events-none"
        style="background: radial-gradient(ellipse at center top, rgba(79,70,229,0.18) 0%, transparent 65%); filter: blur(60px);">
      </div>

      <!-- Nav -->
      <nav class="relative z-10 flex items-center justify-between px-6 pt-7 pb-0 max-w-5xl mx-auto w-full">
        <a href="/" class="text-white font-bold text-lg tracking-tight hover:opacity-80 transition-opacity">
          PawPass
        </a>
        <a href="/register" class="text-sm font-medium text-white/50 hover:text-white/80 transition-colors">
          List your business →
        </a>
      </nav>

      <!-- Hero copy -->
      <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 pt-10 pb-8">
        <span class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.22em] text-indigo-400 mb-5">
          <span class="h-px w-6 bg-indigo-500/60 rounded-full"></span>
          Live Leaderboard
          <span class="h-px w-6 bg-indigo-500/60 rounded-full"></span>
        </span>

        <h1 class="text-white text-center leading-tight tracking-tight mb-3"
          style="font-size: clamp(2rem, 4.5vw, 3.2rem); max-width: 600px; font-family: 'Lora', Georgia, serif;">
          <template v-if="city && state">
            Top Daycares in {{ city }}, {{ state }}
          </template>
          <template v-else>
            Today's Busiest Dog Daycares
          </template>
        </h1>

        <p class="text-white/45 text-center text-sm" style="max-width: 400px; line-height: 1.65;">
          Live check-in counts updated every 5 minutes. Click any facility to visit their booking page.
        </p>

        <!-- City filter -->
        <form @submit.prevent="goToCity" class="mt-7 flex gap-2 w-full max-w-sm">
          <input
            v-model="cityInput"
            type="text"
            placeholder="City, e.g. Memphis"
            class="flex-1 rounded-xl px-4 py-2.5 text-sm text-gray-900 bg-white/90 placeholder-gray-400 outline-none focus:ring-2 focus:ring-indigo-400"
          />
          <select v-model="stateInput" class="rounded-xl px-3 py-2.5 text-sm text-gray-900 bg-white/90 outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">State</option>
            <option v-for="s in US_STATES" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <button type="submit"
            class="rounded-xl px-4 py-2.5 text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-500 transition-colors">
            Go
          </button>
        </form>
      </div>
    </section>

    <!-- ═══ LEADERBOARD ═══ -->
    <section class="max-w-4xl mx-auto px-4 py-10">

      <!-- Breadcrumb to global when on city page -->
      <div v-if="city" class="mb-6 text-sm text-gray-500">
        <a href="/leaderboard" class="hover:text-indigo-600 transition-colors">All cities</a>
        <span class="mx-2 text-gray-300">/</span>
        <span class="text-gray-700">{{ city }}, {{ state }}</span>
      </div>

      <!-- Empty state -->
      <div v-if="tenants.length === 0" class="text-center py-20">
        <div class="text-5xl mb-4">🐾</div>
        <p class="text-gray-500 text-lg">No daycares found{{ city ? ` in ${city}, ${state}` : '' }}.</p>
        <a href="/find-a-daycare" class="mt-4 inline-block text-indigo-600 text-sm font-medium hover:underline">
          Search the full directory →
        </a>
      </div>

      <!-- Desktop table -->
      <div v-else class="hidden md:block rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr style="background: #f5f4f0;">
              <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide w-12">#</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Facility</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Location</th>
              <th class="text-right px-4 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Currently In</th>
              <th class="text-right px-4 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Today Total</th>
              <th class="w-8"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(t, i) in tenants" :key="t.id"
              class="border-t border-gray-50 hover:bg-indigo-50/40 transition-colors group">
              <!-- Rank -->
              <td class="px-5 py-4">
                <span class="text-xs font-bold text-gray-300">{{ i + 1 }}</span>
              </td>
              <!-- Facility -->
              <td class="px-4 py-4">
                <div class="flex items-center gap-3">
                  <img v-if="t.logo_url" :src="t.logo_url" :alt="t.name"
                    class="w-9 h-9 rounded-xl object-cover border border-gray-100 flex-shrink-0" />
                  <div v-else class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center text-sm font-bold text-white"
                    style="background: #4f46e5;">
                    {{ t.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <div class="font-semibold text-gray-900 leading-tight">{{ t.name }}</div>
                    <span :class="typeBadgeClass(t.business_type)"
                      class="mt-0.5 inline-block text-[10px] font-semibold px-1.5 py-0.5 rounded-full">
                      {{ typeLabel(t.business_type) }}
                    </span>
                  </div>
                </div>
              </td>
              <!-- Location -->
              <td class="px-4 py-4 text-gray-500">
                <span v-if="!city">
                  <a :href="`/leaderboard/${t.state.toLowerCase()}/${citySlug(t.city)}`"
                    class="hover:text-indigo-600 transition-colors">
                    {{ t.city }}, {{ t.state }}
                  </a>
                </span>
                <span v-else>{{ t.city }}, {{ t.state }}</span>
              </td>
              <!-- Currently in -->
              <td class="px-4 py-4 text-right">
                <span class="inline-flex items-center gap-1 font-semibold"
                  :class="t.currently_in > 0 ? 'text-emerald-600' : 'text-gray-300'">
                  <span v-if="t.currently_in > 0" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  {{ t.currently_in }}
                </span>
              </td>
              <!-- Today total -->
              <td class="px-4 py-4 text-right font-semibold text-gray-700">{{ t.today_total }}</td>
              <!-- Book link -->
              <td class="px-4 py-4 text-right">
                <a :href="`https://${t.slug}.${appDomain}`" target="_blank" rel="noopener"
                  class="opacity-0 group-hover:opacity-100 transition-opacity text-xs font-semibold text-indigo-600 hover:text-indigo-800 whitespace-nowrap">
                  Book →
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="md:hidden space-y-3">
        <a v-for="(t, i) in tenants" :key="t.id"
          :href="`https://${t.slug}.${appDomain}`" target="_blank" rel="noopener"
          class="block rounded-2xl bg-white border border-gray-100 shadow-sm p-4 hover:border-indigo-200 transition-colors">
          <div class="flex items-start gap-3">
            <!-- Rank badge -->
            <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
              :class="i < 3 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-400'">
              {{ i + 1 }}
            </div>
            <!-- Logo -->
            <img v-if="t.logo_url" :src="t.logo_url" :alt="t.name"
              class="w-10 h-10 rounded-xl object-cover border border-gray-100 flex-shrink-0" />
            <div v-else class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center text-sm font-bold text-white"
              style="background: #4f46e5;">
              {{ t.name.charAt(0).toUpperCase() }}
            </div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-gray-900 text-sm truncate">{{ t.name }}</div>
              <div class="text-xs text-gray-400 mt-0.5">{{ t.city }}, {{ t.state }}</div>
            </div>
            <!-- Stats -->
            <div class="text-right flex-shrink-0">
              <div class="text-sm font-bold text-gray-800">{{ t.today_total }} <span class="text-xs font-normal text-gray-400">today</span></div>
              <div class="text-xs mt-0.5" :class="t.currently_in > 0 ? 'text-emerald-600 font-medium' : 'text-gray-300'">
                {{ t.currently_in }} in now
              </div>
            </div>
          </div>
        </a>
      </div>

      <!-- Footer note -->
      <p class="text-center text-xs text-gray-400 mt-8">
        Counts refresh every 5 minutes. Only participating facilities are listed.
        <a href="/find-a-daycare" class="ml-1 text-indigo-500 hover:underline">Browse full directory →</a>
      </p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

const appDomain = import.meta.env.VITE_APP_DOMAIN ?? 'pawpass.com';

interface TenantStat {
  id: string;
  name: string;
  slug: string;
  logo_url: string | null;
  business_type: string;
  city: string;
  state: string;
  today_total: number;
  currently_in: number;
}

const props = defineProps<{
  tenants: TenantStat[];
  city: string | null;
  state: string | null;
  headTitle: string;
  headDescription: string;
}>();

const cityInput = ref(props.city ?? '');
const stateInput = ref(props.state ?? '');

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

function goToCity() {
  if (!cityInput.value || !stateInput.value) return;
  const slug = cityInput.value.trim().toLowerCase().replace(/\s+/g, '-');
  router.visit(`/leaderboard/${stateInput.value.toLowerCase()}/${slug}`);
}

function citySlug(city: string): string {
  return city.toLowerCase().replace(/\s+/g, '-');
}

function typeLabel(type: string): string {
  if (type === 'kennel') return 'Boarding Kennel';
  if (type === 'hybrid') return 'Daycare + Boarding';
  return 'Daycare';
}

function typeBadgeClass(type: string): string {
  if (type === 'kennel') return 'bg-purple-100 text-purple-700';
  if (type === 'hybrid') return 'bg-amber-100 text-amber-700';
  return 'bg-indigo-100 text-indigo-700';
}
</script>
