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
                    <span class="text-sm font-normal text-text-muted">one-time</span>
                  </p>
                </div>

                <ul class="mt-2.5 space-y-1.5">
                  <li class="flex items-center gap-2 text-sm text-text-muted">
                    <svg class="h-4 w-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                    </svg>
                    {{ pkg.credits }} credits
                  </li>
                  <li v-if="pkg.max_dogs > 1" class="flex items-center gap-2 text-sm text-text-muted">
                    <svg class="h-4 w-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                    </svg>
                    Up to {{ pkg.max_dogs }} dogs
                  </li>
                  <li v-if="pkg.is_auto_replenish_eligible" class="flex items-center gap-2 text-sm text-text-muted">
                    <svg class="h-4 w-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Auto-replenish available
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

            <!-- Dog selector -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wide mb-2">For</label>
              <!-- Single-dog package: dropdown -->
              <select
                v-if="!selectedPackage || selectedPackage.max_dogs === 1"
                v-model="selectedDogId"
                class="input"
              >
                <option value="">— choose a dog —</option>
                <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
              </select>
              <!-- Multi-dog: checkboxes -->
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
              <div v-if="savedCard && !useNewCard && !showPaymentForm" class="rounded-lg bg-surface-subtle border border-gray-200 px-3 py-2 text-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <span>{{ capitalize(savedCard.brand) }} ····{{ savedCard.last4 }}</span>
                <button type="button" @click="useNewCard = true" class="ml-auto text-xs text-indigo-600 hover:underline">Change</button>
              </div>
              <div v-show="showPaymentForm" id="card-element" class="input py-3" />
              <p v-if="cardError" class="mt-1.5 text-xs text-red-600">{{ cardError }}</p>
              <label class="mt-2 flex items-center gap-2 text-sm text-text-muted cursor-pointer">
                <input type="checkbox" v-model="saveCard" class="h-4 w-4 rounded border-gray-300" />
                Save card for future purchases
              </label>
            </div>

            <!-- Auto-replenish toggle -->
            <div v-if="autoReplenishEnabled && selectedPackage?.is_auto_replenish_eligible && activeDogIds.length === 1">
              <label class="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="autoReplenish"
                  class="mt-0.5 h-4 w-4 rounded border-gray-300 shrink-0"
                  :style="{ accentColor: accentColor }"
                  @change="onAutoReplenishChange"
                />
                <span class="text-sm text-text-body">
                  Auto-replenish when credits run out
                  <span class="block text-xs text-text-muted mt-0.5">Card saved securely · cancel anytime from your account</span>
                </span>
              </label>
            </div>

            <!-- Summary -->
            <div v-if="selectedPackage" class="rounded-lg bg-surface-subtle px-4 py-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-text-muted">{{ selectedPackage.name }}</span>
                <span class="font-semibold text-text-body">${{ (selectedPackage.price_cents / 100).toFixed(2) }}</span>
              </div>
              <template v-if="tax_enabled && taxCents > 0">
                <div class="flex items-center justify-between text-text-muted mt-1">
                  <span>Est. tax</span>
                  <span>${{ (taxCents / 100).toFixed(2) }}</span>
                </div>
                <div class="flex items-center justify-between font-semibold border-t border-gray-200 pt-2 mt-2">
                  <span>Total</span>
                  <span>${{ ((selectedPackage.price_cents + taxCents) / 100).toFixed(2) }}</span>
                </div>
              </template>
              <p v-if="autoReplenish" class="text-xs text-text-muted mt-1">Re-purchased automatically when credits reach zero · cancel anytime</p>
            </div>

            <button
              v-if="!showPaymentForm"
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
              <template v-else-if="selectedPackage">Pay ${{ (displayTotal / 100).toFixed(2) }}</template>
              <template v-else>Pay Now</template>
            </button>
            <button
              v-if="showPaymentForm"
              @click="confirmNewCard"
              :disabled="paying"
              class="btn-primary w-full justify-center py-3 text-base disabled:opacity-40 disabled:cursor-not-allowed"
              :style="{ backgroundColor: accentColor }"
            >
              <svg v-if="paying" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <template v-if="paying">Processing…</template>
              <template v-else>Confirm Payment</template>
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
              Payment successful! Credits will appear shortly.
            </div>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Package, PageProps } from '@/types';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeElements, StripePaymentElement } from '@stripe/stripe-js';

interface DogOption { id: string; name: string; credits_expire_at: string | null; auto_replenish_enabled: boolean; auto_replenish_package_id: string | null; }
interface PurchasePackage extends Package {
  is_auto_replenish_eligible: boolean;
}

const props = defineProps<{
  packages: PurchasePackage[];
  dogs: DogOption[];
  stripe_key: string;
  stripe_account_id: string | null;
  auto_replenish_enabled: boolean;
  tax_enabled: boolean;
  saved_card: { last4: string; brand: string } | null;
}>();

const page = usePage<PageProps>();
const accentColor = computed(() => page.props.tenant?.primary_color ?? '#4f46e5');
const autoReplenishEnabled = computed(() => props.auto_replenish_enabled);
const savedCard = computed(() => props.saved_card);

const selectedPackageId = ref('');
const selectedDogId = ref('');
const selectedDogIds = ref<string[]>([]);
const paying = ref(false);
const saveCard = ref(false);
const useNewCard = ref(false);
const autoReplenish = ref(false);

