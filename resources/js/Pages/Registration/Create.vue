<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto py-12 px-4">
      <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-900">Start your free trial</h1>
        <p class="mt-2 text-gray-600">{{ trialDays }}-day free trial, no credit card required.</p>
      </div>

      <!-- Step indicator -->
      <div class="flex items-center justify-center mb-10 gap-4">
        <div
          v-for="(label, i) in ['Pick a Plan', 'Business Details', 'Your Account']"
          :key="i"
          class="flex items-center gap-2"
        >
          <div
            class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-semibold"
            :class="step === i + 1 ? 'bg-indigo-600 text-white' : step > i + 1 ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500'"
          >
            {{ i + 1 }}
          </div>
          <span class="text-sm" :class="step === i + 1 ? 'font-semibold text-gray-900' : 'text-gray-500'">
            {{ label }}
          </span>
          <span v-if="i < 2" class="text-gray-300">›</span>
        </div>
      </div>

      <!-- Step 1: Pick plan -->
      <div v-if="step === 1">
        <div class="flex justify-center mb-6">
          <div class="bg-gray-100 rounded-full p-1 flex">
            <button
              class="px-4 py-1 rounded-full text-sm font-medium transition"
              :class="billingCycle === 'monthly' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
              @click="billingCycle = 'monthly'"
            >Monthly</button>
            <button
              class="px-4 py-1 rounded-full text-sm font-medium transition"
              :class="billingCycle === 'annual' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
              @click="billingCycle = 'annual'"
            >Annual <span class="text-green-600 text-xs font-semibold">Save 20%</span></button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div
            v-for="plan in plans"
            :key="plan.slug"
            class="bg-white rounded-xl border-2 p-6 cursor-pointer transition"
            :class="selectedPlan === plan.slug ? 'border-indigo-600 shadow-lg' : 'border-gray-200 hover:border-indigo-300'"
            @click="selectedPlan = plan.slug"
          >
            <h3 class="text-lg font-bold text-gray-900">{{ plan.name }}</h3>
            <p class="text-sm text-gray-500 mt-1 mb-4">{{ plan.description }}</p>
            <p class="text-3xl font-extrabold text-gray-900">
              ${{ billingCycle === 'monthly' ? (plan.monthly_price_cents / 100).toFixed(0) : (plan.annual_price_cents / 100 / 12).toFixed(0) }}
              <span class="text-base font-normal text-gray-500">/mo</span>
            </p>
            <p v-if="billingCycle === 'annual'" class="text-xs text-gray-400 mb-4">
              Billed ${{ (plan.annual_price_cents / 100).toFixed(0) }}/year
            </p>
            <ul class="mt-4 space-y-1">
              <li
                v-for="feature in plan.features"
                :key="typeof feature === 'string' ? feature : feature.slug"
                class="flex items-center gap-2 text-sm text-gray-600"
              >
                <span class="text-green-500">✓</span>
                {{ typeof feature === 'string' ? feature.replace(/_/g, ' ') : feature.name }}
              </li>
            </ul>
            <button
              class="mt-6 w-full py-2 rounded-lg text-sm font-semibold transition"
              :class="selectedPlan === plan.slug ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-indigo-50'"
              @click.stop="selectedPlan = plan.slug; step = 2"
            >
              Start free trial
            </button>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <button
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40"
            :disabled="!selectedPlan"
            @click="step = 2"
          >
            Continue
          </button>
        </div>
      </div>

      <!-- Step 2: Business details -->
      <div v-if="step === 2" class="max-w-lg mx-auto bg-white rounded-xl border border-gray-200 p-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Business Details</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
            <input
              v-model="form.business_name"
              type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="Happy Paws Daycare"
              @input="autoSlug"
            />
            <p v-if="errors.business_name" class="mt-1 text-xs text-red-600">{{ errors.business_name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Subdomain</label>
            <div class="flex items-center">
              <input
                v-model="form.slug"
                type="text"
                class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="happy-paws"
              />
              <span class="bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg px-3 py-2 text-sm text-gray-500">.{{ appDomain }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ form.slug ? form.slug + '.' + appDomain : 'yourname.' + appDomain }}</p>
            <p v-if="errors.slug" class="mt-1 text-xs text-red-600">{{ errors.slug }}</p>
          </div>
        </div>

        <div class="flex justify-between mt-8">
          <button class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900" @click="step = 1">Back</button>
          <button
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40"
            :disabled="!form.business_name || !form.slug"
            @click="step = 3"
          >
            Continue
          </button>
        </div>
      </div>

      <!-- Step 3: Account details -->
      <div v-if="step === 3" class="max-w-lg mx-auto bg-white rounded-xl border border-gray-200 p-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Your Account</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
            <input
              v-model="form.owner_name"
              type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="Jane Smith"
            />
            <p v-if="errors.owner_name" class="mt-1 text-xs text-red-600">{{ errors.owner_name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              v-model="form.email"
              type="email"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="jane@happypaws.com"
            />
            <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              v-model="form.password"
              type="password"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <p v-if="errors.password_confirmation" class="mt-1 text-xs text-red-600">{{ errors.password_confirmation }}</p>
          </div>
        </div>

        <div v-if="Object.keys(errors).length && step === 3" class="mt-4 p-3 bg-red-50 rounded-lg">
          <p v-for="(msg, field) in errors" :key="field" class="text-xs text-red-600">{{ msg }}</p>
        </div>

        <div class="flex justify-between mt-8">
          <button class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900" @click="step = 2">Back</button>
          <button
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40"
            :disabled="submitting"
            @click="submit"
          >
            {{ submitting ? 'Creating account...' : 'Start free trial' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'

const appDomain = import.meta.env.VITE_APP_DOMAIN as string

const props = defineProps<{
  plans: Array<{
    id: string
    slug: string
    name: string
    description: string | null
    monthly_price_cents: number
    annual_price_cents: number
    features: Array<{ slug: string; name: string } | string>
    sort_order: number
  }>
  trialDays: number
}>()

const step = ref(1)
const billingCycle = ref<'monthly' | 'annual'>('monthly')
const selectedPlan = ref('')
const submitting = ref(false)
const errors = reactive<Record<string, string>>({})

const form = reactive({
  business_name: '',
  slug: '',
  owner_name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

function autoSlug() {
  form.slug = form.business_name
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .substring(0, 63)
}

function submit() {
  Object.keys(errors).forEach(k => delete errors[k])
  submitting.value = true

  router.post('/register', {
    ...form,
    plan: selectedPlan.value,
    billing_cycle: billingCycle.value,
  }, {
    onError: (errs) => {
      Object.assign(errors, errs)
      submitting.value = false
    },
    onFinish: () => {
      submitting.value = false
    },
  })
}
</script>
