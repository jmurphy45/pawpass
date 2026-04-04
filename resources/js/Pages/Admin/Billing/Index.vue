<template>
  <AdminLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">Billing</h1>

      <!-- Flash messages -->
      <div v-if="flash.success" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">
        {{ flash.success }}
      </div>
      <div v-if="flash.error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm">
        {{ flash.error }}
      </div>

      <!-- Platform subscription card -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Current Plan</p>
            <p class="text-lg font-semibold text-gray-900 capitalize">{{ billing.plan }}</p>
          </div>
          <span class="text-sm px-3 py-1 rounded-full" :class="{
            'bg-green-100 text-green-700': billing.status === 'active',
            'bg-amber-100 text-amber-700': billing.status === 'trialing',
            'bg-red-100 text-red-700': billing.status === 'past_due',
          }">{{ billing.status }}</span>
        </div>
        <div v-if="billing.trial_ends_at" class="text-sm text-gray-600">
          Trial ends: {{ billing.trial_ends_at }}
        </div>
      </div>

      <!-- Plan selection -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Choose a Plan</h2>

          <!-- Billing cycle toggle -->
          <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
            <button
              @click="cycle = 'monthly'"
              class="px-3 py-1.5 text-sm rounded-md transition-colors"
              :class="cycle === 'monthly' ? 'bg-white text-gray-900 shadow-sm font-medium' : 'text-gray-500 hover:text-gray-700'"
            >Monthly</button>
            <button
              @click="cycle = 'annual'"
              class="px-3 py-1.5 text-sm rounded-md transition-colors"
              :class="cycle === 'annual' ? 'bg-white text-gray-900 shadow-sm font-medium' : 'text-gray-500 hover:text-gray-700'"
            >Annual</button>
          </div>
        </div>

        <!-- Plan cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div
            v-for="plan in plans"
            :key="plan.slug"
            class="rounded-xl border-2 p-5 flex flex-col gap-4 transition-colors"
            :class="plan.slug === billing.plan ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
          >
            <div>
              <p class="font-semibold text-gray-900">{{ plan.name }}</p>
              <p class="text-sm text-gray-500 mt-0.5">{{ plan.description }}</p>
            </div>

            <div class="text-2xl font-bold text-gray-900">
              ${{ Math.floor((cycle === 'annual' ? plan.annual_price_cents : plan.monthly_price_cents) / 100) }}<span class="text-base font-normal text-gray-500">/mo</span>
            </div>
            <p class="text-xs text-gray-400">2.9% + 30¢ + {{ plan.platform_fee_pct }}% platform fee per transaction</p>

            <ul class="space-y-1.5 flex-1">
              <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-gray-700">
                <svg class="w-4 h-4 text-indigo-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                {{ feature }}
              </li>
            </ul>

            <!-- Current plan (trialing) -->
            <button
              v-if="plan.slug === billing.plan && billing.status === 'trialing'"
              disabled
              class="w-full text-sm py-2 rounded-lg bg-indigo-100 text-indigo-400 cursor-not-allowed font-medium"
            >Current Plan (Trial)</button>

            <!-- Current plan (active) -->
            <button
              v-else-if="plan.slug === billing.plan && billing.status === 'active'"
              disabled
              class="w-full text-sm py-2 rounded-lg bg-indigo-100 text-indigo-400 cursor-not-allowed font-medium"
            >Current Plan</button>

            <!-- Subscribe (trialing or free_tier) -->
            <button
              v-else-if="billing.status === 'trialing' || billing.status === 'free_tier'"
              @click="openSubscribeFlow(plan.slug)"
              :disabled="cardModal.processing"
              class="w-full text-sm py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 font-medium transition-colors"
            >Subscribe</button>

            <!-- Upgrade / Downgrade (active or past_due) -->
            <button
              v-else-if="billing.status === 'active' || billing.status === 'past_due'"
              @click="submitUpgrade(plan.slug)"
              :disabled="upgradeForm.processing"
              class="w-full text-sm py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 font-medium transition-colors"
            >{{ plan.sort_order > currentPlanOrder ? 'Upgrade' : 'Downgrade' }}</button>
          </div>
        </div>

        <!-- Cancel / cancellation notice -->
        <div class="pt-2">
          <p v-if="billing.plan_cancel_at_period_end" class="text-sm text-amber-700">
            Cancellation scheduled at period end.
          </p>
          <button
            v-else-if="billing.platform_stripe_sub_id"
            @click="submitCancel"
            :disabled="cancelForm.processing"
            class="text-sm text-red-600 hover:underline disabled:opacity-50"
          >Cancel subscription</button>
        </div>
      </div>

      <!-- Payment Method card -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Payment Method</h2>
          <button
            @click="openUpdatePaymentModal"
            class="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
          >Update card</button>
        </div>
        <div v-if="payment_method" class="flex items-center gap-3 text-sm text-gray-700">
          <span class="capitalize font-medium">{{ payment_method.brand }}</span>
          <span>ending in {{ payment_method.last4 }}</span>
          <span class="text-gray-400">·</span>
          <span>Expires {{ payment_method.exp_month }}/{{ payment_method.exp_year }}</span>
        </div>
        <div v-else class="text-sm text-gray-500">No card on file</div>
      </div>

      <!-- Payment processing card -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">Payment Processing</h2>

        <!-- Still provisioning -->
        <div v-if="!stripe_account_id" class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-blue-800 text-sm">
          Your Stripe payment account is being set up — this usually takes under a minute. Refresh the page to check.
        </div>

        <!-- Account provisioned — mount embedded component -->
        <template v-else>
          <div
            v-if="stripeError"
            class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm"
          >
            {{ stripeError }}
          </div>
          <div v-else id="stripe-connect-container" class="min-h-[400px]" />
        </template>
      </div>
    </div>

    <!-- Card collection modal -->
    <Teleport to="body">
      <div
        v-if="cardModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="closeCardModal"
      >
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6 space-y-5">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ cardModal.mode === 'update_payment' ? 'Update Payment Method' : 'Add Payment Method' }}
            </h3>
            <button @click="closeCardModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <p class="text-sm text-gray-600">
            {{ cardModal.mode === 'update_payment' ? 'Enter your new card details.' : cardModal.mode === 'confirm_existing' ? 'Confirm your subscription using the card on file.' : 'Enter your card details to activate your subscription.' }}
          </p>

          <!-- Existing card confirmation -->
          <div v-if="cardModal.mode === 'confirm_existing' && payment_method" class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 bg-gray-50 text-sm text-gray-700">
            <span class="capitalize font-medium">{{ payment_method.brand }}</span>
            <span>ending in {{ payment_method.last4 }}</span>
            <span class="text-gray-400">·</span>
            <span>Expires {{ payment_method.exp_month }}/{{ payment_method.exp_year }}</span>
          </div>

          <!-- Stripe Card Element (new card modes only) -->
          <div v-else>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Card details</label>
            <div
              id="card-element"
              class="border border-gray-300 rounded-lg px-3 py-3 bg-white focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500"
            />
            <p v-if="cardModal.cardError" class="mt-1.5 text-sm text-red-600">{{ cardModal.cardError }}</p>
          </div>

          <div v-if="cardModal.error" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-red-800 text-sm">
            {{ cardModal.error }}
          </div>

          <div class="flex gap-3 justify-end">
            <button
              @click="closeCardModal"
              :disabled="cardModal.processing"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            >Cancel</button>
            <button
              @click="cardModal.mode === 'confirm_existing' ? confirmSubscribeExisting() : confirmSubscribe()"
              :disabled="cardModal.processing"
              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
            >
              <svg v-if="cardModal.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ cardModal.processing ? 'Processing…' : (cardModal.mode === 'update_payment' ? 'Save Card' : 'Subscribe') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
  <AppModal :open="confirmModal.open" :title="confirmModal.title" :message="confirmModal.message" @confirm="handleConfirm" @cancel="handleCancel" />
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { loadConnectAndInitialize } from '@stripe/connect-js';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeCardElement } from '@stripe/stripe-js';
import axios from 'axios';

