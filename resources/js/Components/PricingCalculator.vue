<template>
  <section style="background-color: #111110;" class="px-6 py-24 relative overflow-hidden">
    <!-- Subtle radial glow -->
    <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(ellipse 80% 50% at 50% 0%, rgba(79,70,229,0.07) 0%, transparent 70%);"></div>

    <div class="relative mx-auto max-w-7xl">

      <!-- ── Header ────────────────────────────────────────── -->
      <div class="text-center mb-14">
        <span class="inline-block rounded-full border border-indigo-500/30 bg-indigo-500/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-400 mb-5">
          Cost Estimator
        </span>
        <h2 class="text-4xl font-extrabold tracking-tight text-white md:text-5xl">
          Calculate your monthly cost
        </h2>
        <p class="mt-4 text-lg text-white/50 max-w-xl mx-auto">
          Adjust your expected volume below to find the best plan for your business.
        </p>
      </div>

      <!-- ── Sliders ─────────────────────────────────────── -->
      <div class="max-w-2xl mx-auto mb-14 space-y-8">

        <!-- Slider 1: Transaction count -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-white/70">Monthly credit pack sales</span>
            <span class="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-0.5 text-xs font-semibold text-indigo-300 tabular-nums">
              {{ transactionCount }} sales
            </span>
          </div>
          <input
            v-model.number="transactionCount"
            type="range"
            min="0"
            max="500"
            step="1"
            class="w-full h-1.5 rounded-full appearance-none cursor-pointer"
            style="accent-color: #4f46e5; background: linear-gradient(to right, #4f46e5 0%, #4f46e5 calc(var(--pct-tx) * 1%), rgba(255,255,255,0.1) calc(var(--pct-tx) * 1%), rgba(255,255,255,0.1) 100%); --pct-tx: calc(var(--tx, 50) / 5);"
            :style="{ '--tx': transactionCount }"
          />
          <div class="flex justify-between mt-1.5 text-xs text-white/20">
            <span>0</span><span>500</span>
          </div>
        </div>

        <!-- Slider 2: Average order size -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-white/70">Average order size</span>
            <span class="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-0.5 text-xs font-semibold text-indigo-300 tabular-nums">
              ${{ avgOrderSize }}
            </span>
          </div>
          <input
            v-model.number="avgOrderSize"
            type="range"
            min="10"
            max="500"
            step="5"
            class="w-full h-1.5 rounded-full appearance-none cursor-pointer"
            style="accent-color: #4f46e5;"
            :style="{ '--ord': avgOrderSize }"
          />
          <div class="flex justify-between mt-1.5 text-xs text-white/20">
            <span>$10</span><span>$500</span>
          </div>
        </div>

      </div>

      <!-- ── Result cards ───────────────────────────────────── -->
      <div v-if="estimates.length" class="grid gap-5 md:grid-cols-3 max-w-5xl mx-auto">
        <div
          v-for="(estimate, i) in estimates"
          :key="estimate.name"
          class="rounded-2xl p-6 flex flex-col transition-all duration-300"
          :class="i === cheapestIndex
            ? 'border-2 border-emerald-500 bg-white/5 shadow-lg shadow-emerald-500/10'
            : 'border border-white/10 bg-white/[0.03]'"
        >
          <!-- Plan name + badge -->
          <div class="flex items-center gap-2 flex-wrap mb-4">
            <span class="text-lg font-bold text-white">{{ estimate.name }}</span>
            <span
              v-if="i === cheapestIndex"
              class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
            >
              Best value for you
            </span>
          </div>

          <!-- Cost breakdown -->
          <div class="space-y-1.5 flex-1">
            <p class="text-sm text-white/50">
              {{ fmt(estimate.subscriptionCost) }}/mo subscription
            </p>
            <p v-if="transactionCount > 0" class="text-sm text-white/50">
              {{ fmt(estimate.feeCost) }} in platform fees
            </p>
            <p v-else class="text-sm text-white/30 italic">
              Enter volume to see fees
            </p>
          </div>

          <!-- Divider -->
          <div class="border-t border-white/10 my-4"></div>

          <!-- Total -->
          <div>
            <p class="text-2xl font-extrabold text-white tabular-nums">
              {{ fmt(estimate.totalCost) }}<span class="text-base font-normal text-white/40">/mo estimated</span>
            </p>
            <p v-if="transactionCount > 0" class="text-xs text-white/30 mt-1">
              on ${{ (transactionCount * avgOrderSize).toLocaleString() }} monthly volume
            </p>
          </div>
        </div>
      </div>

      <!-- ── Savings callout ───────────────────────────────── -->
      <div v-if="savingsVsStarter > 0" class="mt-10 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-5 py-2.5">
          <svg class="w-4 h-4 text-emerald-400 shrink-0" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/>
            <path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span class="text-sm text-emerald-400">
            Upgrading to {{ estimates[cheapestIndex]?.name }} saves you {{ fmt(savingsVsStarter) }}/mo vs. Starter at this volume
          </span>
        </span>
      </div>

      <!-- ── Annual note ────────────────────────────────────── -->
      <p class="text-center text-xs text-white/30 mt-8">
        All estimates use monthly pricing. See annual plans above for a 20% discount.
      </p>

    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface Plan {
  name: string
  price: string
  monthly_price: number
  featured: boolean
  cta: string
  features: Array<{ slug: string; name: string } | string>
  transaction_fee_pct: number
}

const props = defineProps<{ plans: Plan[] }>()

const transactionCount = ref(50)
const avgOrderSize = ref(100)

const estimates = computed(() =>
  props.plans.map(plan => {
    const feeCost = transactionCount.value * avgOrderSize.value * (plan.transaction_fee_pct / 100)
    const totalCost = plan.monthly_price + feeCost
    return { name: plan.name, subscriptionCost: plan.monthly_price, feeCost, totalCost }
  })
)

const cheapestIndex = computed(() =>
  estimates.value.reduce((minIdx, e, i, arr) =>
    e.totalCost < arr[minIdx].totalCost ? i : minIdx, 0)
)

const savingsVsStarter = computed(() =>
  cheapestIndex.value === 0 ? 0 :
  estimates.value[0].totalCost - estimates.value[cheapestIndex.value].totalCost
)

function fmt(n: number): string {
  return '$' + n.toFixed(2).replace(/\.00$/, '')
}
</script>
