<template>
  <AdminLayout>
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -my-6 flex flex-col bg-[#faf9f6] min-h-[calc(100vh-64px)] overflow-hidden">

      <!-- ── Header ──────────────────────────────────────────────────────── -->
      <header class="flex flex-none items-center justify-between border-b border-gray-200 bg-white px-6 py-3.5">
        <div>
          <h1 class="text-sm font-semibold text-gray-900 leading-none">{{ periodLabel }}</h1>
          <p class="mt-1 text-[11px] text-gray-400">{{ viewLabel }}</p>
        </div>

        <div class="flex items-center gap-2.5">
          <!-- Prev / Today / Next -->
          <div class="relative flex items-center rounded-lg bg-white shadow-xs outline outline-1 -outline-offset-1 outline-gray-200 md:items-stretch">
            <button
              type="button"
              @click="navigate(-1)"
              class="flex h-8 w-8 items-center justify-center rounded-l-lg text-gray-400 hover:text-gray-600 hover:bg-gray-50 focus:relative transition-colors"
            >
              <span class="sr-only">Previous</span>
              <ChevronLeftIcon class="size-4" aria-hidden="true" />
            </button>
            <button
              type="button"
              @click="goToday"
              class="hidden px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 border-l border-r border-gray-200 focus:relative transition-colors md:block"
            >Today</button>
            <span class="relative -mx-px h-5 w-px bg-gray-200 md:hidden" />
            <button
              type="button"
              @click="navigate(1)"
              class="flex h-8 w-8 items-center justify-center rounded-r-lg text-gray-400 hover:text-gray-600 hover:bg-gray-50 focus:relative transition-colors"
            >
              <span class="sr-only">Next</span>
              <ChevronRightIcon class="size-4" aria-hidden="true" />
            </button>
          </div>

          <!-- Desktop: view dropdown + action -->
          <div class="hidden md:flex md:items-center gap-2.5">
            <Menu as="div" class="relative">
              <MenuButton
                type="button"
                class="flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs ring-1 ring-gray-200 ring-inset hover:bg-gray-50 transition-colors"
              >
                {{ currentViewLabel }}
                <ChevronDownIcon class="-mr-0.5 size-3.5 text-gray-400" aria-hidden="true" />
              </MenuButton>

              <transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
              >
                <MenuItems class="absolute right-0 z-10 mt-2 w-32 origin-top-right overflow-hidden rounded-lg bg-white shadow-lg outline outline-1 outline-black/5 py-1">
                  <MenuItem v-for="v in VIEWS" :key="v.key" v-slot="{ active }">
                    <button
                      type="button"
                      @click="switchView(v.key)"
                      class="w-full text-left block px-3 py-1.5 text-xs"
                      :class="[
                        active ? 'bg-gray-50 text-gray-900 outline-none' : 'text-gray-600',
                        props.view === v.key ? 'font-semibold' : '',
                      ]"
                    >{{ v.label }}</button>
                  </MenuItem>
                </MenuItems>
              </transition>
            </Menu>

            <Link :href="route('admin.boarding.reservations')">
              <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:opacity-90 transition-opacity"
                :style="{ backgroundColor: accentColor }"
              >
                Reservations
              </button>
            </Link>
          </div>

          <!-- Mobile: ellipsis menu -->
          <div class="md:hidden">
            <Menu as="div" class="relative">
              <MenuButton class="relative flex items-center rounded-full p-1 text-gray-400 hover:text-gray-500">
                <span class="absolute -inset-2" />
                <span class="sr-only">Open menu</span>
                <EllipsisHorizontalIcon class="size-5" aria-hidden="true" />
              </MenuButton>

              <transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
              >
                <MenuItems class="absolute right-0 z-10 mt-3 w-40 origin-top-right divide-y divide-gray-100 overflow-hidden rounded-lg bg-white shadow-lg outline outline-1 outline-black/5">
                  <div class="py-1">
                    <MenuItem v-slot="{ active }">
                      <button type="button" @click="goToday" class="w-full text-left block px-4 py-2 text-xs" :class="active ? 'bg-gray-50 text-gray-900 outline-none' : 'text-gray-700'">Go to today</button>
                    </MenuItem>
                  </div>
                  <div class="py-1">
                    <MenuItem v-for="v in VIEWS" :key="v.key" v-slot="{ active }">
                      <button type="button" @click="switchView(v.key)" class="w-full text-left block px-4 py-2 text-xs" :class="active ? 'bg-gray-50 text-gray-900 outline-none' : 'text-gray-700'">{{ v.label }}</button>
                    </MenuItem>
                  </div>
                  <div class="py-1">
                    <MenuItem v-slot="{ active }">
                      <Link :href="route('admin.boarding.reservations')" class="block px-4 py-2 text-xs" :class="active ? 'bg-gray-50 text-gray-900 outline-none' : 'text-gray-700'">Reservations</Link>
                    </MenuItem>
                  </div>
                </MenuItems>
              </transition>
            </Menu>
          </div>
        </div>
      </header>

      <!-- ── Occupancy strip ────────────────────────────────────────────── -->
      <div v-if="units.length > 0" class="flex-none px-6 py-2 bg-white border-b border-gray-100 flex items-center gap-3">
        <span class="text-[11px] text-gray-500">
          <span class="font-semibold text-gray-800">{{ occupiedCount }}</span> of {{ units.length }} units occupied {{ occupancyRefLabel }}
        </span>
        <div class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-500"
            :style="{ width: occupancyPct + '%', backgroundColor: accentColor }"
          />
        </div>
      </div>

      <!-- ── Body ────────────────────────────────────────────────────────── -->
      <div class="relative isolate flex flex-auto overflow-hidden">
        <!-- Loading overlay -->
        <div v-if="isNavigating" class="absolute inset-0 z-30 bg-white/60 flex items-center justify-center">
          <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        </div>

        <!-- Occupancy grid -->
        <div class="flex flex-auto flex-col overflow-auto">
          <div v-if="units.length === 0" class="flex flex-1 items-center justify-center text-sm text-gray-400 py-20">
            No active kennel units configured.
          </div>

          <table v-else class="text-xs min-w-full border-collapse">
            <!-- Dark header -->
            <thead class="sticky top-0 z-10">
              <tr style="background-color: #0f0e0d;">
                <th
                  class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider sticky left-0 z-20 min-w-[150px] border-r"
                  style="background-color: #0f0e0d; border-color: rgba(255,255,255,0.07); color: rgba(255,255,255,0.35);"
                >
                  Unit
                </th>
                <th
                  v-for="day in days"
                  :key="day"
                  class="py-2.5 text-center font-semibold transition-colors"
                  :class="props.view === 'month' ? 'min-w-[48px]' : 'min-w-[100px]'"
                  :style="isToday(day) ? { backgroundColor: accentColor } : {}"
                >
                  <div
                    class="text-xs leading-none"
                    :class="isToday(day) ? 'text-white font-bold' : 'text-white/45'"
                  >
                    {{ formatDayHeader(day) }}
                  </div>
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="unit in units"
                :key="unit.id"
                class="border-b border-gray-100 hover:bg-gray-50/40 transition-colors"
                style="height: 44px;"
              >
                <!-- Unit label (sticky) -->
                <td class="px-4 py-2 sticky left-0 bg-white z-10 border-r border-gray-100 align-middle">
                  <div class="font-semibold text-gray-900 text-xs leading-none">{{ unit.name }}</div>
                  <div class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-gray-100 text-gray-400 uppercase tracking-wide">{{ unit.type }}</div>
                </td>

                <!-- Gantt bar cells -->
                <td
                  v-for="day in days"
                  :key="day"
                  class="px-0 py-1 align-middle border-r border-gray-100/80"
                  :class="isToday(day) ? 'bg-indigo-50/40' : ''"
                >
                  <template v-if="occupancyMap[unit.id]?.[day]">
                    <Link
                      :href="route('admin.boarding.reservations.show', { reservation: occupancyMap[unit.id][day].id })"
                      :title="ganttTitle(unit.id, day)"
                      class="flex items-center h-6 hover:opacity-75 transition-opacity"
                      :class="ganttBarClass(unit.id, day)"
                    >
                      <span
                        v-if="isSpanStart(unit.id, day) && props.view !== 'month'"
                        class="text-[10px] px-1.5 font-semibold truncate leading-none max-w-[80px]"
                      >
                        {{ occupancyMap[unit.id][day].dog?.name ?? '—' }}
                      </span>
                    </Link>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── Mini calendar sidebar (desktop) ─────────────────────────── -->
        <div class="hidden w-52 flex-none border-l border-gray-100 px-5 py-6 md:block overflow-auto bg-white">
          <!-- Mini calendar header -->
          <div class="flex items-center justify-between mb-3">
            <button
              type="button"
              @click="prevMiniMonth"
              class="p-1 -ml-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors"
            >
              <span class="sr-only">Previous month</span>
              <ChevronLeftIcon class="size-4" aria-hidden="true" />
            </button>
            <div class="text-xs font-semibold text-gray-900">{{ miniMonthLabel }}</div>
            <button
              type="button"
              @click="nextMiniMonth"
              class="p-1 -mr-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors"
            >
              <span class="sr-only">Next month</span>
              <ChevronRightIcon class="size-4" aria-hidden="true" />
            </button>
          </div>

          <!-- Day of week labels -->
          <div class="grid grid-cols-7 text-center mb-1">
            <div v-for="d in ['M','T','W','T','F','S','S']" :key="d" class="text-[10px] text-gray-400 font-medium py-0.5">{{ d }}</div>
          </div>

          <!-- Day cells -->
          <div class="grid grid-cols-7">
            <button
              v-for="(day, idx) in miniCalendarDays"
              :key="day.date + idx"
              type="button"
              @click="selectMiniDay(day.date)"
              class="flex items-center justify-center py-0.5 hover:bg-gray-50 transition-colors rounded"
            >
              <time
                :datetime="day.date"
                class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] leading-none transition-colors"
                :class="[
                  !day.isToday && day.isInPeriod ? 'bg-gray-800 text-white font-semibold' : '',
                  !day.isToday && !day.isInPeriod && day.isCurrentMonth ? 'text-gray-700' : '',
                  !day.isToday && !day.isInPeriod && !day.isCurrentMonth ? 'text-gray-300' : '',
                ]"
                :style="day.isToday ? { backgroundColor: accentColor, color: 'white' } : {}"
              >{{ day.date.split('-').pop()!.replace(/^0/, '') }}</time>
            </button>
          </div>

          <!-- Legend -->
          <div class="mt-5 pt-4 border-t border-gray-100 space-y-2">
            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Status</p>
            <div v-for="s in STATUS_LEGEND" :key="s.key" class="flex items-center gap-2">
              <span class="inline-block h-2 w-3 rounded-sm flex-none" :class="s.color" />
              <span class="text-[11px] text-gray-600">{{ s.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  EllipsisHorizontalIcon,
} from '@heroicons/vue/20/solid';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import type { PageProps } from '@/types';

// ── Types ──────────────────────────────────────────────────────────────────

interface Reservation {
  id: string;
  status: 'pending' | 'confirmed' | 'checked_in' | 'checked_out' | 'cancelled';
  starts_at: string;
  ends_at: string;
  dog: { id: string; name: string } | null;
  customer: { id: string; name: string } | null;
}

interface Unit {
  id: string;
  name: string;
  type: string;
  reservations: Reservation[];
}

type ViewMode = 'day' | 'week' | 'month';

// ── Props ──────────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
  units: Unit[];
  from: string;
  to: string;
  view: string;
}>(), {
  view: 'week',
});

