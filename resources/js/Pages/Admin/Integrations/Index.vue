<template>
  <AdminLayout>
    <div class="space-y-8">
      <AppPageHeader title="PIMS Integrations">
        <template #description>
          Connect your Practice Information Management System to automatically sync client and patient data.
        </template>
      </AppPageHeader>

      <!-- Available providers -->
      <section>
        <h2 class="text-base font-semibold text-text-body mb-4">Available Integrations</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <AppCard
            v-for="provider in availableProviders"
            :key="provider.key"
            :padded="true"
            class="flex items-center justify-between gap-4"
          >
            <div>
              <p class="font-semibold text-text-body">{{ provider.label }}</p>
              <p class="text-sm text-text-muted mt-0.5">
                {{ providerDescription(provider.key) }}
              </p>
            </div>
            <AppButton
              v-if="!connectedKeys.has(provider.key)"
              variant="primary"
              size="sm"
              @click="openConnect(provider.key)"
            >
              Connect
            </AppButton>
            <AppBadge v-else color="green">Connected</AppBadge>
          </AppCard>
        </div>
      </section>

      <!-- Connected integrations -->
      <section v-if="integrations.length">
        <h2 class="text-base font-semibold text-text-body mb-4">Connected</h2>
        <AppCard>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border-warm text-left text-text-muted">
                <th class="pb-3 pr-6 font-medium">Provider</th>
                <th class="pb-3 pr-6 font-medium">Status</th>
                <th class="pb-3 pr-6 font-medium">Last Sync</th>
                <th class="pb-3 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border-warm">
              <tr v-for="integration in integrations" :key="integration.id">
                <td class="py-3 pr-6 font-medium text-text-body">{{ integration.provider_label }}</td>
                <td class="py-3 pr-6">
                  <AppBadge :color="statusColor(integration.status)">
                    {{ integration.status }}
                  </AppBadge>
                </td>
                <td class="py-3 pr-6 text-text-muted">
                  {{ formatDate(integration.last_delta_sync_at ?? integration.last_full_sync_at) }}
                </td>
                <td class="py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      class="text-xs text-indigo-600 hover:underline"
                      :disabled="testing === integration.id"
                      @click="testConnection(integration)"
                    >
                      {{ testing === integration.id ? 'Testing…' : 'Test' }}
                    </button>
                    <button
                      class="text-xs text-text-muted hover:underline"
                      @click="openLogs(integration)"
                    >
                      Logs
                    </button>
                    <button
                      class="text-xs text-red-500 hover:underline"
                      @click="confirmRemove(integration)"
                    >
                      Remove
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </AppCard>
      </section>

      <!-- Connect modal -->
      <AppModal :show="showConnect" @close="showConnect = false">
        <div class="p-6 space-y-4">
          <h3 class="text-lg font-semibold text-text-body">
            Connect {{ connectingLabel }}
          </h3>
          <form @submit.prevent="submitConnect" class="space-y-4">
            <div v-if="connectingKey === 'ezyvet'">
              <label class="block text-sm font-medium text-text-body mb-1">Practice Base URL</label>
              <input
                v-model="connectForm.api_base_url"
                type="url"
                class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                placeholder="https://yourpractice.ezyvet.com/api/v1"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-body mb-1">
                {{ connectingKey === 'vetspire' ? 'API Key' : 'Client ID' }}
              </label>
              <input
                v-model="connectForm.client_id"
                type="text"
                class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                required
              />
            </div>
            <div v-if="connectingKey === 'ezyvet'">
              <label class="block text-sm font-medium text-text-body mb-1">Client Secret</label>
              <input
                v-model="connectForm.client_secret"
                type="password"
                class="w-full rounded-lg border border-border-warm px-3 py-2.5 text-sm bg-white text-text-body outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                required
              />
            </div>
            <p v-if="connectError" class="text-sm text-red-600">{{ connectError }}</p>
            <div class="flex gap-3 pt-2">
              <AppButton type="submit" variant="primary" :disabled="connecting">
                {{ connecting ? 'Saving…' : 'Save Integration' }}
              </AppButton>
              <AppButton type="button" variant="secondary" @click="showConnect = false">Cancel</AppButton>
            </div>
          </form>
        </div>
      </AppModal>

      <!-- Sync log drawer -->
      <AppModal :show="showLogs" @close="showLogs = false">
        <div class="p-6 space-y-4 min-w-[560px]">
          <h3 class="text-lg font-semibold text-text-body">Sync History — {{ logsIntegration?.provider_label }}</h3>
          <div v-if="logsLoading" class="text-sm text-text-muted">Loading…</div>
          <div v-else-if="!logs.length" class="text-sm text-text-muted">No sync runs yet.</div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-border-warm text-left text-text-muted">
                <th class="pb-2 pr-4 font-medium">Started</th>
                <th class="pb-2 pr-4 font-medium">Status</th>
                <th class="pb-2 pr-4 font-medium">Clients</th>
                <th class="pb-2 pr-4 font-medium">Patients</th>
                <th class="pb-2 font-medium">Vax</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border-warm">
              <tr v-for="log in logs" :key="log.id">
                <td class="py-2 pr-4 text-text-muted">{{ formatDate(log.started_at) }}</td>
                <td class="py-2 pr-4">
                  <AppBadge :color="logStatusColor(log.status)">{{ log.status }}</AppBadge>
                </td>
                <td class="py-2 pr-4">{{ log.clients_processed }}</td>
                <td class="py-2 pr-4">{{ log.patients_processed }}</td>
                <td class="py-2">{{ log.vaccinations_processed }}</td>
              </tr>
            </tbody>
          </table>
          <AppButton variant="secondary" @click="showLogs = false">Close</AppButton>
        </div>
      </AppModal>

      <!-- Remove confirmation -->
      <AppModal :show="!!removingIntegration" @close="removingIntegration = null">
        <div class="p-6 space-y-4">
          <h3 class="text-lg font-semibold text-text-body">Remove Integration?</h3>
          <p class="text-sm text-text-muted">
            This will disconnect <strong>{{ removingIntegration?.provider_label }}</strong>. Synced customer and dog records will not be deleted.
          </p>
          <div class="flex gap-3">
            <AppButton variant="danger" :disabled="removing" @click="removeIntegration">
              {{ removing ? 'Removing…' : 'Remove' }}
            </AppButton>
            <AppButton variant="secondary" @click="removingIntegration = null">Cancel</AppButton>
          </div>
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppCard from '@/Components/AppCard.vue';
import AppButton from '@/Components/AppButton.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppModal from '@/Components/AppModal.vue';