interface Plan {
  slug: string;
  name: string;
  description: string;
  monthly_price_cents: number;
  annual_price_cents: number;
  features: string[];
  staff_limit: number | null;
  platform_fee_pct: number;
  sort_order: number;
}

interface PaymentMethod {
  brand: string;
  last4: string;
  exp_month: number;
  exp_year: number;
}

const props = defineProps<{
  billing: {
    plan: string;
    status: string;
    trial_ends_at: string | null;
    plan_current_period_end: string | null;
    plan_past_due_since: string | null;
    plan_cancel_at_period_end: boolean;
    plan_billing_cycle: string | null;
    platform_stripe_sub_id: string | null;
  };
  plans: Plan[];
  stripe_key: string;
  stripe_account_id: string | null;
  stripe_onboarded: boolean;
  payment_method: PaymentMethod | null;
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string }) ?? {});

const cycle = ref<'monthly' | 'annual'>((props.billing.plan_billing_cycle as 'monthly' | 'annual') ?? 'monthly');

const currentPlanOrder = computed(() => {
  const current = props.plans.find((p) => p.slug === props.billing.plan);
  return current?.sort_order ?? 0;
});

// ── Card modal state ─────────────────────────────────────────────────────────

type CardModalMode = 'subscribe' | 'update_payment' | 'confirm_existing';

