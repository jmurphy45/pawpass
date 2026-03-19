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
            @click="selectedPackageId = pkg.id"
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

            <!-- Dog selector -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wide mb-2">For</label>
              <select v-model="selectedDogId" class="input">
                <option value="">— choose a dog —</option>
                <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
              </select>
            </div>

            <!-- Card element -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wide mb-2">Payment</label>
              <div id="card-element" class="input py-3" />
              <p v-if="cardError" class="mt-1.5 text-xs text-red-600">{{ cardError }}</p>
            </div>

            <!-- Summary -->
            <div v-if="selectedPackage" class="rounded-lg bg-surface-subtle px-4 py-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-text-muted">{{ selectedPackage.name }}</span>
                <span class="font-semibold text-text-body">${{ (selectedPackage.price_cents / 100).toFixed(2) }}</span>
              </div>
            </div>

            <button
              @click="purchase"
              :disabled="!selectedPackageId || !selectedDogId || paying"
              class="btn-primary w-full justify-center py-3 text-base disabled:opacity-40 disabled:cursor-not-allowed"
              :style="{ backgroundColor: accentColor }"
            >
              <svg v-if="paying" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ paying ? 'Processing…' : selectedPackage ? `Pay $${(selectedPackage.price_cents / 100).toFixed(2)}` : 'Pay Now' }}
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
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Package, PageProps } from '@/types';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeCardElement } from '@stripe/stripe-js';

interface DogOption { id: string; name: string; credits_expire_at: string | null; }

const props = defineProps<{
  packages: Package[];
  dogs: DogOption[];
  stripe_key: string;
  stripe_account_id: string | null;
}>();

const page = usePage<PageProps>();
const accentColor = computed(() => page.props.tenant?.primary_color ?? '#4f46e5');

const selectedPackageId = ref('');
const selectedDogId = ref('');
const paying = ref(false);

const selectedPackage = computed(() => props.packages.find(p => p.id === selectedPackageId.value) ?? null);
const selectedDog = computed(() => props.dogs.find(d => d.id === selectedDogId.value) ?? null);

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

onMounted(async () => {
  if (!props.stripe_key) return;
  stripe = await loadStripe(props.stripe_key, props.stripe_account_id ? { stripeAccount: props.stripe_account_id } : undefined);
  if (!stripe) return;
  const elements = stripe.elements();
  cardElement = elements.create('card', {
    style: { base: { fontSize: '14px', color: '#2a2522', fontFamily: 'Instrument Sans, sans-serif' } },
  });
  cardElement.mount('#card-element');
});

async function purchase() {
  if (!selectedPackageId.value || !selectedDogId.value || !stripe || !cardElement) return;

  paying.value = true;
  cardError.value = '';

  try {
    const resp = await fetch(route('portal.purchase.store'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        'X-Inertia': '1',
      },
      body: JSON.stringify({ package_id: selectedPackageId.value, dog_id: selectedDogId.value }),
    });

    if (!resp.ok) {
      const data = await resp.json();
      cardError.value = data.message ?? 'Something went wrong.';
      return;
    }

    const { client_secret } = await resp.json();

    const result = await stripe.confirmCardPayment(client_secret, {
      payment_method: { card: cardElement },
    });

    if (result.error) {
      cardError.value = result.error.message ?? 'Payment failed.';
    } else {
      success.value = true;
      setTimeout(() => router.visit(route('portal.history')), 3000);
    }
  } catch {
    cardError.value = 'An unexpected error occurred.';
  } finally {
    paying.value = false;
  }
}
</script>
