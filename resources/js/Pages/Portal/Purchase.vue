<template>
  <PortalLayout>
    <div class="space-y-8">
      <div>
        <h1 class="text-2xl font-bold text-text-body">Buy Credits</h1>
        <p class="text-sm text-text-muted mt-1">Choose a package for your dog</p>
      </div>

      <!-- Two-column layout on desktop -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Package grid -->
        <div class="lg:col-span-2 space-y-4">
          <div
            v-for="pkg in packages"
            :key="pkg.id"
            class="relative rounded-xl border-2 cursor-pointer transition-all duration-200 overflow-hidden"
            :class="selectedPackageId === pkg.id
              ? '-translate-y-0.5'
              : 'hover:-translate-y-0.5'"
            :style="selectedPackageId === pkg.id
              ? { borderColor: accentColor, boxShadow: `0 0 0 3px ${accentColor}22, 0 4px 12px rgba(0,0,0,0.08)` }
              : { borderColor: pkg.is_featured ? accentColor + '66' : '#e5e0d8' }"
            @click="onPackageSelect(pkg.id)"
          >
            <!-- Popular banner -->
            <div
              v-if="pkg.is_featured"
              class="text-white text-center text-xs font-bold py-1.5 tracking-widest uppercase"
              :style="{ backgroundColor: accentColor }"
            >✦ Most Popular</div>

            <div class="p-5 flex items-start gap-4 bg-white">
              <!-- Radio indicator -->
              <div
                class="mt-0.5 h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors duration-150"
                :style="selectedPackageId === pkg.id
                  ? { borderColor: accentColor, backgroundColor: accentColor }
                  : { borderColor: '#e5e0d8' }"
              >
                <svg v-if="selectedPackageId === pkg.id" class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                </svg>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-3 flex-wrap">
                  <p class="font-bold text-text-body text-lg">{{ pkg.name }}</p>
                  <p class="text-2xl font-black text-text-body">
                    ${{ (pkg.price_cents / 100).toFixed(2) }}
                    <span class="text-sm font-normal text-text-muted">{{ pkg.billing_interval ? `/ ${pkg.billing_interval}` : 'one-time' }}</span>
                  </p>
                </div>

                <ul class="mt-2.5 space-y-1.5">
                  <li class="flex items-center gap-2 text-sm text-text-muted">
                    <svg class="h-4 w-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                    </svg>
                    <template v-if="pkg.billing_interval">
                      <template v-if="selectedDogExpiry && selectedPackageId === pkg.id">
                        <span class="text-amber-600">{{ pkg.credits }} credits (expiring {{ selectedDogExpiry }})</span>
                        <span class="text-gray-400">+ {{ pkg.credits }} more on {{ selectedDogRenewal }}</span>
                      </template>
                      <template v-else>{{ pkg.credits }} credits / month</template>
                    </template>
                    <template v-else>{{ pkg.credits }} credits</template>
                  </li>
                  <li v-if="pkg.max_dogs > 1" class="flex items-center gap-2 text-sm text-text-muted">
                    <svg class="h-4 w-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                    </svg>
                    Up to {{ pkg.max_dogs }} dogs
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Checkout panel -->
        <div class="lg:col-span-1">
          <div class="card-padded sticky top-24 space-y-5">
            <h2 class="text-sm font-semibold text-text-body">Checkout</h2>

            <!-- Billing mode toggle (monthly subscription) -->
            <div v-if="recurringCheckoutEnabled && selectedPackage?.has_monthly_price">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wide mb-2">Billing</label>
              <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button
                  type="button"
                  class="flex-1 py-2 font-medium transition-colors"
                  :class="billingMode === 'one_time' ? 'text-white' : 'text-text-muted bg-white hover:bg-gray-50'"
                  :style="billingMode === 'one_time' ? { backgroundColor: accentColor } : {}"
                  @click="billingMode = 'one_time'"
                >Pay Once</button>
                <button
                  type="button"
                  class="flex-1 py-2 font-medium transition-colors"
                  :class="billingMode === 'subscription' ? 'text-white' : 'text-text-muted bg-white hover:bg-gray-50'"
                  :style="billingMode === 'subscription' ? { backgroundColor: accentColor } : {}"
                  @click="billingMode = 'subscription'"
                >Subscribe Monthly</button>
              </div>
            </div>

            <!-- Recurring billing toggle (non-native, for one_time + unlimited packages) -->
            <div v-if="recurringCheckoutEnabled && selectedPackage?.is_recurring_enabled && selectedPackage?.type !== 'subscription'">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wide mb-2">Recurring</label>
              <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button
                  type="button"
                  class="flex-1 py-2 font-medium transition-colors"
                  :class="billingMode !== 'recurring' ? 'text-white' : 'text-text-muted bg-white hover:bg-gray-50'"
                  :style="billingMode !== 'recurring' ? { backgroundColor: accentColor } : {}"
                  @click="billingMode = 'one_time'"
                >Pay Once</button>
                <button
                  type="button"
                  class="flex-1 py-2 font-medium transition-colors"
                  :class="billingMode === 'recurring' ? 'text-white' : 'text-text-muted bg-white hover:bg-gray-50'"
                  :style="billingMode === 'recurring' ? { backgroundColor: accentColor } : {}"
                  @click="billingMode = 'recurring'"
                >Make Recurring</button>
              </div>
            </div>

            <!-- Dog selector -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wide mb-2">For</label>
              <!-- Subscription/recurring mode or single-dog package: dropdown only -->
              <select
                v-if="billingMode === 'subscription' || billingMode === 'recurring' || !selectedPackage || selectedPackage.max_dogs === 1"
                v-model="selectedDogId"
                class="input"
              >
                <option value="">— choose a dog —</option>
                <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
              </select>
              <!-- Multi-dog one-time: checkboxes -->
              <div v-else class="space-y-2">
                <label
                  v-for="dog in dogs"
                  :key="dog.id"
                  class="flex items-center gap-2 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :value="dog.id"
                    v-model="selectedDogIds"
                    :disabled="!selectedDogIds.includes(dog.id) && selectedDogIds.length >= selectedPackage.max_dogs"
                    class="h-4 w-4 rounded border-gray-300"
                    :style="{ accentColor: accentColor }"
                  />
                  <span class="text-sm text-text-body">{{ dog.name }}</span>
                </label>
                <p v-if="selectedPackage" class="text-xs text-text-muted">Select up to {{ selectedPackage.max_dogs }} dogs</p>
              </div>
            </div>

            <!-- Card element / card on file -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wide mb-2">Payment</label>
              <!-- Card-on-file badge (shown when recurring/subscription and saved card exists and user hasn't clicked "Change") -->
              <div
                v-if="savedCard && (billingMode === 'recurring' || billingMode === 'subscription') && !useNewCard"
                class="rounded-lg bg-surface-subtle border border-gray-200 px-3 py-2 text-sm flex items-center gap-2"
              >
                <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <span>{{ capitalize(savedCard.brand) }} ····{{ savedCard.last4 }}</span>
                <button type="button" @click="useNewCard = true" class="ml-auto text-xs text-indigo-600 hover:underline">Change</button>
              </div>
              <!-- Card element (shown when no saved card, or user clicked "Change", or one-time purchase) -->
              <div v-else id="card-element" class="input py-3" />
              <p v-if="cardError" class="mt-1.5 text-xs text-red-600">{{ cardError }}</p>
              <!-- Save card checkbox (shown whenever card element is visible) -->
              <label
                v-if="!savedCard || useNewCard || billingMode === 'one_time'"
                class="mt-2 flex items-center gap-2 text-sm text-text-muted cursor-pointer"
              >
                <input type="checkbox" v-model="saveCard" class="h-4 w-4 rounded border-gray-300" />
                Save card for future purchases
              </label>
            </div>

            <!-- Summary -->
            <div v-if="selectedPackage" class="rounded-lg bg-surface-subtle px-4 py-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-text-muted">{{ selectedPackage.name }}</span>
                <span class="font-semibold text-text-body">
                  ${{ (selectedPackage.price_cents / 100).toFixed(2) }}
                  <span v-if="billingMode === 'subscription'" class="text-xs font-normal text-text-muted">/mo</span>
                </span>
              </div>
              <p v-if="billingMode === 'subscription'" class="text-xs text-text-muted mt-1">Billed monthly · cancel anytime</p>
              <p v-if="billingMode === 'recurring' && selectedPackage.recurring_interval_days" class="text-xs text-text-muted mt-1">
                Billed every {{ selectedPackage.recurring_interval_days }} days · cancel anytime
              </p>
            </div>

            <button
              @click="purchase"
              :disabled="!selectedPackageId || activeDogIds.length === 0 || paying"
              class="btn-primary w-full justify-center py-3 text-base disabled:opacity-40 disabled:cursor-not-allowed"
              :style="{ backgroundColor: accentColor }"
            >
              <svg v-if="paying" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <template v-if="paying">Processing…</template>
              <template v-else-if="selectedPackage && billingMode === 'subscription'">
                Subscribe — ${{ (selectedPackage.price_cents / 100).toFixed(2) }}/mo
              </template>
              <template v-else-if="selectedPackage && billingMode === 'recurring'">
                Subscribe (every {{ selectedPackage.recurring_interval_days ?? selectedPackage.duration_days ?? 30 }}d) — ${{ (selectedPackage.price_cents / 100).toFixed(2) }}
              </template>
              <template v-else-if="selectedPackage">
                Pay ${{ (selectedPackage.price_cents / 100).toFixed(2) }}
              </template>
              <template v-else>Pay Now</template>
            </button>

            <!-- Trust indicator -->
            <p class="text-center text-xs text-text-muted flex items-center justify-center gap-1.5">
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
              </svg>
              Secured by Stripe
            </p>

            <div v-if="success" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm flex items-center gap-2">
              <svg class="h-4 w-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
              </svg>
              <span v-if="billingMode === 'subscription' || billingMode === 'recurring'">Subscription activated! Redirecting…</span>
              <span v-else>Payment successful! Credits will appear shortly.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Package, PageProps } from '@/types';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeCardElement } from '@stripe/stripe-js';

