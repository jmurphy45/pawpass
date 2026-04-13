<template>
  <div class="min-h-screen" style="background: #faf9f6; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;">

    <!-- ═══ HERO + SEARCH ═══ -->
    <section class="relative overflow-hidden" style="background: #1c1a17; min-height: 68vh; display: flex; flex-direction: column;">

      <!-- Grain texture -->
      <div class="absolute inset-0 pointer-events-none opacity-[0.04]"
        style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><filter id=%22n%22><feTurbulence type=%22fractalNoise%22 baseFrequency=%220.75%22 numOctaves=%224%22 stitchTiles=%22stitch%22/></filter><rect width=%22200%22 height=%22200%22 filter=%22url(%23n)%22 opacity=%221%22/></svg>');">
      </div>

      <!-- Ambient glow -->
      <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] pointer-events-none"
        style="background: radial-gradient(ellipse at center top, rgba(79,70,229,0.18) 0%, transparent 65%); filter: blur(60px);">
      </div>

      <!-- Decorative paw — bottom right, very faint -->
      <div class="absolute -bottom-8 -right-8 w-[360px] h-[360px] opacity-[0.03] pointer-events-none select-none text-white">
        <svg viewBox="0 0 200 200" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <ellipse cx="60" cy="55" rx="22" ry="28"/>
          <ellipse cx="140" cy="55" rx="22" ry="28"/>
          <ellipse cx="32" cy="108" rx="18" ry="24" transform="rotate(-18 32 108)"/>
          <ellipse cx="168" cy="108" rx="18" ry="24" transform="rotate(18 168 108)"/>
          <ellipse cx="100" cy="155" rx="52" ry="42"/>
        </svg>
      </div>

      <!-- Nav -->
      <nav class="relative z-10 flex items-center justify-between px-6 pt-7 pb-0 max-w-5xl mx-auto w-full">
        <a href="/" class="find-hero-item text-white font-bold text-lg tracking-tight hover:opacity-80 transition-opacity" style="font-family: 'Instrument Sans', sans-serif;">
          PawPass
        </a>
        <a href="/register" class="find-hero-item text-sm font-medium text-white/50 hover:text-white/80 transition-colors">
          List your business →
        </a>
      </nav>

      <!-- Hero copy -->
      <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 pt-14 pb-4">
        <div class="find-hero-item text-center mb-3">
          <span class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.22em] text-indigo-400 mb-6">
            <span class="h-px w-6 bg-indigo-500/60 rounded-full"></span>
            Directory
            <span class="h-px w-6 bg-indigo-500/60 rounded-full"></span>
          </span>
        </div>

        <h1 class="find-hero-item find-serif text-white text-center leading-[1.05] tracking-tight mb-4"
          style="font-size: clamp(2.4rem, 5vw, 4rem); max-width: 640px;">
          Find a daycare<br>near you.
        </h1>

        <p class="find-hero-item text-white/45 text-center text-sm mb-10" style="max-width: 380px; line-height: 1.65;">
          Browse doggy daycares and boarding kennels on PawPass.
        </p>

        <!-- Search card -->
        <div class="find-hero-item w-full max-w-xl rounded-2xl p-6"
          style="background: rgba(255,255,255,0.055); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.09);">

          <!-- Mode toggle -->
          <div class="flex gap-1 mb-5 p-1 rounded-xl" style="background: rgba(0,0,0,0.25);">
            <button type="button" @click="searchMode = 'city'"
              class="flex-1 py-2 rounded-lg text-sm font-medium transition-all duration-200"
              :class="searchMode === 'city'
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-white/45 hover:text-white/70'">
              City &amp; State
            </button>
            <button type="button" @click="searchMode = 'zip'"
              class="flex-1 py-2 rounded-lg text-sm font-medium transition-all duration-200"
              :class="searchMode === 'zip'
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-white/45 hover:text-white/70'">
              ZIP Code
            </button>
          </div>

          <form @submit.prevent="doSearch" class="space-y-3">
            <!-- City + State -->
            <div v-if="searchMode === 'city'" class="flex gap-2">
              <input
                v-model="form.city"
                type="text"
                placeholder="City (e.g. Austin)"
                class="find-input flex-1"
              />
              <input
                v-model="form.state"
                type="text"
                placeholder="State"
                maxlength="2"
                class="find-input w-24 uppercase"
              />
            </div>

            <!-- ZIP -->
            <div v-else>
              <input
                v-model="form.zip"
                type="text"
                placeholder="ZIP Code (e.g. 78701)"
                class="find-input w-full"
              />
            </div>

            <!-- Boarding date filter toggle -->
            <div>
              <button type="button" @click="showDates = !showDates"
                class="flex items-center gap-1.5 text-xs text-white/40 hover:text-white/65 transition-colors">
                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="showDates ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
                Filter by boarding dates
              </button>

              <div v-if="showDates" class="flex gap-2 mt-3 items-center flex-wrap">
                <input
                  v-model="form.date_from"
                  type="date"
                  class="find-input flex-1 min-w-[140px]"
                />
                <span class="text-white/25 text-xs">to</span>
                <input
                  v-model="form.date_to"
                  type="date"
                  class="find-input flex-1 min-w-[140px]"
                />
              </div>
            </div>

            <!-- Search button -->
            <button type="submit"
              class="w-full rounded-xl py-3 text-sm font-semibold text-white transition-all duration-200 hover:opacity-90 active:scale-[0.98]"
              style="background: #4f46e5;">
              Search
            </button>
          </form>
        </div>
      </div>

      <!-- Scroll hint -->
      <div v-if="props.searched" class="relative z-10 flex justify-center pb-7 pt-4">
        <svg class="w-4 h-4 text-white/20 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </div>
    </section>

    <!-- ═══ RESULTS ═══ -->
    <main class="max-w-5xl mx-auto px-5 py-14">

      <!-- Results found -->
      <template v-if="props.searched && props.results.length > 0">
        <div class="flex items-center gap-4 mb-8">
          <span class="text-sm font-medium" style="color: #6b6560;">
            {{ props.results.length }} result{{ props.results.length !== 1 ? 's' : '' }} found
          </span>
          <div class="flex-1 h-px" style="background: #e5e0d8;"></div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="(daycare, i) in props.results"
            :key="daycare.slug"
            class="find-card bg-white rounded-2xl p-5 flex flex-col gap-3.5 border"
            :style="`--card-delay: ${i * 60}ms; border-color: #e5e0d8;`"
          >
            <!-- Logo + name -->
            <div class="flex items-start gap-3">
              <div class="w-12 h-12 rounded-xl flex-shrink-0 overflow-hidden flex items-center justify-center"
                style="background: #eef2ff;">
                <img v-if="daycare.logo_url" :src="daycare.logo_url" :alt="daycare.name" class="w-full h-full object-contain p-1.5" />
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" style="color: #a5b4fc;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-sm leading-snug" style="color: #2a2522;">{{ daycare.name }}</h3>
                <p class="text-xs mt-0.5" style="color: #9c9690;">{{ daycare.city }}, {{ daycare.state }}</p>
              </div>
            </div>

            <!-- Badges -->
            <div class="flex flex-wrap gap-1.5">
              <span :class="typeClass(daycare.business_type)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium">
                {{ typeLabel(daycare.business_type) }}
              </span>
              <span v-if="'boarding_available' in daycare"
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                :class="daycare.boarding_available ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400'">
                {{ daycare.boarding_available ? 'Boarding Available' : 'Boarding Full' }}
              </span>
              <span v-else-if="daycare.has_boarding" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium bg-sky-50 text-sky-700">
                Boarding
              </span>
            </div>

            <!-- Description -->
            <p v-if="daycare.description" class="text-xs line-clamp-2 leading-relaxed" style="color: #6b6560;">{{ daycare.description }}</p>

            <!-- Phone -->
            <p v-if="daycare.phone" class="text-xs" style="color: #9c9690;">{{ daycare.phone }}</p>

            <!-- CTA -->
            <div class="mt-auto pt-1 flex items-center justify-between">
              <div></div>
              <a
                :href="`https://${daycare.slug}.${appDomain}`"
                target="_blank"
                class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 transition-colors group"
              >
                Visit
                <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
              </a>
            </div>
          </div>
        </div>
      </template>

      <!-- No results -->
      <template v-else-if="props.searched && props.results.length === 0">
        <div class="flex flex-col items-center py-24 text-center">
          <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-5" style="background: #f0ede8;">
            <svg class="w-8 h-8" style="color: #c4bdb5;" viewBox="0 0 200 200" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <ellipse cx="60" cy="55" rx="22" ry="28"/>
              <ellipse cx="140" cy="55" rx="22" ry="28"/>
              <ellipse cx="32" cy="108" rx="18" ry="24" transform="rotate(-18 32 108)"/>
              <ellipse cx="168" cy="108" rx="18" ry="24" transform="rotate(18 168 108)"/>
              <ellipse cx="100" cy="155" rx="52" ry="42"/>
            </svg>
          </div>
          <p class="font-semibold mb-1" style="color: #2a2522;">No daycares found</p>
          <p class="text-sm" style="color: #9c9690;">Try a different city, state, or ZIP code.</p>
        </div>
      </template>

      <!-- Pre-search prompt -->
      <template v-else>
        <div class="flex flex-col items-center py-24 text-center">
          <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-5" style="background: #f0ede8;">
            <svg class="w-8 h-8" style="color: #c4bdb5;" viewBox="0 0 200 200" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <ellipse cx="60" cy="55" rx="22" ry="28"/>
              <ellipse cx="140" cy="55" rx="22" ry="28"/>
              <ellipse cx="32" cy="108" rx="18" ry="24" transform="rotate(-18 32 108)"/>
              <ellipse cx="168" cy="108" rx="18" ry="24" transform="rotate(18 168 108)"/>
              <ellipse cx="100" cy="155" rx="52" ry="42"/>
            </svg>
          </div>
          <p class="font-semibold mb-1" style="color: #2a2522;">Start your search above</p>
          <p class="text-sm" style="color: #9c9690;">Enter a city, state, or ZIP code to find daycares near you.</p>
        </div>
      </template>
    </main>

    <!-- ═══ FOOTER ═══ -->
    <footer class="border-t px-6 py-5" style="border-color: #e5e0d8;">
      <div class="max-w-5xl mx-auto flex flex-wrap items-center justify-between gap-3">
        <span class="text-xs" style="color: #b0a9a1;">Powered by <span class="font-semibold" style="color: #6b6560;">PawPass</span> · © 2025</span>
        <a href="/register" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 transition-colors">List your business →</a>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const appDomain = import.meta.env.VITE_APP_DOMAIN ?? 'pawpass.com';

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
const showDates = ref(!!(props.search.date_from || props.search.date_to));

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

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');