const cardModal = ref({
  open: false,
  processing: false,
  error: null as string | null,
  cardError: null as string | null,
  planSlug: '' as string,
  mode: 'subscribe' as CardModalMode,
});

let stripeInstance: Stripe | null = null;
let cardElement: StripeCardElement | null = null;

function openSubscribeFlow(planSlug: string) {
  if (props.payment_method) {
    cardModal.value.open = true;
    cardModal.value.planSlug = planSlug;
    cardModal.value.mode = 'confirm_existing';
    cardModal.value.error = null;
    cardModal.value.cardError = null;
  } else {
    openCardModal(planSlug);
  }
}

async function confirmSubscribeExisting() {
  cardModal.value.processing = true;
  cardModal.value.error = null;

  router.post(
    route('admin.billing.subscribe'),
    { plan: cardModal.value.planSlug, cycle: cycle.value },
    {
      onError: (errors) => {
        cardModal.value.error = Object.values(errors).flat().join(' ') || 'Subscription failed.';
      },
      onFinish: () => {
        cardModal.value.processing = false;
      },
      onSuccess: () => {
        cardModal.value.processing = false;
        closeCardModal();
      },
    },
  );
}

async function openCardModal(planSlug: string) {
  cardModal.value.open = true;
  cardModal.value.planSlug = planSlug;
  cardModal.value.mode = 'subscribe';
  cardModal.value.error = null;
  cardModal.value.cardError = null;

  await nextTick();

  if (!stripeInstance) {
    stripeInstance = await loadStripe(props.stripe_key);
  }

  if (!stripeInstance) {
    cardModal.value.error = 'Stripe could not be loaded. Please try again.';
    return;
  }

  const elements = stripeInstance.elements();
  cardElement = elements.create('card', {
    style: {
      base: {
        fontSize: '15px',
        color: '#111827',
        '::placeholder': { color: '#9ca3af' },
      },
    },
  });

  cardElement.mount('#card-element');

  cardElement.on('change', (event) => {
    cardModal.value.cardError = event.error ? event.error.message : null;
  });
}

async function openUpdatePaymentModal() {
  cardModal.value.open = true;
  cardModal.value.mode = 'update_payment';
  cardModal.value.error = null;
  cardModal.value.cardError = null;

  await nextTick();

  if (!stripeInstance) {
    stripeInstance = await loadStripe(props.stripe_key);
  }

  if (!stripeInstance) {
    cardModal.value.error = 'Stripe could not be loaded. Please try again.';
    return;
  }

  const elements = stripeInstance.elements();
  cardElement = elements.create('card', {
    style: {
      base: {
        fontSize: '15px',
        color: '#111827',
        '::placeholder': { color: '#9ca3af' },
      },
    },
  });

  cardElement.mount('#card-element');

  cardElement.on('change', (event) => {
    cardModal.value.cardError = event.error ? event.error.message : null;
  });
}

function closeCardModal() {
  if (cardModal.value.processing) return;
  cardModal.value.open = false;
  cardElement?.unmount();
  cardElement = null;
}