interface DogOption { id: string; name: string; credits_expire_at: string | null; }
interface PurchasePackage extends Package {
  has_monthly_price: boolean;
  is_recurring_enabled: boolean;
  recurring_interval_days: number | null;
}

const props = defineProps<{
  packages: PurchasePackage[];
  dogs: DogOption[];
  stripe_key: string;
  stripe_account_id: string | null;
  recurring_checkout_enabled: boolean;
  saved_card: { last4: string; brand: string } | null;
}>();

const page = usePage<PageProps>();
const accentColor = computed(() => page.props.tenant?.primary_color ?? '#4f46e5');
const recurringCheckoutEnabled = computed(() => props.recurring_checkout_enabled);
const savedCard = computed(() => props.saved_card);

const selectedPackageId = ref('');
const selectedDogId = ref('');
const selectedDogIds = ref<string[]>([]);
const billingMode = ref<'one_time' | 'subscription' | 'recurring'>('one_time');
const paying = ref(false);
const saveCard = ref(false);
const useNewCard = ref(false);

function capitalize(str: string): string {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
}

const selectedPackage = computed(() => props.packages.find(p => p.id === selectedPackageId.value) ?? null);
const selectedDog = computed(() => props.dogs.find(d => d.id === selectedDogId.value) ?? null);

