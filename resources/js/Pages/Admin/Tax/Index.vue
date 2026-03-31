<template>
  <AdminLayout title="Tax Settings">
    <div class="max-w-4xl mx-auto space-y-8">

      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Tax Settings</h1>
        <p class="mt-1 text-sm text-gray-500">
          Configure your tax settings and registrations so customers are charged the correct amount.
        </p>
      </div>

      <!-- Guard: Stripe not yet connected -->
      <div v-if="!stripe_account_id" class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-blue-800 text-sm">
        You need to complete your Stripe account setup before configuring tax.
        <a :href="route('admin.billing.index')" class="font-medium underline ml-1">Go to Billing</a>
      </div>

      <template v-else>
        <!-- Error state -->
        <div
          v-if="stripeError"
          class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm"
        >
          {{ stripeError }}
        </div>

        <template v-else>
          <!-- Tax Settings component -->
          <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Head Office &amp; Tax Code</h2>
              <p class="text-sm text-gray-500 mt-0.5">Enter your business location and default tax category.</p>
            </div>
            <div id="stripe-tax-settings-container" class="min-h-[200px]" />
          </div>

          <!-- Tax Registrations component -->
          <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Tax Registrations</h2>
              <p class="text-sm text-gray-500 mt-0.5">Add the jurisdictions where you are registered to collect tax.</p>
            </div>
            <div id="stripe-tax-registrations-container" class="min-h-[200px]" />
          </div>
        </template>
      </template>

    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { loadConnectAndInitialize } from '@stripe/connect-js';

const props = defineProps<{
  stripe_key: string;
  stripe_account_id: string | null;
}>();

const stripeError = ref<string | null>(null);

onMounted(async () => {
  if (!props.stripe_account_id) return;

  let instance: ReturnType<typeof loadConnectAndInitialize>;

  try {
    instance = loadConnectAndInitialize({
      publishableKey: props.stripe_key,
      fetchClientSecret: async () => {
        const resp = await fetch(route('admin.tax.account-session'));
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

  try {
    const taxSettings = instance.create('tax-settings');
    const taxSettingsContainer = document.getElementById('stripe-tax-settings-container');
    if (taxSettingsContainer) taxSettingsContainer.appendChild(taxSettings);
  } catch (err) {
    stripeError.value = err instanceof Error ? err.message : 'Tax settings component could not be created.';
    return;
  }

  try {
    const taxRegistrations = instance.create('tax-registrations');
    const taxRegistrationsContainer = document.getElementById('stripe-tax-registrations-container');
    if (taxRegistrationsContainer) taxRegistrationsContainer.appendChild(taxRegistrations);
  } catch (err) {
    stripeError.value = err instanceof Error ? err.message : 'Tax registrations component could not be created.';
  }
});
</script>
