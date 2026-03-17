<template>
  <PortalLayout>
    <div class="space-y-8">
      <h1 class="text-2xl font-bold text-gray-900">Subscribe</h1>

      <!-- Dog selector -->
      <div class="max-w-xs">
        <label class="block text-sm font-medium text-gray-700 mb-1">Select Dog</label>
        <select
          v-model="selectedDogId"
          class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">— choose a dog —</option>
          <option v-for="dog in dogs" :key="dog.id" :value="dog.id">{{ dog.name }}</option>
        </select>
      </div>

      <!-- Package grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="pkg in packages"
          :key="pkg.id"
          class="relative rounded-2xl border p-6 shadow-sm cursor-pointer transition-all"
          :class="selectedPackageId === pkg.id
            ? 'border-indigo-500 ring-2 ring-indigo-500 bg-indigo-50'
            : 'border-gray-200 bg-white hover:border-indigo-300'"
          @click="selectedPackageId = pkg.id"
        >
          <div
            v-if="pkg.is_featured"
            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3 py-0.5 text-xs font-semibold text-white"
          >Popular</div>

          <p class="font-bold text-gray-900 text-lg">{{ pkg.name }}</p>
          <p class="text-3xl font-bold text-gray-900 mt-2">${{ (pkg.price_cents / 100).toFixed(2) }}</p>
          <p class="text-xs text-gray-500">per month</p>

          <ul class="mt-4 space-y-1 text-sm text-gray-600">
            <li v-if="pkg.credits">{{ pkg.credits }} credits / month</li>
            <li v-else>Unlimited visits / month</li>
            <li v-if="pkg.max_dogs > 1">Up to {{ pkg.max_dogs }} dogs</li>
          </ul>
        </div>
      </div>

      <!-- Card element + subscribe button -->
      <div class="max-w-xs">
        <div id="card-element" class="rounded-lg border border-gray-300 px-3 py-3 mb-4 bg-white" />
        <p v-if="cardError" class="text-xs text-red-600 mb-3">{{ cardError }}</p>

        <button
          @click="subscribe"
          :disabled="!selectedPackageId || !selectedDogId || subscribing"
          class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          {{ subscribing ? 'Processing…' : 'Subscribe' }}
        </button>

        <div v-if="success" class="mt-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">
          Subscription activated! Redirecting…
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Package } from '@/types';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeCardElement } from '@stripe/stripe-js';

interface DogOption { id: string; name: string; credits_expire_at: string | null; }

const props = defineProps<{
  packages: Package[];
  dogs: DogOption[];
  stripe_key: string;
}>();

const selectedPackageId = ref('');
const selectedDogId = ref('');
const subscribing = ref(false);
const success = ref(false);
const cardError = ref('');

let stripe: Stripe | null = null;
let cardElement: StripeCardElement | null = null;

onMounted(async () => {
  if (!props.stripe_key) return;
  stripe = await loadStripe(props.stripe_key);
  if (!stripe) return;
  const elements = stripe.elements();
  cardElement = elements.create('card', {
    style: { base: { fontSize: '14px', color: '#1f2937' } },
  });
  cardElement.mount('#card-element');
});

async function subscribe() {
  if (!selectedPackageId.value || !selectedDogId.value || !stripe || !cardElement) return;

  subscribing.value = true;
  cardError.value = '';

  try {
    const resp = await fetch(route('portal.subscribe.store'), {
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

    const result = await stripe.confirmCardSetup(client_secret, {
      payment_method: { card: cardElement },
    });

    if (result.error) {
      cardError.value = result.error.message ?? 'Setup failed.';
    } else {
      success.value = true;
      setTimeout(() => router.visit(route('portal.history')), 3000);
    }
  } catch {
    cardError.value = 'An unexpected error occurred.';
  } finally {
    subscribing.value = false;
  }
}
</script>