// ── Page / accent color ────────────────────────────────────────────────────

const page = usePage<PageProps>();
const accentColor = computed(() => (page.props.tenant as any)?.primary_color ?? '#4f46e5');

const isNavigating = ref(false);

// ── Constants ──────────────────────────────────────────────────────────────

const VIEWS = [
  { key: 'day',   label: 'Day'   },
  { key: 'week',  label: 'Week'  },
  { key: 'month', label: 'Month' },
] as const;

const STATUS_BG: Record<string, string> = {
  pending:     'bg-amber-100 text-amber-900',
  confirmed:   'bg-emerald-100 text-emerald-900',
  checked_in:  'bg-blue-100 text-blue-900',
  checked_out: 'bg-gray-200 text-gray-500',
};

const STATUS_BORDER_START: Record<string, string> = {
  pending:     'border-l-2 border-amber-400',
  confirmed:   'border-l-2 border-emerald-500',
  checked_in:  'border-l-2 border-blue-500',
  checked_out: 'border-l-2 border-gray-400',
};

const STATUS_LEGEND = [
  { key: 'pending',     label: 'Pending',     color: 'bg-amber-400' },
  { key: 'confirmed',   label: 'Confirmed',   color: 'bg-emerald-500' },
  { key: 'checked_in',  label: 'Checked in',  color: 'bg-blue-500' },
  { key: 'checked_out', label: 'Checked out', color: 'bg-gray-300' },
];