.find-serif {
  font-family: 'Playfair Display', Georgia, serif;
}

/* ── Entrance animations ── */
.find-hero-item {
  opacity: 0;
  animation: findFadeUp 0.55s ease forwards;
}
.find-hero-item:nth-child(1) { animation-delay: 0.05s; }
.find-hero-item:nth-child(2) { animation-delay: 0.12s; }
.find-hero-item:nth-child(3) { animation-delay: 0.2s; }
.find-hero-item:nth-child(4) { animation-delay: 0.3s; }
.find-hero-item:nth-child(5) { animation-delay: 0.38s; }

@keyframes findFadeUp {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Result card ── */
.find-card {
  opacity: 0;
  animation: findFadeUp 0.45s ease forwards;
  animation-delay: var(--card-delay, 0ms);
  box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px -1px rgba(0,0,0,0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.find-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.08), 0 2px 6px -2px rgba(0,0,0,0.05);
}

/* ── Dark-theme inputs ── */
.find-input {
  display: block;
  width: 100%;
  padding: 0.625rem 0.875rem;
  border-radius: 0.625rem;
  font-size: 0.875rem;
  line-height: 1.5;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #fff;
  outline: none;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.find-input::placeholder {
  color: rgba(255, 255, 255, 0.28);
}
.find-input:focus {
  border-color: rgba(99, 102, 241, 0.7);
  background: rgba(255, 255, 255, 0.11);
}
/* date inputs — hide calendar icon on dark bg */
.find-input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1) opacity(0.3);
}
</style>