interface Provider {
  key: string;
  label: string;
}

interface Integration {
  id: string;
  provider: string;
  provider_label: string;
  api_base_url: string | null;
  status: 'active' | 'error' | 'disabled';
  last_full_sync_at: string | null;
  last_delta_sync_at: string | null;
  sync_error: string | null;
}

interface SyncLog {
  id: number;
  status: string;
  started_at: string;
  clients_processed: number;
  patients_processed: number;
  vaccinations_processed: number;
}

const props = defineProps<{
  providers: Provider[];
  integrations: Integration[];
}>();

const integrations = ref<Integration[]>(props.integrations);

const connectedKeys = computed(() => new Set(integrations.value.map((i) => i.provider)));

const availableProviders = computed(() => props.providers);

// ---- Connect modal ----
const showConnect = ref(false);
const connectingKey = ref('');
const connectingLabel = ref('');
const connecting = ref(false);
const connectError = ref('');
const connectForm = ref({ api_base_url: '', client_id: '', client_secret: '' });

function openConnect(key: string) {
  connectingKey.value = key;
  connectingLabel.value = props.providers.find((p) => p.key === key)?.label ?? key;
  connectForm.value = { api_base_url: '', client_id: '', client_secret: '' };
  connectError.value = '';
  showConnect.value = true;
}

async function submitConnect() {
  connecting.value = true;
  connectError.value = '';

  const credentials =
    connectingKey.value === 'vetspire'
      ? { access_token: connectForm.value.client_id }
      : { client_id: connectForm.value.client_id, client_secret: connectForm.value.client_secret };

  try {
    const { data } = await axios.post('/api/admin/v1/pims-integrations', {
      provider: connectingKey.value,
      api_base_url: connectForm.value.api_base_url || null,
      credentials,
    });
    integrations.value.push(data.data);
    showConnect.value = false;
  } catch (e: any) {
    connectError.value = e.response?.data?.message ?? 'Failed to save integration.';
  } finally {
    connecting.value = false;
  }
}

// ---- Test connection ----
const testing = ref<string | null>(null);

async function testConnection(integration: Integration) {
  testing.value = integration.id;
  try {
    await axios.post(`/api/admin/v1/pims-integrations/${integration.id}/test-connection`);
    // Refresh to get updated status
    router.reload({ only: [] });
  } catch (e: any) {
    const msg = e.response?.data?.data?.error ?? 'Connection test failed.';
    alert(msg);
  } finally {
    testing.value = null;
  }
}

// ---- Sync logs drawer ----
const showLogs = ref(false);
const logsIntegration = ref<Integration | null>(null);
const logs = ref<SyncLog[]>([]);
const logsLoading = ref(false);

async function openLogs(integration: Integration) {
  logsIntegration.value = integration;
  showLogs.value = true;
  logsLoading.value = true;
  logs.value = [];

  try {
    const { data } = await axios.get(`/api/admin/v1/pims-integrations/${integration.id}/sync-logs`);
    logs.value = data.data;
  } finally {
    logsLoading.value = false;
  }
}

// ---- Remove integration ----
const removingIntegration = ref<Integration | null>(null);
const removing = ref(false);

function confirmRemove(integration: Integration) {
  removingIntegration.value = integration;
}

async function removeIntegration() {
  if (!removingIntegration.value) return;
  removing.value = true;

  try {
    await axios.delete(`/api/admin/v1/pims-integrations/${removingIntegration.value.id}`);
    integrations.value = integrations.value.filter((i) => i.id !== removingIntegration.value!.id);
    removingIntegration.value = null;
  } finally {
    removing.value = false;
  }
}

// ---- Helpers ----
function statusColor(status: string): 'green' | 'red' | 'gray' {
  if (status === 'active') return 'green';
  if (status === 'error') return 'red';
  return 'gray';
}

function logStatusColor(status: string): 'green' | 'red' | 'blue' | 'gray' {
  if (status === 'completed') return 'green';
  if (status === 'failed') return 'red';
  if (status === 'running') return 'blue';
  return 'gray';
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString();
}

function providerDescription(key: string): string {
  const descriptions: Record<string, string> = {
    ezyvet: 'Cloud-based vet PIMS with REST API. OAuth2 client credentials.',
    vetspire: 'Modern cloud vet PIMS with GraphQL API.',
  };
  return descriptions[key] ?? 'PIMS provider';
}
</script>