function capitalize(str: string): string {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
}

const selectedPackage = computed(() => props.packages.find(p => p.id === selectedPackageId.value) ?? null);

function onPackageSelect(id: string) {
  selectedPackageId.value = id;
  autoReplenish.value = false;
}

// When auto-replenish is checked, ensure card will be saved
function onAutoReplenishChange() {
  if (autoReplenish.value) {
    saveCard.value = true;
  }
}

const activeDogIds = computed(() => {
  if (!selectedPackage.value) return [];
  return selectedPackage.value.max_dogs === 1
    ? (selectedDogId.value ? [selectedDogId.value] : [])
    : selectedDogIds.value;
});

const success = ref(false);
const cardError = ref('');
const taxCents = ref(0);
const taxLoading = ref(false);

const displayTotal = computed(() => {
  if (!selectedPackage.value) return 0;
  return props.tax_enabled && taxCents.value > 0
    ? selectedPackage.value.price_cents + taxCents.value
    : selectedPackage.value.price_cents;
});

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

let stripe: Stripe | null = null;
let paymentElements: StripeElements | null = null;
let paymentElement: StripePaymentElement | null = null;
const showPaymentForm = ref(false);

onMounted(async () => {
  if (!props.stripe_key) return;
  const opts = props.stripe_account_id ? { stripeAccount: props.stripe_account_id } : {};
  stripe = await loadStripe(props.stripe_key, opts);
});

async function mountPaymentElement(clientSecret: string) {
  if (!stripe) return;
  paymentElement?.destroy();
  paymentElement = null;
  paymentElements = stripe.elements({ clientSecret });
  paymentElement = paymentElements.create('payment', { layout: 'tabs' });
  await nextTick();
  paymentElement.mount('#card-element');
}

watch([selectedPackageId, activeDogIds], () => {
  if (showPaymentForm.value) {
    showPaymentForm.value = false;
    paymentElement?.destroy();
    paymentElement = null;
    paymentElements = null;
  }
});

watch(selectedPackageId, async (pkgId) => {
  taxCents.value = 0;
  if (!pkgId || !props.tax_enabled) return;
  taxLoading.value = true;
  try {
    const resp = await fetch(route('portal.purchase.tax-preview') + `?package_id=${pkgId}`, {
      headers: { 'X-CSRF-TOKEN': getCsrfToken() },
    });
    if (resp.ok) {
      const data = await resp.json();
      taxCents.value = data.tax_cents ?? 0;
    }
  } catch {
    // silently ignore — no tax shown
  } finally {
    taxLoading.value = false;
  }
});

async function callConfirmEndpoint(paymentIntentId: string) {
  await fetch(route('portal.purchase.confirm'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
    body: JSON.stringify({
      payment_intent_id: paymentIntentId,
      save_card: saveCard.value,
      auto_replenish: autoReplenish.value,
    }),
  });
  success.value = true;
  showPaymentForm.value = false;
  paymentElement?.destroy();
  paymentElement = null;
  paymentElements = null;
  setTimeout(() => router.visit(route('portal.history')), 2000);
}

async function purchase() {
  const usingCardOnFile = !!(savedCard.value && !useNewCard.value);
  if (!selectedPackageId.value || activeDogIds.value.length === 0 || !stripe) return;

  paying.value = true;
  cardError.value = '';

  try {
    const resp = await fetch(route('portal.purchase.store'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
      body: JSON.stringify({
        package_id: selectedPackageId.value,
        dog_ids: activeDogIds.value,
        save_card: saveCard.value,
      }),
    });

    if (!resp.ok) {
      if (resp.status === 419) {
        cardError.value = 'Session expired — please refresh the page and try again.';
      } else {
        try {
          const err = await resp.json();
          cardError.value = err.message ?? 'Something went wrong.';
        } catch {
          cardError.value = 'Something went wrong.';
        }
      }
      return;
    }

    const { client_secret, payment_method_id } = await resp.json();

    if (usingCardOnFile) {
      const result = await stripe.confirmCardPayment(client_secret, {
        payment_method: payment_method_id,
      });
      if (result.error) {
        cardError.value = 'Your saved card could not be charged. Please enter a new card.';
        useNewCard.value = true;
        showPaymentForm.value = true;
        await mountPaymentElement(client_secret);
      } else {
        await callConfirmEndpoint(result.paymentIntent!.id);
      }
    } else {
      showPaymentForm.value = true;
      await mountPaymentElement(client_secret);
    }
  } catch {
    cardError.value = 'An unexpected error occurred.';
  } finally {
    paying.value = false;
  }
}

async function confirmNewCard() {
  if (!stripe || !paymentElements) return;
  paying.value = true;
  cardError.value = '';
  try {
    const result = await stripe.confirmPayment({
      elements: paymentElements,
      confirmParams: { return_url: window.location.href },
      redirect: 'if_required',
    });
    if (result.error) {
      cardError.value = result.error.message ?? 'Payment failed.';
    } else {
      await callConfirmEndpoint(result.paymentIntent!.id);
    }
  } catch {
    cardError.value = 'An unexpected error occurred.';
  } finally {
    paying.value = false;
  }
}
</script>