// ── Date helpers ────────────────────────────────────────────────────────────

function parseISO(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
}

function toISO(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function addDays(d: Date, n: number): Date {
  const r = new Date(d);
  r.setDate(r.getDate() + n);
  return r;
}

function startOfWeek(d: Date): Date {
  const dow = d.getDay();
  return addDays(d, dow === 0 ? -6 : 1 - dow);
}

function getPeriodBounds(view: string, anchorISO: string): { from: string; to: string } {
  const anchor = parseISO(anchorISO);
  if (view === 'day') {
    return { from: anchorISO, to: anchorISO };
  }
  if (view === 'week') {
    const mon = startOfWeek(anchor);
    return { from: toISO(mon), to: toISO(addDays(mon, 6)) };
  }
  const first = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
  const last  = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0);
  return { from: toISO(first), to: toISO(last) };
}

// ── Mini calendar state ─────────────────────────────────────────────────────

const miniYear  = ref(parseISO(props.from).getFullYear());
const miniMonth = ref(parseISO(props.from).getMonth());

function prevMiniMonth() {
  if (miniMonth.value === 0) { miniMonth.value = 11; miniYear.value--; }
  else { miniMonth.value--; }
}

function nextMiniMonth() {
  if (miniMonth.value === 11) { miniMonth.value = 0; miniYear.value++; }
  else { miniMonth.value++; }
}

