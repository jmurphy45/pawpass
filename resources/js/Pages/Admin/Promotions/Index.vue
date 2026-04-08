<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-text-body">Promotions</h1>
        <AppButton v-if="isOwner" variant="primary" @click="showForm = !showForm">
          {{ showForm ? 'Cancel' : 'New Promotion' }}
        </AppButton>
      </div>

      <!-- Create form -->
      <AppCard v-if="showForm && isOwner" :padded="true" class="space-y-4">
        <h2 class="text-base font-semibold text-text-body">New Promotion</h2>
        <form @submit.prevent="submitCreate" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Name</label>
              <input v-model="form.name" type="text" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" placeholder="e.g. Summer Special" required />
              <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Code</label>
              <input v-model="form.code" type="text" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition uppercase" placeholder="SUMMER10" required />
              <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Type</label>
              <select v-model="form.type" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                <option value="percentage">Percentage off (%)</option>
                <option value="fixed_cents">Fixed amount off ($)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">
                {{ form.type === 'percentage' ? 'Discount %' : 'Discount Amount ($)' }}
              </label>
              <input v-model.number="discountDisplay" type="number" min="1" :max="form.type === 'percentage' ? 100 : undefined" step="0.01" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" required />
              <p v-if="form.errors.discount_value" class="mt-1 text-sm text-red-600">{{ form.errors.discount_value }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Applies To</label>
              <select v-model="form.applicable_type" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                <option value="">All purchases</option>
                <option value="App\Models\Package">Specific package</option>
                <option value="boarding">Boarding only</option>
                <option value="daycare">Daycare only</option>
              </select>
            </div>
            <div v-if="form.applicable_type === 'App\\Models\\Package'">
              <label class="block text-sm font-medium text-text-body mb-1">Package</label>
              <select v-model="form.applicable_id" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                <option value="">Select a package</option>
                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Min Purchase ($)</label>
              <input v-model.number="minPurchaseDollars" type="number" min="0" step="0.01" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" placeholder="0.00" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Max Uses</label>
              <input v-model.number="form.max_uses" type="number" min="1" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" placeholder="Unlimited" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">Expires At</label>
              <input v-model="form.expires_at" type="datetime-local" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-text-body mb-1">Description (optional)</label>
              <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" placeholder="Internal note or customer-facing description" />
            </div>
          </div>
          <div class="flex gap-3">
            <AppButton type="submit" variant="primary" :disabled="form.processing">Create Promotion</AppButton>
            <AppButton type="button" variant="secondary" @click="showForm = false">Cancel</AppButton>
          </div>
        </form>
      </AppCard>

      <!-- Promotions list -->
      <AppCard class="overflow-hidden">
        <div v-if="promotions.length === 0" class="px-5 py-8 text-center text-sm text-text-muted">
          No promotions yet. Create one to start offering discounts to your customers.
        </div>
        <div v-else class="divide-y divide-border-warm">
          <div v-for="promo in promotions" :key="promo.id" class="px-5 py-4 flex items-start gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-sm font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">{{ promo.code }}</span>
                <span class="text-sm font-medium text-text-body">{{ promo.name }}</span>
                <span v-if="!promo.is_active || promo.is_expired || promo.is_maxed_out" class="text-xs text-red-600 bg-red-50 px-2 py-0.5 rounded">
                  {{ !promo.is_active ? 'Inactive' : promo.is_expired ? 'Expired' : 'Maxed out' }}
                </span>
                <span v-else class="text-xs text-green-700 bg-green-50 px-2 py-0.5 rounded">Active</span>
              </div>
              <p class="mt-1 text-sm text-text-muted">
                {{ promo.type === 'percentage' ? `${promo.discount_value}% off` : `$${(promo.discount_value / 100).toFixed(2)} off` }}
                <span v-if="promo.applicable_type === 'boarding'"> · Boarding only</span>
                <span v-else-if="promo.applicable_type === 'daycare'"> · Daycare only</span>
                <span v-else-if="promo.applicable_type"> · Specific package</span>
                <span v-else> · All purchases</span>
                <span v-if="promo.expires_at"> · Expires {{ new Date(promo.expires_at).toLocaleDateString() }}</span>
              </p>
              <p class="text-xs text-text-muted mt-0.5">
                {{ promo.redemptions_count }} redemption{{ promo.redemptions_count === 1 ? '' : 's' }}
                <span v-if="promo.max_uses"> / {{ promo.max_uses }} max</span>
              </p>
            </div>
            <div v-if="isOwner" class="flex gap-2 shrink-0">
              <AppButton
                v-if="promo.is_active"
                variant="secondary"
                size="sm"
                @click="toggleActive(promo)"
              >
                Deactivate
              </AppButton>
              <AppButton
                v-else
                variant="secondary"
                size="sm"
                @click="toggleActive(promo)"
              >
                Activate
              </AppButton>
              <AppButton variant="danger" size="sm" @click="deletePromo(promo)">Delete</AppButton>
            </div>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'

const page = usePage()

interface Package {
  id: string
  name: string
  type: string
}

interface Promotion {
  id: string
  name: string
  code: string
  type: string
  discount_value: number
  applicable_type: string | null
  applicable_id: string | null
  min_purchase_cents: number
  expires_at: string | null
  max_uses: number | null
  used_count: number
  is_active: boolean
  description: string | null
  redemptions_count: number
  is_expired: boolean
  is_maxed_out: boolean
}

defineProps<{
  promotions: Promotion[]
  packages: Package[]
}>()

const isOwner = computed(() => page.props.auth.user?.role === 'business_owner')
const showForm = ref(false)

const discountDisplay = ref(10)
const minPurchaseDollars = ref(0)

const form = useForm({
  name: '',
  code: '',
  type: 'percentage' as 'percentage' | 'fixed_cents',
  discount_value: 10,
  applicable_type: '' as string,
  applicable_id: '' as string,
  min_purchase_cents: 0,
  expires_at: '',
  max_uses: null as number | null,
  description: '',
})

function submitCreate() {
  form.discount_value = form.type === 'percentage'
    ? Math.round(discountDisplay.value)
    : Math.round(discountDisplay.value * 100)
  form.min_purchase_cents = Math.round(minPurchaseDollars.value * 100)
  form.applicable_type = form.applicable_type || ''
  form.post(route('admin.promotions.store'), {
    onSuccess: () => {
      showForm.value = false
      form.reset()
      discountDisplay.value = 10
      minPurchaseDollars.value = 0
    },
  })
}

function toggleActive(promo: Promotion) {
  useForm({ is_active: !promo.is_active }).patch(route('admin.promotions.update', promo.id))
}

function deletePromo(promo: Promotion) {
  if (confirm(`Delete promotion "${promo.code}"? This cannot be undone.`)) {
    useForm({}).delete(route('admin.promotions.destroy', promo.id))
  }
}
</script>