// Reset billing mode to one_time when selecting a package without applicable recurring options
function onPackageSelect(id: string) {
  selectedPackageId.value = id;
  const pkg = props.packages.find(p => p.id === id);
  if (!pkg?.has_monthly_price && !pkg?.is_recurring_enabled) billingMode.value = 'one_time';
  if (billingMode.value === 'recurring' && !pkg?.is_recurring_enabled) billingMode.value = 'one_time';
  if (billingMode.value === 'subscription' && !pkg?.has_monthly_price) billingMode.value = 'one_time';
}

// The dog IDs to actually submit
const activeDogIds = computed(() => {
  if (!selectedPackage.value) return [];
  if (billingMode.value === 'subscription' || billingMode.value === 'recurring') {
    return selectedDogId.value ? [selectedDogId.value] : [];
  }
  return selectedPackage.value.max_dogs === 1
    ? (selectedDogId.value ? [selectedDogId.value] : [])
    : selectedDogIds.value;
});

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

const selectedDogExpiry = computed(() => {
  const exp = selectedDog.value?.credits_expire_at;
  return exp ? fmtDate(exp) : null;
});

const selectedDogRenewal = computed(() => {
  const exp = selectedDog.value?.credits_expire_at;
  if (!exp) return null;
  const d = new Date(exp);
  d.setDate(d.getDate() + 30);
  return fmtDate(d.toISOString());
});

