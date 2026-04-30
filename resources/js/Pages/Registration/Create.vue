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
          v-for="(label, i) in ['Pick a Plan', 'Business Details', 'Your Account', 'Billing']"
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
          <span v-if="i < 3" class="text-gray-300">›</span>
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
            <p class="text-xs text-gray-400 mt-1">2.9% + 30¢ + {{ plan.platform_fee_pct }}% platform fee per transaction</p>
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

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <select
              v-model="form.timezone"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option v-for="tz in timezones" :key="tz.id" :value="tz.id">{{ tz.name }}</option>
            </select>
            <p v-if="errors.timezone" class="mt-1 text-xs text-red-600">{{ errors.timezone }}</p>
          </div>
        </div>

        <div class="flex justify-between mt-8">
          <button class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900" @click="step = 1">Back</button>
          <button
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40"
            :disabled="!form.business_name || !form.slug || !form.timezone"
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
        </div>

        <div class="flex justify-between mt-8">
          <button class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900" @click="step = 2">Back</button>
          <button
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40"
            :disabled="!form.owner_name || !form.email"
            @click="step = 4"
          >
            Continue
          </button>
        </div>
      </div>

      <!-- Step 4: Billing details -->
      <div v-if="step === 4" class="max-w-lg mx-auto bg-white rounded-xl border border-gray-200 p-8">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Billing Details</h2>
        <p class="text-sm text-gray-500 mb-6">Used for tax purposes. No credit card required during your free trial.</p>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label>
            <input
              v-model="form.billing_street"
              type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="123 Main St"
            />
            <p v-if="errors['billing_address.street']" class="mt-1 text-xs text-red-600">{{ errors['billing_address.street'] }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
              <input
                v-model="form.billing_city"
                type="text"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Springfield"
              />
              <p v-if="errors['billing_address.city']" class="mt-1 text-xs text-red-600">{{ errors['billing_address.city'] }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">State / Province</label>
              <AppSelect
                v-if="billingStateOptions.length > 0"
                v-model="form.billing_state"
                :options="billingStateOptions"
                placeholder="Select state/province"
                :error="errors['billing_address.state'] ?? null"
              />
              <div v-else>
                <input
                  v-model="form.billing_state"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="State / Province"
                />
                <p v-if="errors['billing_address.state']" class="mt-1 text-xs text-red-600">{{ errors['billing_address.state'] }}</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
              <input
                v-model="form.billing_postal_code"
                type="text"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="62701"
              />
              <p v-if="errors['billing_address.postal_code']" class="mt-1 text-xs text-red-600">{{ errors['billing_address.postal_code'] }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
              <select
                v-model="form.billing_country"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="US">United States</option>
                <option value="CA">Canada</option>
                <option value="GB">United Kingdom</option>
                <option value="AU">Australia</option>
                <option value="NZ">New Zealand</option>
              </select>
              <p v-if="errors['billing_address.country']" class="mt-1 text-xs text-red-600">{{ errors['billing_address.country'] }}</p>
            </div>
          </div>
        </div>

        <div v-if="Object.keys(errors).length" class="mt-4 p-3 bg-red-50 rounded-lg">
          <p v-for="(msg, field) in errors" :key="field" class="text-xs text-red-600">{{ msg }}</p>
        </div>

        <div class="flex justify-between mt-8">
          <button class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900" @click="step = 3">Back</button>
          <button
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40"
            :disabled="submitting || !form.billing_street || !form.billing_city || !form.billing_postal_code"
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
import { ref, reactive, computed, watch } from 'vue'

const appDomain = import.meta.env.VITE_APP_DOMAIN as string

type StateOption = { value: string; label: string }

const props = defineProps<{
  plans: Array<{
    id: string
    slug: string
    name: string
    description: string | null
    monthly_price_cents: number
    annual_price_cents: number
    features: Array<{ slug: string; name: string } | string>
    platform_fee_pct: number
    sort_order: number
  }>
  trialDays: number
  us_states: StateOption[]
  ca_provinces: StateOption[]
  timezones: Array<{ id: string; name: string }>
}>()

const step = ref(1)
const billingCycle = ref<'monthly' | 'annual'>('monthly')
const selectedPlan = ref('')
const submitting = ref(false)
const errors = reactive<Record<string, string>>({})

const form = reactive({
  business_name: '',
  slug: '',
  timezone: 'America/Chicago',
  owner_name: '',
  email: '',
  billing_street: '',
  billing_city: '',
  billing_state: '',
  billing_postal_code: '',
  billing_country: 'US',
})

const billingStateOptions = computed<StateOption[]>(() => {
  if (form.billing_country === 'US') return props.us_states
  if (form.billing_country === 'CA') return props.ca_provinces
  return []
})

watch(() => form.billing_country, () => {
  form.billing_state = ''
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
    business_name: form.business_name,
    slug: form.slug,
    timezone: form.timezone,
    owner_name: form.owner_name,
    email: form.email,
    plan: selectedPlan.value,
    billing_cycle: billingCycle.value,
    billing_address: {
      street: form.billing_street,
      city: form.billing_city,
      state: form.billing_state,
      postal_code: form.billing_postal_code,
      country: form.billing_country,
    },
  }, {
    onError: (errs) => {
      Object.assign(errors, errs)
      submitting.value = false
      // If billing errors, stay on step 4; account errors go back to step 3
      const hasBillingError = Object.keys(errs).some(k => k.startsWith('billing_address'))
      const hasAccountError = Object.keys(errs).some(k => ['owner_name', 'email'].includes(k))
      const hasBusinessError = Object.keys(errs).some(k => ['business_name', 'slug', 'timezone'].includes(k))
      if (hasBillingError) step.value = 4
      else if (hasAccountError) step.value = 3
      else if (hasBusinessError) step.value = 2
    },
    onFinish: () => {
      submitting.value = false
    },
  })
}
</script>