const miniMonthLabel = computed(() =>
  new Date(miniYear.value, miniMonth.value, 1)
    .toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
);

const miniCalendarDays = computed(() => {
  const firstOfMonth = new Date(miniYear.value, miniMonth.value, 1);
  const dow = firstOfMonth.getDay();
  const start = addDays(firstOfMonth, dow === 0 ? -6 : 1 - dow);

  return Array.from({ length: 42 }, (_, i) => {
    const d   = addDays(start, i);
    const iso = toISO(d);
    return {
      date: iso,
      isCurrentMonth: d.getMonth() === miniMonth.value,
      isToday:        iso === toISO(new Date()),
      isInPeriod:     iso >= props.from && iso <= props.to,
    };
  });
});

// ── Main grid computed ──────────────────────────────────────────────────────

const days = computed<string[]>(() => {
  const result: string[] = [];
  let current = parseISO(props.from);
  const end   = parseISO(props.to);
  while (current <= end) {
    result.push(toISO(current));
    current = addDays(current, 1);
  }
  return result;
});

const periodLabel = computed<string>(() => {
  const from = parseISO(props.from);
  const to   = parseISO(props.to);
  if (props.view === 'day') {
    return from.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }
  if (props.view === 'month') {
    return from.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  }
  const fromStr = from.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const toStr   = to.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  return `${fromStr} – ${toStr}`;
});

const viewLabel = computed<string>(() => {
  if (props.view === 'day') {
    return parseISO(props.from).toLocaleDateString('en-US', { weekday: 'long' });
  }
  if (props.view === 'month') return 'Month view';
  return 'Week view';
});

const currentViewLabel = computed(() =>
  VIEWS.find(v => v.key === props.view)?.label ?? 'Week'
);

/** Pre-computed occupancy map: occupancyMap[unitId][YYYY-MM-DD] = Reservation */
const occupancyMap = computed<Record<string, Record<string, Reservation>>>(() => {
  const map: Record<string, Record<string, Reservation>> = {};
  for (const unit of props.units) {
    map[unit.id] = {};
    for (const res of unit.reservations) {
      if (!res.starts_at || !res.ends_at) continue;
      let cur = parseISO(res.starts_at.slice(0, 10));
      const end = parseISO(res.ends_at.slice(0, 10));
      while (cur < end) {
        map[unit.id][toISO(cur)] = res;
        cur = addDays(cur, 1);
      }
    }
  }
  return map;
});

// ── Occupancy stats ──────────────────────────────────────────────────────────

const todayISO = computed(() => toISO(new Date()));

// Use today's count when today is in the viewed period, otherwise use the period start
const isViewingToday = computed(() => todayISO.value >= props.from && todayISO.value <= props.to);
const refDay = computed(() => isViewingToday.value ? todayISO.value : props.from);

const occupiedCount = computed(() =>
  props.units.filter(unit => !!occupancyMap.value[unit.id]?.[refDay.value]).length
);

const occupancyPct = computed(() => {
  if (props.units.length === 0) return 0;
  return Math.round((occupiedCount.value / props.units.length) * 100);
});