const success = ref(false);
const cardError = ref('');

let stripe: Stripe | null = null;
let cardElement: StripeCardElement | null = null;

function mountCardElement() {
  if (!stripe) return;
  const el = document.getElementById('card-element');
  if (!el) return;
  if (cardElement) {
    cardElement.mount('#card-element');
    return;
  }
  const elements = stripe.elements();
  cardElement = elements.create('card', {
    style: { base: { fontSize: '14px', color: '#2a2522', fontFamily: 'Instrument Sans, sans-serif' } },
  });
  cardElement.mount('#card-element');
}

onMounted(async () => {
  if (!props.stripe_key) return;
  const opts = props.stripe_account_id ? { stripeAccount: props.stripe_account_id } : {};
  stripe = await loadStripe(props.stripe_key, opts);
  if (!stripe) return;
  // Only mount immediately if card element should be visible
  if (!savedCard.value || billingMode.value === 'one_time') {
    mountCardElement();
  }
});

// When user clicks "Change card", lazily mount the card element
watch(useNewCard, (val) => {
  if (val) {
    // Wait for DOM to update
    setTimeout(() => mountCardElement(), 0);
  }
});

async function purchase() {
  // Allow purchase without cardElement when using card on file
  const usingCardOnFile = savedCard.value && (billingMode.value === 'recurring' || billingMode.value === 'subscription') && !useNewCard.value;
  if (!selectedPackageId.value || activeDogIds.value.length === 0 || !stripe || (!cardElement && !usingCardOnFile)) return;

  paying.value = true;
  cardError.value = '';

  try {
    const resp = await fetch(route('portal.purchase.store'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
      },
      body: JSON.stringify({
        package_id: selectedPackageId.value,
        dog_ids: activeDogIds.value,
        billing_mode: billingMode.value,
        save_card: saveCard.value,
      }),
    });

    if (!resp.ok) {
      const data = await resp.json();
      cardError.value = data.message ?? 'Something went wrong.';
      return;
    }

    const data = await resp.json();

    // Fast path: server created subscription directly (card on file)
    if (data.fast) {
      success.value = true;
      setTimeout(() => router.visit(route('portal.history')), 3000);
      return;
    }

    const { client_secret } = data;

    if (billingMode.value === 'subscription' || billingMode.value === 'recurring') {
      const result = await stripe.confirmCardSetup(client_secret, {
        payment_method: { card: cardElement! },
      });

      if (result.error) {
        cardError.value = result.error.message ?? 'Setup failed.';
      } else {
        success.value = true;
        setTimeout(() => router.visit(route('portal.history')), 3000);
      }
    } else {
      const result = await stripe.confirmCardPayment(client_secret, {
        payment_method: { card: cardElement! },
      });

      if (result.error) {
        cardError.value = result.error.message ?? 'Payment failed.';
      } else {
        await fetch(route('portal.purchase.confirm'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
          },
          body: JSON.stringify({ payment_intent_id: result.paymentIntent.id, save_card: saveCard.value }),
        });
        router.visit(route('portal.history'));
      }
    }
  } catch {
    cardError.value = 'An unexpected error occurred.';
  } finally {
    paying.value = false;
  }
}
</script>
