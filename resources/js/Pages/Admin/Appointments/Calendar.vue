<template>
  <AdminLayout>
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -my-6 flex flex-col bg-[#faf9f6] min-h-[calc(100vh-64px)]">

      <!-- Header -->
      <header class="flex flex-none items-center justify-between border-b border-gray-200 bg-white px-6 py-3.5">
        <div>
          <h1 class="text-sm font-semibold text-gray-900 leading-none">{{ periodLabel }}</h1>
          <p class="mt-1 text-[11px] text-gray-400">Appointments calendar</p>
        </div>

        <div class="flex items-center gap-2.5">
          <!-- Prev / Today / Next -->
          <div class="relative flex items-center rounded-lg bg-white shadow-xs outline outline-1 -outline-offset-1 outline-gray-200">
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
            <button
              type="button"
              @click="navigate(1)"
              class="flex h-8 w-8 items-center justify-center rounded-r-lg text-gray-400 hover:text-gray-600 hover:bg-gray-50 focus:relative transition-colors"
            >
              <span class="sr-only">Next</span>
              <ChevronRightIcon class="size-4" aria-hidden="true" />
            </button>
          </div>

          <Link :href="route('admin.appointments.index')" class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs ring-1 ring-gray-200 ring-inset hover:bg-gray-50 transition-colors">
            List view
          </Link>
        </div>
      </header>

      <!-- Agenda body -->
      <div class="flex-auto overflow-y-auto px-6 py-4">
        <div v-if="days.length === 0" class="flex items-center justify-center py-20 text-sm text-gray-400">
          No appointments this week.
        </div>

        <div v-for="day in days" :key="day.date" class="mb-6">
          <!-- Day header -->
          <div class="flex items-center gap-3 mb-2">
            <div
              class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold shrink-0"
              :class="day.isToday ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
            >
              {{ day.dayNumber }}
            </div>
            <div>
              <span class="text-sm font-semibold text-gray-900">{{ day.label }}</span>
              <span class="ml-2 text-xs text-gray-400">{{ day.appointments.length }} appointment{{ day.appointments.length !== 1 ? 's' : '' }}</span>
            </div>
          </div>

          <!-- Appointment rows -->
          <div class="ml-11 space-y-1.5">
            <div
              v-for="appt in day.appointments"
              :key="appt.id"
              class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm hover:border-gray-300 transition-colors"
            >
              <!-- Service color dot -->
              <span class="size-2 rounded-full shrink-0" :class="serviceColor(appt.service_type)" />

              <!-- Time -->
              <span class="w-16 shrink-0 text-xs text-gray-500 tabular-nums">{{ formatTime(appt.starts_at) }}</span>

              <!-- Dog name -->
              <span class="font-medium text-gray-900 min-w-0 truncate">{{ appt.dog?.name ?? '—' }}</span>

              <!-- Service label -->
              <span class="text-xs text-gray-500 capitalize shrink-0">{{ serviceLabel(appt.service_type) }}</span>

              <!-- Resource -->
              <span v-if="appt.resource" class="text-xs text-gray-400 shrink-0 hidden sm:block">{{ appt.resource.name }}</span>

              <!-- Status badge -->
              <AppBadge :variant="statusVariant(appt.status)" class="ml-auto shrink-0">{{ appt.status }}</AppBadge>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'

interface Appointment {
  id: string
  service_type: string
  status: string
  starts_at: string
  dog?: { name: string } | null
  resource?: { name: string; resource_type: string } | null
}

interface PageProps {
  appointments: Appointment[]
  weekStart: string
  [key: string]: unknown
}

const props = defineProps<PageProps>()

const anchor = ref(new Date(props.weekStart + 'T00:00:00'))

const weekDates = computed(() => {
  const dates: Date[] = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(anchor.value)
    d.setDate(d.getDate() + i)
    dates.push(d)
  }
  return dates
})

const periodLabel = computed(() => {
  const start = weekDates.value[0]
  const end = weekDates.value[6]
  const fmt = (d: Date) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  const year = end.getFullYear()
  return `${fmt(start)} – ${fmt(end)}, ${year}`
})

const today = new Date()
today.setHours(0, 0, 0, 0)

const days = computed(() => {
  return weekDates.value.map(date => {
    const iso = date.toISOString().slice(0, 10)
    const appts = props.appointments.filter(a => a.starts_at.slice(0, 10) === iso)
    appts.sort((a, b) => a.starts_at.localeCompare(b.starts_at))
    const d = new Date(date)
    d.setHours(0, 0, 0, 0)
    return {
      date: iso,
      dayNumber: date.getDate(),
      label: date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }),
      isToday: d.getTime() === today.getTime(),
      appointments: appts,
    }
  }).filter(d => d.appointments.length > 0 || d.isToday)
})

function navigate(direction: number) {
  const next = new Date(anchor.value)
  next.setDate(next.getDate() + direction * 7)
  const iso = next.toISOString().slice(0, 10)
  router.get(route('admin.appointments.calendar'), { week: iso }, { preserveState: false })
}

function goToday() {
  router.get(route('admin.appointments.calendar'), {}, { preserveState: false })
}

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}

function serviceLabel(type: string) {
  const map: Record<string, string> = {
    vet: 'Vet',
    grooming: 'Grooming',
    daycare_booking: 'Daycare',
    boarding: 'Boarding',
    daycare: 'Daycare',
  }
  return map[type] ?? type
}

function serviceColor(type: string) {
  const map: Record<string, string> = {
    vet: 'bg-blue-500',
    grooming: 'bg-pink-500',
    daycare_booking: 'bg-yellow-500',
    boarding: 'bg-green-500',
    daycare: 'bg-yellow-500',
  }
  return map[type] ?? 'bg-gray-400'
}

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'default' {
  if (['confirmed', 'checked_in', 'checked_out'].includes(status)) return 'success'
  if (status === 'pending') return 'warning'
  if (['cancelled', 'no_show'].includes(status)) return 'danger'
  return 'default'
}
</script>