async function confirmSubscribe() {
  if (!stripeInstance || !cardElement) return;

  cardModal.value.processing = true;
  cardModal.value.error = null;

  try {
    // 1. Get a SetupIntent client_secret from the server
    let siData: { client_secret?: string; message?: string };
    try {
      const siResp = await axios.post<{ client_secret: string }>(
        route('admin.billing.setup-intent'),
      );
      siData = siResp.data;
    } catch (siErr) {
      if (axios.isAxiosError(siErr) && siErr.response?.status === 419) {
        cardModal.value.error = 'Your session has expired. Please refresh the page.';
      } else if (axios.isAxiosError(siErr)) {
        cardModal.value.error =
          (siErr.response?.data as { message?: string })?.message ??
          `Server error (${siErr.response?.status ?? 'unknown'})`;
      } else {
        cardModal.value.error = 'An unexpected error occurred.';
      }
      return;
    }
    if (!siData.client_secret) {
      cardModal.value.error = 'Could not create setup intent. Please try again.';
      return;
    }

    // 2. Confirm card setup with Stripe
    const { setupIntent, error } = await stripeInstance.confirmCardSetup(siData.client_secret, {
      payment_method: { card: cardElement },
    });

    if (error) {
      cardModal.value.cardError = error.message ?? 'Card confirmation failed.';
      return;
    }

    const paymentMethodId = setupIntent?.payment_method;
    if (typeof paymentMethodId !== 'string') {
      cardModal.value.error = 'Could not retrieve payment method. Please try again.';
      return;
    }

    if (cardModal.value.mode === 'update_payment') {
      // POST to update payment method endpoint
      try {
        await axios.post<{ success: boolean }>(
          route('admin.billing.payment-method'),
          { payment_method_id: paymentMethodId },
        );
      } catch (pmErr) {
        if (axios.isAxiosError(pmErr) && pmErr.response?.status === 419) {
          cardModal.value.error = 'Your session has expired. Please refresh the page.';
        } else if (axios.isAxiosError(pmErr)) {
          cardModal.value.error =
            (pmErr.response?.data as { message?: string })?.message ??
            `Server error (${pmErr.response?.status ?? 'unknown'})`;
        } else {
          cardModal.value.error = 'An unexpected error occurred.';
        }
        return;
      }

      cardModal.value.processing = false;
      closeCardModal();
      router.reload();
    } else {
      // 3. POST subscribe with the payment method ID
      router.post(
        route('admin.billing.subscribe'),
        { plan: cardModal.value.planSlug, cycle: cycle.value, payment_method_id: paymentMethodId },
        {
          onError: (errors) => {
            cardModal.value.error = Object.values(errors).flat().join(' ') || 'Subscription failed.';
          },
          onFinish: () => {
            cardModal.value.processing = false;
          },
          onSuccess: () => {
            cardModal.value.processing = false;
            closeCardModal();
          },
        },
      );
    }
  } catch (err) {
    cardModal.value.error = err instanceof Error ? err.message : 'An unexpected error occurred.';
  } finally {
    // processing is reset in onFinish above for the router call
    // but we need to reset it here if we returned early
    if (cardModal.value.open) {
      cardModal.value.processing = false;
    }
  }
}

// ── Upgrade form ─────────────────────────────────────────────────────────────

const upgradeForm = useForm({ plan: '', cycle: 'monthly' });
function submitUpgrade(slug: string) {
  upgradeForm.plan  = slug;
  upgradeForm.cycle = cycle.value;
  upgradeForm.post(route('admin.billing.upgrade'));
}

// ── Cancel form ──────────────────────────────────────────────────────────────

const cancelForm = useForm({});

const confirmModal = ref<{ open: boolean; title: string; message: string; onConfirm: (() => void) | null }>
  ({ open: false, title: '', message: '', onConfirm: null });

function askConfirm(title: string, message: string, onConfirm: () => void) {
  confirmModal.value = { open: true, title, message, onConfirm };
}
function handleConfirm() { confirmModal.value.onConfirm?.(); confirmModal.value.open = false; }
function handleCancel() { confirmModal.value.open = false; }

function submitCancel() {
  askConfirm(
    'Cancel Subscription',
    'Your subscription will be cancelled at the end of the current billing period.',
    () => cancelForm.post(route('admin.billing.cancel')),
  );
}

// ── Stripe Connect embed ─────────────────────────────────────────────────────

const stripeError = ref<string | null>(null);

onMounted(() => {
  if (!props.stripe_account_id) return;

  if (!props.stripe_key || !props.stripe_key.startsWith('pk_')) {
    stripeError.value = 'Stripe is not configured. Please contact support.';
    return;
  }

  let instance: ReturnType<typeof loadConnectAndInitialize>;

  try {
    instance = loadConnectAndInitialize({
      publishableKey: props.stripe_key,
      fetchClientSecret: async () => {
        const resp = await fetch(route('admin.billing.account-session'));
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.client_secret) {
          const msg = data.error ?? data.message ?? `Stripe session error (HTTP ${resp.status})`;
          stripeError.value = msg;
          throw new Error(msg);
        }
        return data.client_secret as string;
      },
    });
  } catch (err) {
    stripeError.value = err instanceof Error ? err.message : 'Stripe Connect could not be initialized.';
    return;
  }

  const componentName = props.stripe_onboarded ? 'account-management' : 'account-onboarding';

  let el: ReturnType<typeof instance.create>;
  try {
    el = instance.create(componentName);
  } catch (err) {
    stripeError.value = err instanceof Error ? err.message : 'Stripe Connect component could not be created.';
    return;
  }

  const container = document.getElementById('stripe-connect-container');
  if (container) {
    container.appendChild(el);
  }
});
</script>