const occupancyRefLabel = computed(() => {
  if (isViewingToday.value) return 'today';
  const d = parseISO(props.from);
  return `on ${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
});

// ── Gantt bar helpers ────────────────────────────────────────────────────────

function isSpanStart(unitId: string, day: string): boolean {
  const res = occupancyMap.value[unitId]?.[day];
  if (!res) return false;
  const dayIdx = days.value.indexOf(day);
  if (dayIdx === 0) return true;
  const prevDay = days.value[dayIdx - 1];
  return res.id !== occupancyMap.value[unitId]?.[prevDay]?.id;
}

function isSpanEnd(unitId: string, day: string): boolean {
  const res = occupancyMap.value[unitId]?.[day];
  if (!res) return false;
  const dayIdx = days.value.indexOf(day);
  if (dayIdx === days.value.length - 1) return true;
  const nextDay = days.value[dayIdx + 1];
  return res.id !== occupancyMap.value[unitId]?.[nextDay]?.id;
}

function ganttBarClass(unitId: string, day: string): string {
  const res = occupancyMap.value[unitId]?.[day];
  if (!res) return '';

  const start = isSpanStart(unitId, day);
  const end   = isSpanEnd(unitId, day);
  const bg     = STATUS_BG[res.status] ?? 'bg-gray-100 text-gray-500';
  const border = start ? (STATUS_BORDER_START[res.status] ?? '') : '';

  let rounding = '';
  if (start && end)        rounding = 'rounded-md mx-0.5';
  else if (start && !end)  rounding = 'rounded-l-md ml-0.5';
  else if (!start && end)  rounding = 'rounded-r-md mr-0.5';

  return [bg, border, rounding].filter(Boolean).join(' ');
}

function ganttTitle(unitId: string, day: string): string {
  const res = occupancyMap.value[unitId]?.[day];
  if (!res) return '';
  const dog = res.dog?.name ?? 'Unknown';
  const from = parseISO(res.starts_at.slice(0, 10)).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const to   = parseISO(res.ends_at.slice(0, 10)).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const status = res.status.replace(/_/g, ' ');
  return `${dog} · ${from}–${to} · ${status}`;
}

// ── Helpers ─────────────────────────────────────────────────────────────────

function isToday(iso: string): boolean {
  return iso === todayISO.value;
}

function formatDayHeader(iso: string): string {
  const d = parseISO(iso);
  if (props.view === 'month') return d.toLocaleDateString('en-US', { day: 'numeric' });
  if (props.view === 'day')   return d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
  return d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' });
}

// ── Navigation ───────────────────────────────────────────────────────────────

function navigate(dir: 1 | -1) {
  const anchor = parseISO(props.from);
  let newAnchor: Date;
  if (props.view === 'day')        newAnchor = addDays(anchor, dir);
  else if (props.view === 'week')  newAnchor = addDays(anchor, dir * 7);
  else                             newAnchor = new Date(anchor.getFullYear(), anchor.getMonth() + dir, 1);

  miniYear.value  = newAnchor.getFullYear();
  miniMonth.value = newAnchor.getMonth();
  isNavigating.value = true;
  const { from, to } = getPeriodBounds(props.view, toISO(newAnchor));
  router.get(route('admin.boarding.occupancy'), { view: props.view, from, to }, { preserveScroll: false });
}

function switchView(newView: ViewMode) {
  // Anchor to the current period's start, not today
  const { from, to } = getPeriodBounds(newView, props.from);
  const d = parseISO(props.from);
  miniYear.value  = d.getFullYear();
  miniMonth.value = d.getMonth();
  isNavigating.value = true;
  router.get(route('admin.boarding.occupancy'), { view: newView, from, to }, { preserveScroll: false });
}

function goToday() {
  const { from, to } = getPeriodBounds(props.view, toISO(new Date()));
  const d = new Date();
  miniYear.value  = d.getFullYear();
  miniMonth.value = d.getMonth();
  isNavigating.value = true;
  router.get(route('admin.boarding.occupancy'), { view: props.view, from, to }, { preserveScroll: false });
}

function selectMiniDay(iso: string) {
  const { from, to } = getPeriodBounds(props.view, iso);
  const d = parseISO(iso);
  miniYear.value  = d.getFullYear();
  miniMonth.value = d.getMonth();
  isNavigating.value = true;
  router.get(route('admin.boarding.occupancy'), { view: props.view, from, to }, { preserveScroll: false });
}
</script>
